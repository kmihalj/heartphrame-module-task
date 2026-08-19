<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Tests;

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleTask\Event\TaskChanged;
use AaiEduHr\HeartPhrameModuleTask\ModuleTask;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskDefinition;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskStateService;
use HeartPhrame\Config\Config;
use HeartPhrame\Helper\Helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(TaskStateService::class)]
#[CoversClass(TaskDefinition::class)]
#[UsesClass(TaskChanged::class)]
final class TaskStateServiceTest extends TestCase
{
    private Database $database;

    private TaskStateService $states;

    /**
     * HR: Priprema praznu SQLite bazu preko iste početne ORM migracije kao host.
     * EN: Prepares an empty SQLite database through the same initial ORM migration as the host.
     */
    protected function setUp(): void
    {
        $helper = new Helper();
        $config = new Config($helper, [
            'database' => [
                'connections' => [
                    'default' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                    ],
                ],
            ],
        ]);
        $this->database = new Database($config, $helper);
        $migration = require dirname(__DIR__) . '/resources/migrations/initial_task_schema.php';
        $this->assertInstanceOf(ReversibleMigrationInterface::class, $migration);
        $migration->up($this->database);
        $this->states = new TaskStateService($this->database);
    }

    /**
     * HR: Provjerava da početna migracija stvara samo prazne Task tablice.
     * EN: Verifies that the initial migration creates only empty Task tables.
     */
    public function testInitialSchemaIsCompleteAndEmpty(): void
    {
        $this->assertTrue($this->database->schema()->hasColumns(ModuleTask::TABLE_STATES, [
            'task_uuid',
            'task_list_uuid',
            'document_id',
            'is_completed',
            'updated_by_user_id',
            'updated_by_display_name',
            'state_version',
        ]));
        $this->assertTrue($this->database->schema()->hasColumns(ModuleTask::TABLE_EVENTS, [
            'uuid',
            'task_uuid',
            'document_id',
            'is_completed',
            'changed_by_user_id',
            'changed_by_display_name',
        ]));
        $this->assertSame([], $this->database->table(ModuleTask::TABLE_STATES)->get());
        $this->assertSame([], $this->database->table(ModuleTask::TABLE_EVENTS)->get());
    }

    /**
     * HR: Isti task UUID u dva dokumenta mora imati neovisno stanje i audit.
     * EN: The same task UUID in two documents must have independent state and audit.
     */
    public function testStateIsScopedByDocumentAndIdempotent(): void
    {
        $task = new TaskDefinition(
            'e3d1be37-04fd-4f69-ad6e-39fa9f085223',
            '5adf2862-a532-4d66-b916-b977284fc159',
            'Provjeri dokument',
            'editors',
            'Objava dokumenta',
        );

        $first = $this->states->setState($task, 'document-a', true, 7, 'Ana Horvat');
        $same = $this->states->setState($task, 'document-a', true, 7, 'Ana Horvat');
        $secondDocument = $this->states->setState($task, 'document-b', false, 8, 'Ivo Ivić');

        $this->assertTrue((bool)$first['completed']);
        $this->assertSame($first['version'], $same['version']);
        $this->assertFalse((bool)$secondDocument['completed']);
        $this->assertCount(1, $this->states->history('document-a', $task->uuid));
        $this->assertCount(1, $this->states->history('document-b', $task->uuid));
        $this->assertTrue((bool)$this->states->statesFor('document-a', [$task->uuid])[$task->uuid]['is_completed']);
        $this->assertFalse((bool)$this->states->statesFor('document-b', [$task->uuid])[$task->uuid]['is_completed']);
    }

    /**
     * HR: Događaj nosi identitet cijele liste i promijenjenog retka za precizno praćenje.
     * EN: The event carries both the whole-list and changed-row identities for precise following.
     */
    public function testChangedEventContainsTaskListIdentity(): void
    {
        $events = new class implements EventDispatcherInterface {
            /** @var list<object> */
            public array $dispatched = [];

            public function dispatch(object $event): object
            {
                $this->dispatched[] = $event;

                return $event;
            }
        };
        $task = new TaskDefinition(
            'e3d1be37-04fd-4f69-ad6e-39fa9f085223',
            '5adf2862-a532-4d66-b916-b977284fc159',
            'Provjeri dokument',
            'editors',
            'Objava dokumenta',
        );

        (new TaskStateService($this->database, $events))
            ->setState($task, 'document-a', true, 7, 'Ana Horvat');

        $this->assertCount(1, $events->dispatched);
        $this->assertInstanceOf(TaskChanged::class, $events->dispatched[0]);
        $this->assertSame($task->listUuid, $events->dispatched[0]->listUuid);
        $this->assertSame($task->listLabel, $events->dispatched[0]->listLabel);
        $this->assertSame($task->uuid, $events->dispatched[0]->taskUuid);
    }
}
