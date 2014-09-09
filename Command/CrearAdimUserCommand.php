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

class CrearAdimUserCommand extends ContainerAwareCommand
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

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('security:create:admin-user')
            ->setDescription('Crear el usuario administrativo')
            ->setDefinition(array(
                new InputArgument('email', InputArgument::REQUIRED, 'El email del usuario administrativo'),
            ))
            ->setHelp(<<<EOT
El comando <info>security:crear:usuario-admin</info> permite crear un usuario administrativo valido para la aplicación:

  <info>php app/console security:crear:usuario-admin</info>

Tambien podria pasar el email como parametro:

  <info>php app/console security:create:admin-user micorreo@example.com </info>

EOT
            );
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username    = $this->username;
        $email       = $input->getArgument('email');
        $password    = $this->password;
        
        $output->writeln('<comment>Creando usuario Administrador...</comment>');
        $output->writeln($this->crearUsuarioAdmin($username,$email,$password));
    }

    /**
     * @see Command
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->init();

        if(!!$this->entityUsuario->existeUsuarioAdmin())
        {
            $output->writeln('<comment>El usuario administrativo ya fue creado</comment>');exit;
        }
        else
        {
            $output->writeln('<comment>Inicializando Roles...</comment>');
            $this->crearRoles();
        }

        if (!$input->getArgument('email')) {
            $email = $this->getHelper('dialog')->askAndValidate(
                $output,
                '<fg=cyan>Por favor ingrese el email:</fg=cyan>',
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

            $input->setArgument('email', $email);
        }
        else
        {
            $email = $input->getArgument('email');
        }

        $output->writeln("<comment>Validando Correo Electronico...</comment>");

        if(!!$this->entityUsuario->existeEmail($email))
        {
            throw new \Exception('El email $email ya esta registrado');
        }

        $output->writeln("<info>Correo valido!!</info>");

        
        $output->writeln("<comment>Enviando codifo de validación...</comment>");

        $this->mailer->send('jerry.anselmi@gmail.com','jerry.anselmi@gmail.com',$this->validCode,'proabndo');

        $this->getHelper('dialog')->askAndValidate(
            $output,
            '<fg=cyan>Por favor ingrese el codigo enviado a su coreo electronico:</fg=cyan>',
            function($code) use ($output){
                if (empty($code)) {
                    throw new \Exception('El Codigo no puede estar en blanco');
                }
                else if($code!=$this->validCode)
                {
                    $this->errorCode++;
                    if($this->errorCode<3)
                    {
                        throw new \Exception('El Codigo no es valido');
                    }
                    $output->writeln('<error>Excedio el numero de intentos</error>');exit;
                }
                return TRUE;
            }
        );

        $this->username = $this->getHelper('dialog')->askAndValidate(
            $output,
            '<fg=cyan>Por favor ingrese el username:</fg=cyan>',
            function($username) {
                if (empty($username))
                {
                    throw new \Exception('Username no puede estar en blanco');
                }
                else if(!!$this->entityUsuario->existeUsername($username))
                {
                    throw new Exception("El usuario $username ya esta registrado");
                }
                return $username;
            }
        );

        $this->password = $this->getHelper('dialog')->askAndValidate(
            $output,
            '<fg=cyan>Por favor ingrese el password:</fg=cyan>',
            function($password) {
                if (empty($password)) {
                    throw new \Exception('Password no puede estar en blanco');
                }
                return $password;
            }
        );
    }

    /**
     * Metodo para crear los roles iniciales de la aplicación
     * @return void
     */
    private function crearRoles()
    {
        $doctrine    = $this->getContainer()->get('doctrine');
        $em          = $doctrine->getManager();
        $entityRol   = $doctrine->getRepository("ElMijoSeguridadBundle:Rol");

        foreach ($this->initRoles as $name => $role)
        {
            if (is_null($entityRol->findOneBy(array("role" => $role))))
            {
                $rol = new Rol();
                $rol->setName($name)->setRole($role);
                $em->persist($rol);
                $em->flush();
            }    
        }
    }

    /**
     * Metodo para crear el usaurio administrativo
     * @param  string $username     Nombre de usuario
     * @param  string $email        Correo electronico del usuario
     * @param  string $password     Clave del usuario
     * @return string
     */
    private function crearUsuarioAdmin($username,$email,$password)
    {
        $doctrine      = $this->getContainer()->get('doctrine');
        $em            = $doctrine->getManager();
        $entityRol     = $doctrine->getRepository("ElMijoSeguridadBundle:Rol");
        $entityUsuario = $doctrine->getRepository("ElMijoSeguridadBundle:Usuario");
        $rolSuperAdmin = $entityRol->findOneBy(array("role" => $this->initRoles["Super Administrador"]));

        if(!!$entityUsuario->existeUsername($username))
        {
            return "<fg=magenta>El usuario $username ya esta registrado</fg=magenta>";
        }
        else if(!!$entityUsuario->existeEmail($email))
        {
            return "<fg=magenta>El email $email ya esta registrado</fg=magenta>";
        }
        else
        {
            $usuario  = new Usuario();
            $encoder  = $this->getContainer()->get('security.encoder_factory')->getEncoder($usuario);
            $password = $encoder->encodePassword($password, $usuario->getSalt());
            $usuario->setUsername($username)
                    ->setPassword($password)
                    ->setEmail($email)
                    ->addRole($rolSuperAdmin)
            ;
            $em->persist($usuario);
            $em->flush();

            return "<info>Usuario $username creado Exitosamente!!</info>";
        }
    }

    private function generateCode()
    {
        $this->validCode = intval(hexdec(md5(time()))/100000000000000000000000000000000);
    }

    private function init()
    {
        $this->generateCode();
        $this->doctrine      = $this->getContainer()->get('doctrine');
        $this->em            = $this->doctrine->getManager();
        $this->entityRol     = $this->doctrine->getRepository("ElMijoSeguridadBundle:Rol");
        $this->entityUsuario = $this->doctrine->getRepository("ElMijoSeguridadBundle:Usuario");
        $this->mailer        = $this->getContainer()->get('security.sendmail');
    }
}