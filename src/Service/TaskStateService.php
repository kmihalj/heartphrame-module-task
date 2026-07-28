<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Service;

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleTask\ModuleTask;
use RuntimeException;

use function array_values;
use function bin2hex;
use function chr;
use function date;
use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function max;
use function min;
use function ord;
use function random_bytes;
use function sprintf;
use function substr;
use function trim;

/**
 * HR: Sprema aktualno stanje zadataka i svaki prijelaz zapisuje u trajni audit.
 *     Drugi moduli zato ne trebaju poznavati strukturu Task tablica.
 * EN: Persists current task state and records every transition in a durable
 *     audit trail. Other modules therefore do not need to know Task table structure.
 */
final readonly class TaskStateService
{
    /**
     * HR: Prima prenosivi ORM database servis.
     * EN: Receives the portable ORM database service.
     */
    public function __construct(private Database $database)
    {
    }

    /**
     * HR: Provjerava jesu li obje početne tablice instalirane.
     * EN: Checks whether both initial tables are installed.
     */
    public function tablesReady(): bool
    {
        $schema = $this->database->schema();

        return $schema->hasTable(ModuleTask::TABLE_STATES)
        && $schema->hasTable(ModuleTask::TABLE_EVENTS);
    }

    /**
     * HR: Učitava stanja svih zadanih UUID-eva jednim upitom i indeksira ih po UUID-u.
     * EN: Loads states for all supplied UUIDs in one query and indexes them by UUID.
     *
     * @param list<string> $taskUuids
     * @return array<string, array<string, mixed>>
     */
    public function statesFor(string $documentId, array $taskUuids): array
    {
        $documentId = trim($documentId);
        if ($documentId === '' || $taskUuids === [] || !$this->tablesReady()) {
            return [];
        }

        $rows = $this->database->table(ModuleTask::TABLE_STATES)
            ->where('document_id', '=', $documentId)
            ->whereIn('task_uuid', array_values($taskUuids))
            ->get();
        $states = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $row = $this->stringKeyedRow($row);
            $uuid = $this->stringValue($row['task_uuid'] ?? '');
            if ($uuid !== '') {
                $states[$uuid] = $row;
            }
        }

        return $states;
    }

    /**
     * HR: Postavlja željeno stanje idempotentno, povećava verziju i dodaje
     *     audit događaj samo kada se vrijednost doista promijenila.
     * EN: Sets the desired state idempotently, increments its version, and adds
     *     an audit event only when the value actually changed.
     *
     * @return array<string, mixed>
     */
    public function setState(
        TaskDefinition $task,
        string $documentId,
        bool $completed,
        int $userId,
        string $displayName,
    ): array {
        $this->assertTablesReady();
        $now = date('Y-m-d H:i:s');

        $result = $this->database->transaction(function () use (
            $task,
            $documentId,
            $completed,
            $userId,
            $displayName,
            $now,
        ): array {
            $existing = $this->database->table(ModuleTask::TABLE_STATES)
                ->where('document_id', '=', trim($documentId))
                ->where('task_uuid', '=', $task->uuid)
                ->first();
            $existing = is_array($existing) ? $this->stringKeyedRow($existing) : null;

            $previous = $existing !== null && (bool)($existing['is_completed'] ?? false);
            $version = $existing !== null
            ? max(1, $this->intValue($existing['state_version'] ?? 1))
            : 0;

            if ($existing !== null && $previous === $completed) {
                return $this->normalize($existing);
            }

            $values = [
                'task_list_uuid' => $task->listUuid,
                'document_id' => trim($documentId),
                'is_completed' => $completed,
                'updated_by_user_id' => $userId > 0 ? $userId : null,
                'updated_by_display_name' => trim($displayName) !== '' ? trim($displayName) : null,
                'state_version' => $version + 1,
                'updated_at' => $now,
            ];
            if ($existing === null) {
                $this->database->table(ModuleTask::TABLE_STATES)->insert([
                    'task_uuid' => $task->uuid,
                    'created_at' => $now,
                    ...$values,
                ]);
            } else {
                $this->database->table(ModuleTask::TABLE_STATES)
                    ->where('document_id', '=', trim($documentId))
                    ->where('task_uuid', '=', $task->uuid)
                    ->update($values);
            }

            $this->database->table(ModuleTask::TABLE_EVENTS)->insert([
                'uuid' => $this->uuid(),
                'task_uuid' => $task->uuid,
                'task_list_uuid' => $task->listUuid,
                'document_id' => trim($documentId),
                'is_completed' => $completed,
                'changed_by_user_id' => $userId > 0 ? $userId : null,
                'changed_by_display_name' => trim($displayName) !== '' ? trim($displayName) : null,
                'created_at' => $now,
            ]);

            $row = $this->database->table(ModuleTask::TABLE_STATES)
                ->where('document_id', '=', trim($documentId))
                ->where('task_uuid', '=', $task->uuid)
                ->first();
            if (!is_array($row)) {
                throw new RuntimeException(__('Spremljeno stanje zadatka nije moguće učitati.'));
            }

            return $this->normalize($this->stringKeyedRow($row));
        });

        if (!is_array($result)) {
            throw new RuntimeException(__('Spremljeno stanje zadatka nije moguće učitati.'));
        }

        return $this->stringKeyedRow($result);
    }

    /**
     * HR: Vraća najnovije audit događaje jednog zadatka.
     * EN: Returns the newest audit events for one task.
     *
     * @return list<array<string, mixed>>
     */
    public function history(string $documentId, string $taskUuid, int $limit = 50): array
    {
        $this->assertTablesReady();
        $rows = $this->database->table(ModuleTask::TABLE_EVENTS)
            ->where('document_id', '=', trim($documentId))
            ->where('task_uuid', '=', trim($taskUuid))
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(max(1, min(100, $limit)))
            ->get();

        $events = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $events[] = $this->stringKeyedRow($row);
            }
        }

        return $events;
    }

    /**
     * HR: Normalizira redak stanja u stabilan javni payload.
     * EN: Normalizes a state row into a stable public payload.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        return [
            'task_uuid' => $this->stringValue($row['task_uuid'] ?? ''),
            'task_list_uuid' => $this->stringValue($row['task_list_uuid'] ?? ''),
            'document_id' => $this->stringValue($row['document_id'] ?? ''),
            'completed' => (bool)($row['is_completed'] ?? false),
            'updated_by_user_id' => $this->intValue($row['updated_by_user_id'] ?? 0),
            'updated_by_display_name' => $this->stringValue($row['updated_by_display_name'] ?? ''),
            'updated_at' => $this->stringValue($row['updated_at'] ?? ''),
            'version' => max(1, $this->intValue($row['state_version'] ?? 1)),
        ];
    }

    /**
     * HR: Zaustavlja write operaciju kada početna migracija nije primijenjena.
     * EN: Stops a write operation when the initial migration has not been applied.
     */
    private function assertTablesReady(): void
    {
        if (!$this->tablesReady()) {
            throw new RuntimeException(__('Task tablice nisu instalirane.'));
        }
    }

    /**
     * HR: Pretvara skalarnu vrijednost u očišćen string.
     * EN: Converts a scalar value into a trimmed string.
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * HR: Pretvara brojivu vrijednost u cijeli broj.
     * EN: Converts a numeric value into an integer.
     */
    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }

    /**
     * HR: Zadržava samo string ključeve ORM retka za stabilan javni oblik podataka.
     * EN: Keeps only string keys from an ORM row for a stable public data shape.
     *
     * @param array<mixed, mixed> $row
     * @return array<string, mixed>
     */
    private function stringKeyedRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * HR: Generira nasumični RFC 4122 UUID v4 bez vanjske biblioteke.
     * EN: Generates a random RFC 4122 UUID v4 without an external library.
     */
    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        );
    }
}
