<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Api;

use AaiEduHr\HeartPhrameModuleApi\Contract\ApiExtensionInterface;
use AaiEduHr\HeartPhrameModuleApi\Contract\ApiRouteRegistry;

/**
 * HR: Oglašava Task API rute generičkoj API jezgri.
 * EN: Advertises Task API routes to the generic API core.
 * @see \AaiEduHr\HeartPhrameModuleTask\Tests\Api\TaskApiExtensionTest
 */
final readonly class TaskApiExtension implements ApiExtensionInterface
{
    /**
     * HR: Vraća stabilni identifikator Task proširenja.
     * EN: Returns the stable Task extension identifier.
     */
    public function id(): string
    {
        return 'task';
    }

    /**
     * HR: Dodaje Task rute kroz zajednički sigurni registar.
     * EN: Adds Task routes through the shared secure registry.
     */
    public function register(ApiRouteRegistry $routes): void
    {
        foreach ($this->routeDefinitions() as [$method, $path, $action, $name]) {
            $routes->add(
                $method,
                $path,
                TaskResourceController::class,
                $action,
                $name,
            );
        }
    }

    /**
     * HR: Vraća stabilni popis Task ruta pod stranicom koja sadrži definiciju.
     * EN: Returns the stable Task routes beneath the page containing the definition.
     *
     * @return list<array{string,string,string,string}>
     */
    private function routeDefinitions(): array
    {
        return [
            ['GET', '/api/v1/pages/{documentId}/tasks', 'listTasks', 'api.v1.pages.tasks'],
            [
                'GET',
                '/api/v1/pages/{documentId}/tasks/{taskUuid}',
                'getTask',
                'api.v1.pages.tasks.get',
            ],
            [
                'PUT',
                '/api/v1/pages/{documentId}/tasks/{taskUuid}/state',
                'setState',
                'api.v1.pages.tasks.state',
            ],
            [
                'GET',
                '/api/v1/pages/{documentId}/tasks/{taskUuid}/history',
                'history',
                'api.v1.pages.tasks.history',
            ],
        ];
    }
}
