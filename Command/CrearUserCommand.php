<?php
namespace ElMijo\SeguridadBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use ElMijo\SeguridadBundle\Entity\Rol;
use ElMijo\SeguridadBundle\Entity\Usuario;
use ElMijo\SeguridadBundle\Tools\MensajesCommmand as MSJ;

class CrearUserCommand extends ContainerAwareCommand
{
    private $initRoles = array(
        "Super Administrador" => "ROLE_SUPER_ADMIN",
        "Administrador"       => "ROLE_ADMIN",
        "Usuario"             =>"ROLE_USER"
    );
    private $validCode = 0;

    private $errorCode = 0;

    private $doctrine;

    private $em;

    private $entityRol;

    private $entityUsuario;

    private $mailer;

    private $username;

    private $password;

    private $email;

    private $superadmin;

    private $existeSuperAdmin;

    private $output;

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('security:create:user')
            ->setDescription('Permite crear un usuario')
            ->setDefinition(array(
                new InputArgument('email', InputArgument::REQUIRED, 'El email del usuario'),
                new InputOption('super-admin', null, InputOption::VALUE_NONE, 'Pemite craer al Super Usuario')
            ))
            ->setHelp(MSJ::SECURITY_CREATE_USER);
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username    = $this->username;
        $email       = $this->email;
        $password    = $this->password;
        $roles       = $this->roles;
        
        if(!$this->existeSuperAdmin&&!!$this->superadmin)
        {
            $output->writeln(MSJ::CREANDO_SUPER_ADMIN);
        }
        else
        {
            $output->writeln(MSJ::CREANDO_USUARIO);
        }

