<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorService;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorWorkspaceIntegration;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
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

return [
    TaskDefinitionParser::class => static fn(): TaskDefinitionParser => new TaskDefinitionParser(),

    TaskStateService::class => static fn(ContainerInterface $container): TaskStateService =>
        new TaskStateService($container->get(Database::class)),

    TaskDocumentAccess::class => static fn(ContainerInterface $container): TaskDocumentAccess =>
        new TaskDocumentAccess(
            $container->get(EditorService::class),
            $container->get(AuthnHandlerInterface::class),
            $container->get(EditorWorkspaceIntegration::class),
            $container->get(TaskDefinitionParser::class),
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
