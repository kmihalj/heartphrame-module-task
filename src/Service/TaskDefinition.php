<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Service;

/**
 * HR: Predstavlja jedan zadatak pročitan iz verzioniranog HTML sadržaja.
 * EN: Represents one task read from versioned HTML content.
 */
final readonly class TaskDefinition
{
    /**
     * HR: Prima stabilne identifikatore, tekst i pravilo promjene statusa.
     * EN: Receives stable identifiers, text, and the status-change rule.
     */
    public function __construct(
        public string $uuid,
        public string $listUuid,
        public string $text,
        public string $scope,
    ) {
    }
}
