<?php

namespace App\Message;

class MailNotification
{
    public function __construct(
        public string $from,
        public string $id,
        public string $description
    ) {
    }

    public function getFrom(): string
    {
        return $this->from;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
