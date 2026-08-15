<?php

/**
 * HR: Neutralni opis Task API scopeova koji API modul čita samo kada je Task uključen.
 *
 * EN: Neutral Task API scope descriptor read by the API module only when Task is enabled.
 */

declare(strict_types=1);

return [
    'module' => 'task',
    'extension' => \AaiEduHr\HeartPhrameModuleTask\Api\TaskApiExtension::class,
    'resources' => [
        'task' => [
            'label' => ['hr' => 'Zadaci', 'en' => 'Tasks'],
            'scopes' => [
                'task:read' => [
                    'label' => ['hr' => 'Pregled', 'en' => 'Read'],
                    'description' => [
                        'hr' => 'Pregled task-lista, aktualnih stanja i audita na dostupnim objavljenim stranicama.',
                        'en' => 'Read task lists, current states, and audit history on accessible published pages.',
                    ],
                ],
                'task:write' => [
                    'label' => ['hr' => 'Promjena statusa', 'en' => 'Change state'],
                    'description' => [
                        'hr' => 'Označavanje i odznačavanje zadataka kada to dopuštaju '
                            . 'prava stranice i pravilo task-liste.',
                        'en' => 'Check and uncheck tasks when page permissions and the task-list rule allow it.',
                    ],
                ],
            ],
        ],
    ],
];
