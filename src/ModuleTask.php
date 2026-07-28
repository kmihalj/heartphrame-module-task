<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask;

/**
 * HR: Sadrži stabilne identifikatore Task modula koje dijele migracija,
 *     poslovni servisi i integracija s HTML Editorom.
 * EN: Contains stable Task module identifiers shared by the migration,
 *     business services, and HTML Editor integration.
 */
final class ModuleTask
{
    public const PACKAGE_NAME = 'aaieduhr/heartphrame-module-task';

    public const TABLE_STATES = 'task_item_states';

    public const TABLE_EVENTS = 'task_item_events';

    /**
     * HR: Sprječava instanciranje registra konstanti.
     * EN: Prevents instantiation of the constants registry.
     */
    private function __construct()
    {
    }
}
