<?php

namespace App\MessageHandler;

use App\Message\MailNotification;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler()]
class MailNotificationHandler
{
    public function __construct(
        private MailerInterface $mailer,
    ) {
    }

    public function __invoke(MailNotification $message)
    {
        $email = (new Email())
            ->from($message->getFrom())
            ->to('you@example.com')
            ->subject('New Incident #'.$message->getId() . ' from ' . $message->getFrom())
            ->html('<h1>New Incident</h1><p>'.$message->getDescription().'</p>');

        sleep(10);

        $this->mailer->send($email);
    }
}
