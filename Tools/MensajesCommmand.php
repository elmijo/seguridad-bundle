<?php
namespace ElMijo\SeguridadBundle\Tools;

class MensajesCommmand
{
	const SECURITY_CREATE_USER  = <<<EOT

El comando <info>security:security:create:user</info> permite crear un usuario valido para la aplicación:

  <info>php app/console security:create:user</info>

Tambien podria pasar el email como parametro:

  <info>php app/console security:create:user micorreo@example.com </info>

EOT;

    const NO_EXISTE_SUPER_ADMIN = <<<EOT

    <comment>Primero debes crear el Super Administrador</comment>

EOT;

    const EXISTE_SUPER_ADMIN    = <<<EOT

    <comment>El Super Administrador ya esta creado</comment>

EOT;

    const CREANDO_SUPER_ADMIN   = <<<EOT

	<comment>Creando usuario Administrador...</comment>

EOT;

    const CREANDO_USUARIO       = <<<EOT
        
    <comment>Creando usuario...</comment>

EOT;

	const ENVIANDO_CODE         = <<<EOT

	<comment>Enviando codifo de validación a su correo Electronico..</comment>

EOT;

	const INGRESE_CODE          = <<<EOT

	<fg=cyan>Por favor ingrese el codigo:</fg=cyan>

EOT;

	const EXCEDE_INTENTOS       = <<<EOT

	<error>Excedio el numero de intentos</error>

EOT;

	const INGRESE_USERNAME      = <<<EOT

	<fg=cyan>Por favor ingrese el username:</fg=cyan>

EOT;

	const INGRESE_PASSWORD      = <<<EOT

	<fg=cyan>Por favor ingrese el password:</fg=cyan>

EOT;

	const INGRESE_EMAIL         = <<<EOT

	<fg=cyan>Por favor ingrese el email:</fg=cyan>

EOT;

	const INGRESE_ROLES         = <<<EOT

	<fg=cyan>Por favor ingrese los roles:</fg=cyan>

EOT;


}