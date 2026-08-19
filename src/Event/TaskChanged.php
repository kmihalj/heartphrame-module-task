<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Event;

/**
 * HR: Neutralno opisuje promjenu zadatka unutar stabilne liste iz verzioniranog dokumenta.
 * EN: Neutrally describes a task change inside a stable list from a versioned document.
 */
final readonly class TaskChanged
{
    /** HR: Sprema identifikatore liste i retka te njihove sigurne nazive. EN: Stores list and row identifiers with their safe labels. */
    public function __construct(
        public string $action,
        public string $taskUuid,
        public string $listUuid,
        public string $documentId,
        public string $taskLabel,
        public string $listLabel,
        public int $actorUserId,
    ) {
    }
}
