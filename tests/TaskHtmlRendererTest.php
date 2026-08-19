<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Tests;

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleTask\ModuleTask;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskDefinition;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskDefinitionParser;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskDocumentAccess;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskHtmlRenderer;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskStateService;
use HeartPhrame\Config\Config;
use HeartPhrame\Helper\Helper;
use HeartPhrame\Routing\UrlGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function substr_count;

#[CoversClass(TaskHtmlRenderer::class)]
#[UsesClass(TaskDefinition::class)]
#[UsesClass(TaskDefinitionParser::class)]
#[UsesClass(TaskStateService::class)]
final class TaskHtmlRendererTest extends TestCase
{
    private Database $database;

    /** HR: Priprema stvarne prenosive Task tablice u memoriji. EN: Prepares real portable Task tables in memory. */
    protected function setUp(): void
    {
        $helper = new Helper();
        $config = new Config($helper, [
            'database' => [
                'connections' => [
                    'default' => ['driver' => 'sqlite', 'database' => ':memory:'],
                ],
            ],
        ]);
        $this->database = new Database($config, $helper);
        $migration = require dirname(__DIR__) . '/resources/migrations/initial_task_schema.php';
        self::assertInstanceOf(ReversibleMigrationInterface::class, $migration);
        $migration->up($this->database);
    }

    /**
     * HR: Svaka lista dobiva samo jedan audit red s najnovijom promjenom, neovisno o broju zadataka.
     * EN: Every list gets only one audit row with its newest change, regardless of task count.
     */
    public function testRendersOneLatestChangePerTaskList(): void
    {
        $listA = '5adf2862-a532-4d66-b916-b977284fc159';
        $listB = 'e4577cb4-dbb4-4fa0-abba-d43ad98898a9';
        $taskA = 'e3d1be37-04fd-4f69-ad6e-39fa9f085223';
        $taskB = 'a03d850b-2d60-491c-a659-ed4a3fbc646c';
        $taskC = '875b30c0-4767-4e51-b5d9-5a7c76de7c70';
        $this->state($taskA, $listA, '2026-08-17 08:00:00', 'Stara promjena');
        $this->state($taskB, $listA, '2026-08-18 09:30:00', 'Nova promjena');
        $this->state($taskC, $listB, '2026-08-16 07:00:00', 'Druga lista');

        $html = <<<HTML
<section data-editor-html-task-list="1" data-task-list-uuid="{$listA}" data-task-toggle-scope="viewers">
  <h3>Prva lista</h3><ul>
    <li data-task-uuid="{$taskA}">Prvi redak</li>
    <li data-task-uuid="{$taskB}">Drugi redak</li>
  </ul>
</section>
<section data-editor-html-task-list="1" data-task-list-uuid="{$listB}" data-task-toggle-scope="viewers">
  <h3>Druga lista</h3><ul><li data-task-uuid="{$taskC}">Treći redak</li></ul>
</section>
HTML;
        $renderer = new TaskHtmlRenderer(
            new TaskDefinitionParser(),
            new TaskStateService($this->database),
            (new ReflectionClass(TaskDocumentAccess::class))->newInstanceWithoutConstructor(),
            new class extends UrlGenerator {
                public function __construct()
                {
                }

                public function namedRouteExists(string $name): bool
                {
                    return false;
                }

                public function getBasePath(): string
                {
                    return '/test';
                }
            },
        );

        $rendered = $renderer->render($html, 'document-a', 'hr', false);

        self::assertSame(2, substr_count($rendered, 'class="editor-task-list-audit"'));
        self::assertStringNotContainsString('class="editor-task-audit"', $rendered);
        self::assertStringContainsString('Zadnja promjena: Nova promjena · 18.08.2026. 09:30:00', $rendered);
        self::assertStringNotContainsString('Zadnja promjena: Stara promjena', $rendered);
        self::assertStringContainsString('Zadnja promjena: Druga lista · 16.08.2026. 07:00:00', $rendered);
        self::assertStringContainsString('data-task-list-label="Prva lista"', $rendered);
    }

    /** HR: Sprema kontrolirano stanje jednog retka. EN: Stores one row's controlled state. */
    private function state(string $taskUuid, string $listUuid, string $updatedAt, string $actor): void
    {
        $this->database->table(ModuleTask::TABLE_STATES)->insert([
            'task_uuid' => $taskUuid,
            'task_list_uuid' => $listUuid,
            'document_id' => 'document-a',
            'is_completed' => false,
            'updated_by_user_id' => 7,
            'updated_by_display_name' => $actor,
            'state_version' => 1,
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);
    }
}
