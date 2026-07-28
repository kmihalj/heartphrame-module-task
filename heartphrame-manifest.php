<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleAuth\Middleware\RequireAuthenticatedUserMiddleware;
use AaiEduHr\HeartPhrameModuleAuth\ModuleAuth;
use AaiEduHr\HeartPhrameModuleEditorHtml\ModuleEditorHtml;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleTask\Command\HpTaskCommand;
use AaiEduHr\HeartPhrameModuleTask\Controller\TaskController;
use AaiEduHr\HeartPhrameModuleTask\ModuleTask;
use HeartPhrame\Bridge\ComposerBridge;
use HeartPhrame\Command\CommandDefinition;
use HeartPhrame\Config\ConfigInterface;
use Psr\Container\ContainerInterface;

return new class extends \HeartPhrame\Module\AbstractModuleManifest {
    private const AUTH_PACKAGE = 'aaieduhr/heartphrame-module-auth';

    private const ORM_PACKAGE = 'aaieduhr/heartphrame-module-orm';

    private const EDITOR_PACKAGE = 'aaieduhr/heartphrame-module-editor-html';

    /**
     * HR: Zaustavlja učitavanje ako obavezni Auth, ORM i Editor moduli nisu
     *     instalirani i poredani prije Task modula.
     * EN: Stops loading unless the required Auth, ORM, and Editor modules are
     *     installed and ordered before the Task module.
     */
    public function canLoad(ContainerInterface $container): bool
    {
        $composer = $container->get(ComposerBridge::class);
        if (!($composer instanceof ComposerBridge)) {
            throw new RuntimeException('Task module requires ComposerBridge.');
        }

        $requiredClasses = [
            self::AUTH_PACKAGE => ModuleAuth::class,
            self::ORM_PACKAGE => Database::class,
            self::EDITOR_PACKAGE => ModuleEditorHtml::class,
        ];
        foreach ($requiredClasses as $package => $className) {
            if (!$composer->isInstalled($package) || !class_exists($className)) {
                throw new RuntimeException('Task module requires installed package "' . $package . '".');
            }
        }

        $config = $container->get(ConfigInterface::class);
        if (!($config instanceof ConfigInterface)) {
            throw new RuntimeException('Task module requires ConfigInterface.');
        }

        $enabled = $config->getAsArrayWithValuesAsNonEmptyStrings('app.modules.enabled') ?? [];
        $taskPosition = array_search(ModuleTask::PACKAGE_NAME, $enabled, true);
        foreach (array_keys($requiredClasses) as $package) {
            $position = array_search($package, $enabled, true);
            if ($position === false || ($taskPosition !== false && $position > $taskPosition)) {
                throw new RuntimeException(
                    'Task module requires enabled module "' . $package . '" before "'
                    . ModuleTask::PACKAGE_NAME . '".',
                );
            }
        }

        return true;
    }

    /**
     * HR: Odgađa registraciju dok obavezni moduli ne izlože svoje servise.
     * EN: Defers registration until required modules expose their services.
     */
    public function requiresDeferredLoading(): bool
    {
        return true;
    }

    /**
     * HR: Učitava servisne definicije modula.
     * EN: Loads module service definitions.
     */
    public function getServices(): array
    {
        $services = require __DIR__ . '/config/services.php';
        if (!is_array($services)) {
            throw new RuntimeException('Task config/services.php must return an array.');
        }

        return $services;
    }

    /**
     * HR: Registrira javne assete i autentificirane JSON operacije.
     * EN: Registers public assets and authenticated JSON operations.
     */
    public function getBaseRoutes(): array
    {
        $authenticated = [RequireAuthenticatedUserMiddleware::class];

        return [
            ['GET', '/tasks/assets.css', TaskController::class . '@css', 'task.assets.css'],
            ['GET', '/tasks/assets.js', TaskController::class . '@js', 'task.assets.js'],
            ['GET', '/tasks/csrf-token', TaskController::class . '@csrf', 'task.csrf', $authenticated],
            ['POST', '/tasks/state', TaskController::class . '@state', 'task.state', $authenticated],
            [
                'GET',
                '/tasks/history/{taskUuid}',
                TaskController::class . '@history',
                'task.history',
                $authenticated,
            ],
        ];
    }

    /**
     * HR: Registrira instalacijsku CLI naredbu za jedinu početnu migraciju.
     * EN: Registers the installation CLI command for the single initial migration.
     */
    public function getCommands(): array
    {
        return [
            new CommandDefinition('task', 'Task module helper command.', [HpTaskCommand::class, 'run']),
            new CommandDefinition(
                'task:install-migration',
                'Copy initial Task migration into the host application.',
                [HpTaskCommand::class, 'installMigration'],
            ),
        ];
    }
};