        $output->writeln($this->crearUsuario($username,$email,$password,$roles));
    }

    /**
     * @see Command
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->init($input->getOption('super-admin'));

        if(!$this->existeSuperAdmin&&!$this->superadmin)
        {
            $output->writeln(MSJ::NO_EXISTE_SUPER_ADMIN);exit;
        }
        if(!!$this->existeSuperAdmin&&!!$this->superadmin)
        {
            $output->writeln(MSJ::EXISTE_SUPER_ADMIN);exit;
        }
        if(!!$this->existeSuperAdmin&&!$this->superadmin)
        {
            $this->ValidarCodigo($this->entityUsuario->superAdminEmail());
        }

        $this->emailInterfaz($input->getArgument('email'));

        if(!$this->existeSuperAdmin&&!!$this->superadmin)
        {
            $this->ValidarCodigo($this->email);    
        }
        $this
            ->usernameInterfaz()
            ->passwordInterfaz()
            ->rolesInterfaz()
        ;
    }

    /**
     * Metodo para crear los roles iniciales de la aplicación
     * @return void
     */
    private function crearRoles()
    {
        foreach ($this->initRoles as $name => $role)
        {
            if (is_null($this->entityRol->findOneBy(array("role" => $role))))
            {
                $rol = new Rol();
                $rol->setName($name)->setRole($role);
                $this->em->persist($rol);
                $this->em->flush();
            }    
        }
    }

    private function arrayRolesRole()
    {
        $roles = array();
        foreach ($this->entityRol->findAll() as $rol)
        {
            $role = $rol->getRole();
            if($role!=$this->initRoles["Super Administrador"])
            {
                array_push($roles, $role);
            }
        }
        return $roles;
    }

    /**
     * Metodo para crear el usaurio administrativo
     * @param  string $username     Nombre de usuario
     * @param  string $email        Correo electronico del usuario
     * @param  string $password     Clave del usuario
     * @return string
     */
    private function crearUsuario($username,$email,$password,$roles)
    {
        $usuario  = new Usuario();

        $usuario->setUsername($username)
                ->setPassword($this->encodePassword($usuario,$password))
                ->setEmail($email)
        ;
        foreach ($roles as $rol)
        {
            $usuario->addRole($this->entityRol->findOneBy(array("role" => $rol)));
        }

        $this->em->persist($usuario);

        $this->em->flush();

        return <<<EOT

    <info>Usuario $username creado Exitosamente!!</info>

EOT;
    }    

    private function encodePassword($usuario,$password)
    {
        return $this
                    ->getContainer()
                    ->get('security.encoder_factory')
                    ->getEncoder($usuario)
                    ->encodePassword($password, $usuario->getSalt())
        ;
    }

    private function init($superadmin)
    {
        $this->doctrine         = $this->getContainer()->get('doctrine');
        $this->em               = $this->doctrine->getManager();
        $this->entityRol        = $this->doctrine->getRepository("ElMijoSeguridadBundle:Rol");
        $this->entityUsuario    = $this->doctrine->getRepository("ElMijoSeguridadBundle:Usuario");
        $this->mailer           = $this->getContainer()->get('security.sendmail');
        $this->existeSuperAdmin = $this->entityUsuario->existeUsuarioSuperAdmin();
        $this->superadmin       = $superadmin;
        $this->output           = new \Symfony\Component\Console\Output\ConsoleOutput();
        $this->crearRoles();
    }

    private function ValidarCodigo($email)
    {
        $this->validCode = intval(hexdec(md5(time()))/100000000000000000000000000000000);

        $this->output->writeln(MSJ::ENVIANDO_CODE);

        $this->mailer->send('jerry.anselmi@gmail.com',$email,$this->validCode,'proabndo');

        return $this->getHelper('dialog')->askAndValidate(
            $this->output,
            MSJ::INGRESE_CODE,
            function($code){
                if (empty($code))
                {
                    throw new \Exception('El Codigo no puede estar en blanco');
                }
                else if($code!=$this->validCode)
                {
                    $this->errorCode++;
                    if($this->errorCode<3)
                    {
                        throw new \Exception('El Codigo no es valido');
                    }
                    $this->output->writeln(MSJ::EXCEDE_INTENTOS);exit;
                }
                return TRUE;
            }
        );
    }

    private function usernameInterfaz()
    {
        $this->username = $this->getHelper('dialog')->askAndValidate(
            $this->output,
            MSJ::INGRESE_USERNAME,
            function($username) {
                if (empty($username))
                {
                    throw new \Exception('Username no puede estar en blanco');
                }
                else if(!!$this->entityUsuario->existeUsername($username))
                {
                    throw new \Exception("El usuario $username ya esta registrado");
                }
                return $username;
            }
        );
        return $this;
    }

    private function passwordInterfaz()
    {
        $this->password = (!$this->existeSuperAdmin&&!!$this->superadmin)?
                $this->getHelper('dialog')->askAndValidate(
                    $this->output,
                    MSJ::INGRESE_PASSWORD,
                    function($password) {
                        if (!!empty($password)) {
                            throw new \Exception('Password no puede estar en blanco');
                        }
                        return $password;
                    }
                ):base_convert(uniqid('pass', true), 10, 36)
        ;

        return $this;
    }

    private function emailInterfaz($email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = $this->getHelper('dialog')->askAndValidate(
                $this->output,
                MSJ::INGRESE_EMAIL,
                function($email)
                {
                    if (empty($email))
                    {
                        throw new \Exception('Email no puede estar en blanco');
                    }
                    else if(!filter_var($email, FILTER_VALIDATE_EMAIL))
                    {
                        throw new \Exception('Ingrese un Email valido');
                    }
                    
                    return $email;
                }
            );
        }
        if(!!$this->entityUsuario->existeEmail($email))
        {
            $this->output->writeln(<<<EOT

                <error>El email $email ya esta registrado</error>

EOT
            );
            exit;
        }

        $this->email = $email;

        return $this;
    }

    private function rolesInterfaz()
    {

        $allRolesArray = $this->arrayRolesRole();

        $allRoles      = implode(' ', $allRolesArray);

        $this->output->writeln(<<<EOT

<info>Seleccione los roles que desea asignarle al usuario, separados por un espacio

    Roles disponibles : <comment>$allRoles</comment></info>

EOT
        );

        $this->roles = (!!$this->existeSuperAdmin)?
                $this->getHelper('dialog')->askAndValidate(
                    $this->output,
                    MSJ::INGRESE_ROLES,
                    function($roles) use ($allRolesArray){
                        if (!!empty($roles)) {
                            throw new \Exception('Los roles no pueden estar en blanco');
                        }
                        else
                        {
                            $roles = array_filter(explode(" ",$roles));

                            foreach ($roles as $rol) {
                                if(!in_array(trim($rol), $allRolesArray))
                                {
                                    throw new \Exception("El rol $rol no existe!!");
                                }
                            }
                        }
                        return $roles;
                    }
                ):array($this->initRoles["Super Administrador"])
        ;
        return $this;
    }
}