<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Tests\Api;

use AaiEduHr\HeartPhrameModuleApi\Contract\ApiRouteRegistry;
use AaiEduHr\HeartPhrameModuleApi\Middleware\ApiAuthenticationMiddleware;
use AaiEduHr\HeartPhrameModuleTask\Api\TaskApiExtension;
use HeartPhrame\Routing\Routes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** HR: Provjerava Task API oglas. EN: Verifies the Task API declaration. */
#[CoversClass(TaskApiExtension::class)]
#[CoversClass(ApiRouteRegistry::class)]
final class TaskApiExtensionTest extends TestCase
{
    /** HR: Registrira Task rute sa zaštitom. EN: Registers protected Task routes. */
    public function testRegistersOwnedRoutes(): void
    {
        $routes = new Routes();
        (new TaskApiExtension())->register(new ApiRouteRegistry($routes));
        $namedRoutes = $routes->getNamedRoutes();
        $registeredRoutes = $routes->getRoutes();

        $this->assertCount(4, $namedRoutes);
        $this->assertSame(
            '/api/v1/pages/{documentId}/tasks/{taskUuid}/state',
            $namedRoutes['api.v1.pages.tasks.state']['path'] ?? null,
        );
        $this->assertContains(
            ApiAuthenticationMiddleware::class,
            $registeredRoutes['GET']['/api/v1/pages/{documentId}/tasks']['middleware'],
        );
    }
}
