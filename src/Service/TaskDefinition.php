<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Service;

/**
 * HR: Predstavlja jedan zadatak i njegovu listu pročitane iz verzioniranog HTML sadržaja.
 * EN: Represents one task and its list read from versioned HTML content.
 */
final readonly class TaskDefinition
{
    /**
     * HR: Prima stabilne identifikatore, nazive i pravilo promjene statusa.
     * EN: Receives stable identifiers, labels, and the status-change rule.
     */
    public function __construct(
        public string $uuid,
        public string $listUuid,
        public string $text,
        public string $scope,
        public string $listLabel,
    ) {
    }
}
