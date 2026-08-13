<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorApiActorContext;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorService;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorWorkspaceIntegration;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleTask\Api\TaskApiService;
use AaiEduHr\HeartPhrameModuleTask\Command\HpTaskCommand;
use AaiEduHr\HeartPhrameModuleTask\Controller\TaskController;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskDefinitionParser;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskDocumentAccess;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskHtmlRenderer;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskStateService;
use HeartPhrame\Authn\AuthnHandlerInterface;
use HeartPhrame\Config\ConfigInterface;
use HeartPhrame\Http\ResponseFactory;
use HeartPhrame\Routing\UrlGenerator;
use HeartPhrame\Session\SessionInterface;
use Psr\Container\ContainerInterface;

$services = [
    TaskApiService::class => static fn(ContainerInterface $container): TaskApiService =>
        new TaskApiService(
            $container->get(EditorApiActorContext::class),
            $container->get(TaskDocumentAccess::class),
            $container->get(TaskDefinitionParser::class),
            $container->get(TaskStateService::class),
        ),

    TaskDefinitionParser::class => static fn(): TaskDefinitionParser => new TaskDefinitionParser(),

    TaskStateService::class => static fn(ContainerInterface $container): TaskStateService =>
        new TaskStateService($container->get(Database::class)),

    TaskDocumentAccess::class => static fn(ContainerInterface $container): TaskDocumentAccess =>
        new TaskDocumentAccess(
            $container->get(EditorService::class),
            $container->get(AuthnHandlerInterface::class),
            $container->get(EditorWorkspaceIntegration::class),
            $container->get(TaskDefinitionParser::class),
            $container->get(EditorApiActorContext::class),
        ),

    TaskHtmlRenderer::class => static fn(ContainerInterface $container): TaskHtmlRenderer =>
        new TaskHtmlRenderer(
            $container->get(TaskDefinitionParser::class),
            $container->get(TaskStateService::class),
            $container->get(TaskDocumentAccess::class),
            $container->get(UrlGenerator::class),
        ),

    TaskController::class => static fn(ContainerInterface $container): TaskController =>
        new TaskController(
            $container->get(ResponseFactory::class),
            $container->get(SessionInterface::class),
            $container->get(TaskDocumentAccess::class),
            $container->get(TaskStateService::class),
        ),

    HpTaskCommand::class => static fn(ContainerInterface $container): HpTaskCommand =>
        new HpTaskCommand($container->get(ConfigInterface::class)),
];

if (class_exists(\AaiEduHr\HeartPhrameModuleBackup\Service\DatabaseTableBackupProvider::class)) {
    $services['heartphrame.backup.provider.task'] =
        static fn(ContainerInterface $container): \AaiEduHr\HeartPhrameModuleBackup\Service\DatabaseTableBackupProvider =>
            new \AaiEduHr\HeartPhrameModuleBackup\Service\DatabaseTableBackupProvider(
                $container->get(Database::class),
                new \AaiEduHr\HeartPhrameModuleBackup\Value\BackupProviderMetadata(
                    'task',
                    \AaiEduHr\HeartPhrameModuleTask\ModuleTask::PACKAGE_NAME,
                    2,
                    ['hr' => 'Zadaci', 'en' => 'Tasks'],
                    ['auth', 'editor-html'],
                    [\AaiEduHr\HeartPhrameModuleBackup\Value\BackupScope::SITE, \AaiEduHr\HeartPhrameModuleBackup\Value\BackupScope::COMPONENT],
                    true,
                    true,
                ),
                [
                    ['dataset' => 'states', 'table' => \AaiEduHr\HeartPhrameModuleTask\ModuleTask::TABLE_STATES, 'primary_key' => 'id', 'conflict_keys' => ['document_id', 'task_uuid'], 'preserve_primary_key' => false, 'foreign_keys' => [
                        ['column' => 'updated_by_user_id', 'namespace' => 'auth.user', 'nullable' => true],
                    ]],
                    ['dataset' => 'events', 'table' => \AaiEduHr\HeartPhrameModuleTask\ModuleTask::TABLE_EVENTS, 'primary_key' => 'id', 'conflict_keys' => ['uuid'], 'preserve_primary_key' => false, 'foreign_keys' => [
                        ['column' => 'changed_by_user_id', 'namespace' => 'auth.user', 'nullable' => true],
                    ]],
                ],
            );
}

