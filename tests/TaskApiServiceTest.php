<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Tests;

use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorApiActorContext;
use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleTask\Api\TaskApiException;
use AaiEduHr\HeartPhrameModuleTask\Api\TaskApiService;
use AaiEduHr\HeartPhrameModuleTask\Event\TaskChanged;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskApiDocumentAccessInterface;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskDefinition;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskDefinitionParser;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskStateService;
use HeartPhrame\Config\Config;
use HeartPhrame\Helper\Helper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(TaskApiService::class)]
#[CoversClass(TaskApiException::class)]
#[CoversClass(TaskDefinition::class)]
#[CoversClass(TaskDefinitionParser::class)]
#[CoversClass(TaskStateService::class)]
#[UsesClass(TaskChanged::class)]
final class TaskApiServiceTest extends TestCase
{
    private Database $database;

    /**
     * HR: Priprema praznu prenosivu SQLite bazu za stvarno stanje i audit zadataka.
     * EN: Prepares an empty portable SQLite database for real task state and audit records.
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
    }

    /**
     * HR: Provjerava listu, pojedinačni task, promjenu stanja i audit kroz jedan API ugovor.
     * EN: Verifies listing, one task, a state change, and audit through one API contract.
     */
    public function testReadsChangesAndAuditsPublishedTasks(): void
    {
        $first = new TaskDefinition(
            'e3d1be37-04fd-4f69-ad6e-39fa9f085223',
            '5adf2862-a532-4d66-b916-b977284fc159',
            'Provjeri dokument',
            'editors',
            'Objava dokumenta',
        );
        $second = new TaskDefinition(
            '139c506d-d3e3-463e-af17-b19447d467dc',
            '5adf2862-a532-4d66-b916-b977284fc159',
            'Objavi dokument',
            'viewers',
            'Objava dokumenta',
        );
        $access = $this->createMock(TaskApiDocumentAccessInterface::class);
        $access->expects($this->once())
            ->method('publishedHtml')
            ->with('document-a', 'hr')
            ->willReturn($this->taskHtml($first, $second));
        $access->expects($this->exactly(2))
            ->method('taskForRead')
            ->with('document-a', 'hr', $first->uuid)
            ->willReturn($first);
        $access->expects($this->once())
            ->method('taskForChange')
            ->with('document-a', 'hr', $first->uuid)
            ->willReturn($first);
        $access->expects($this->once())
            ->method('auditIdentity')
            ->willReturn(['id' => 7, 'display_name' => 'Ana Horvat']);

        $service = new TaskApiService(
            new EditorApiActorContext(),
            $access,
            new TaskDefinitionParser(),
            new TaskStateService($this->database),
        );
        $actor = ['id' => 7, 'display_name' => 'Ana Horvat', 'is_admin' => false];

        $tasks = $service->listTasks('document-a', 'hr', $actor);
        $this->assertCount(2, $tasks);
        $this->assertFalse($tasks[0]['state']['completed']);
        $this->assertSame('viewers', $tasks[1]['toggle_scope']);
        $this->assertSame($first->uuid, $service->getTask(
            'document-a',
            'hr',
            $first->uuid,
            $actor,
        )['uuid']);

        $state = $service->setState(
            'document-a',
            'hr',
            $first->uuid,
            true,
            $actor,
        );
        $this->assertTrue($state['completed']);
        $this->assertSame(7, $state['updated_by_user_id']);
        $this->assertSame(1, $state['version']);

        $history = $service->history(
            'document-a',
            'hr',
            $first->uuid,
            20,
            $actor,
        );
        $this->assertCount(1, $history);
        $this->assertTrue($history[0]['completed']);
        $this->assertSame('Ana Horvat', $history[0]['changed_by_display_name']);
    }

    /**
     * HR: Potvrđuje da domenska zabrana ne otkriva detalje kroz neočekivani HTTP status.
     * EN: Confirms that a domain denial does not leak details through an unexpected HTTP status.
     */
    public function testMapsDocumentAccessFailureToApiError(): void
    {
        $access = $this->createMock(TaskApiDocumentAccessInterface::class);
        $access->expects($this->once())
            ->method('taskForRead')
            ->willThrowException(new RuntimeException('Nemate pravo pregleda ovog dokumenta.'));
        $service = new TaskApiService(
            new EditorApiActorContext(),
            $access,
            new TaskDefinitionParser(),
            new TaskStateService($this->database),
        );

        try {
            $service->getTask(
                'document-a',
                'hr',
                'e3d1be37-04fd-4f69-ad6e-39fa9f085223',
                ['id' => 9],
            );
            self::fail('A denied document must not expose its task.');
        } catch (TaskApiException $taskApiException) {
            $this->assertSame(403, $taskApiException->status);
            $this->assertSame('task_permission_denied', $taskApiException->errorCode);
        }
    }

    /**
     * HR: Gradi reprezentativan deklarativni task-list HTML koji proizvodi Editor.
     * EN: Builds representative declarative task-list HTML produced by the Editor.
     */
    private function taskHtml(TaskDefinition $first, TaskDefinition $second): string
    {
        return sprintf(
            '<section data-editor-html-task-list="1" data-task-list-uuid="%s"'
            . ' data-task-toggle-scope="editors"><ul><li data-task-uuid="%s">%s</li>'
            . '</ul></section>'
            . '<section data-editor-html-task-list="1" data-task-list-uuid="%s"'
            . ' data-task-toggle-scope="viewers"><ul><li data-task-uuid="%s">%s</li>'
            . '</ul></section>',
            $first->listUuid,
            $first->uuid,
            $first->text,
            $second->listUuid,
            $second->uuid,
            $second->text,
        );
    }
}
