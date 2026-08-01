<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Api;

use RuntimeException;

/**
 * HR: Prenosi stabilni Task API kod pogreške i HTTP status.
 * EN: Carries a stable Task API error code and HTTP status.
 */
final class TaskApiException extends RuntimeException
{
    /**
     * HR: Prima strojni kod, čitljivu poruku i HTTP status.
     * EN: Receives a machine code, readable message, and HTTP status.
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }
}