if (
    class_exists(\AaiEduHr\HeartPhrameModuleBackup\Service\DocumentScopedDatabaseBackupProvider::class)
    && class_exists(\AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace::class)
) {
    $services['heartphrame.backup.provider.task-workspace'] =
        static function (ContainerInterface $container): \AaiEduHr\HeartPhrameModuleBackup\Service\DocumentScopedDatabaseBackupProvider {
            $database = $container->get(Database::class);
            $identities = $container->get(\AaiEduHr\HeartPhrameModuleAuth\Backup\AuthBackupIdentityResolver::class);
            $documentKeys = static function (string $identifier) use ($database): array {
                $workspace = $database->table(\AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace::TABLE_WORKSPACES)
                    ->where('slug', '=', $identifier)
                    ->first()
                    ?? $database->table(\AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace::TABLE_WORKSPACES)
                        ->where('uuid', '=', $identifier)
                        ->first();
                if (!is_array($workspace) || !is_numeric($workspace['id'] ?? null)) {
                    throw new \AaiEduHr\HeartPhrameModuleBackup\Exception\BackupException(
                        'Workspace does not exist: ' . $identifier,
                    );
                }
                $rows = $database->table(\AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace::TABLE_WORKSPACE_NODES)
                    ->select(['document_key'])
                    ->where('workspace_id', '=', (int)$workspace['id'])
                    ->get();
                return array_values(array_unique(array_filter(array_map(
                    static fn(array $row): string => trim((string)($row['document_key'] ?? '')),
                    $rows,
                ))));
            };

            return new \AaiEduHr\HeartPhrameModuleBackup\Service\DocumentScopedDatabaseBackupProvider(
                $database,
                new \AaiEduHr\HeartPhrameModuleBackup\Value\BackupProviderMetadata(
                    'task-workspace',
                    \AaiEduHr\HeartPhrameModuleTask\ModuleTask::PACKAGE_NAME,
                    1,
                    ['hr' => 'Zadaci područja', 'en' => 'Workspace tasks'],
                    ['editor-html-workspace'],
                    [\AaiEduHr\HeartPhrameModuleBackup\Value\BackupScope::WORKSPACE],
                    true,
                    true,
                    [\AaiEduHr\HeartPhrameModuleWorkspace\ModuleWorkspace::PACKAGE_NAME],
                ),
                [
                    [
                        'dataset' => 'states',
                        'table' => \AaiEduHr\HeartPhrameModuleTask\ModuleTask::TABLE_STATES,
                        'primary_key' => 'id',
                        'conflict_keys' => ['document_id', 'task_uuid'],
                        'preserve_primary_key' => false,
                        'scope_document_column' => 'document_id',
                        'portable_document_columns' => ['document_id'],
                        'portable_user_columns' => [[
                            'column' => 'updated_by_user_id',
                            'nullable' => true,
                        ]],
                    ],
                    [
                        'dataset' => 'events',
                        'table' => \AaiEduHr\HeartPhrameModuleTask\ModuleTask::TABLE_EVENTS,
                        'primary_key' => 'id',
                        'conflict_keys' => ['uuid'],
                        'preserve_primary_key' => false,
                        'scope_document_column' => 'document_id',
                        'portable_document_columns' => ['document_id'],
                        'portable_user_columns' => [[
                            'column' => 'changed_by_user_id',
                            'nullable' => true,
                        ]],
                        'copy_uuid_columns' => ['uuid'],
                    ],
                ],
                $documentKeys,
                static fn(mixed $id): ?string => $identities->userKeyForId($id),
                static fn(?string $key): ?int => $identities->userIdForKey($key),
            );
        };
}

return $services;
