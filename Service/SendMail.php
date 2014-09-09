<?php
namespace ElMijo\SeguridadBundle\Service;
/**
* Clase que conriene el servicio para enviar correos electronicos
*/
class SendMail
{
    protected $mailer;
    protected $realTransport;

    public function __construct(\Swift_Mailer $mailer,\Swift_Transport_EsmtpTransport $realTransport)
    {
        $this->mailer        = $mailer;
        $this->realTransport = $realTransport;
    }
    public function send($from, $to, $body, $subject = '')
    {
        try
        {
            $message = \Swift_Message::newInstance()
                ->setSubject($subject)
                ->setFrom($from)
                ->setTo($to)
                ->setBody($body)
            ;

            $this->mailer->send($message);
            $this->mailer->getTransport()->getSpool()->flushQueue($this->realTransport);
        }
        catch(\Swift_TransportException $e)
        {
            throw $e;              
        }
        catch(\Exception $e)
        {
            throw $e;
        }

        return TRUE;
    }    
}
?>