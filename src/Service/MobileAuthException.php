<?php

namespace App\Service;

/**
 * Refus d'authentification mobile : code machine, message affichable et statut HTTP.
 */
class MobileAuthException extends \RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
