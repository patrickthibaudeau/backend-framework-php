<?php
namespace DevFramework\Core\Mail;

use DevFramework\Core\Mail\MailMessage; // explicit for static analyzers

interface MailDriverInterface
{
    /**
     * Send a prepared MailMessage
     * @param MailMessage $message
     * @return array{success:bool, error?:string}
     */
    public function send(MailMessage $message): array;
}
