<?php

declare(strict_types=1);

return ['providers' => [
    'heartphrame.backup.provider.task',
    [
        'service' => 'heartphrame.backup.provider.task-workspace',
        'requires' => ['aaieduhr/heartphrame-module-workspace'],
    ],
]];
