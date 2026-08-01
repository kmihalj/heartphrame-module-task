<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Api;

use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorApiActorContext;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskApiDocumentAccessInterface;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskDefinition;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskDefinitionParser;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskStateService;
use RuntimeException;

/**
 * HR: Neutralni domenski API za čitanje taskova i auditiranu promjenu statusa.
 * EN: Neutral domain API for reading tasks and audited state changes.
 *
 * Početnici / Beginners:
 * HR: Definicija task-liste ostaje u verzioniranom HTML-u. Ovaj servis mijenja
 * samo odvojeno stanje checkboxa pa čitanje stranice ne stvara novu verziju.
 * EN: The task-list definition remains in versioned HTML. This service changes
 * only separate checkbox state, so reading or toggling does not create a page version.
 */
final readonly class TaskApiService
{
    /**
     * HR: Prima Editor actor kontekst te postojeće Task domenske servise.
     * EN: Receives the Editor actor context and existing Task domain services.
     */
    public function __construct(
        private EditorApiActorContext $actors,
        private TaskApiDocumentAccessInterface $access,
        private TaskDefinitionParser $definitions,
        private TaskStateService $states,
    ) {
    }

    /**
     * HR: Vraća sve taskove iz aktualne objave s njihovim stanjem.
     * EN: Returns every task from the current publication with its state.
     *
     * @param array<string,mixed> $actor
     * @return list<array<string,mixed>>
     */
    public function listTasks(string $documentId, string $language, array $actor): array
    {
        return $this->actors->runAs($actor, function () use ($documentId, $language): array {
            $definitions = $this->definitions->parse(
                $this->access->publishedHtml($documentId, $language),
            );
            $states = $this->states->statesFor(
                $documentId,
                array_map(
                    static fn(TaskDefinition $definition): string => $definition->uuid,
                    $definitions,
                ),
            );

            return array_map(
                fn(TaskDefinition $definition): array => $this->taskDto(
                    $definition,
                    is_array($states[$definition->uuid] ?? null)
                        ? $states[$definition->uuid]
                        : [],
                ),
                $definitions,
            );
        });
    }

    /**
     * HR: Vraća jedan task iz aktualne objave.
     * EN: Returns one task from the current publication.
     *
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function getTask(
        string $documentId,
        string $language,
        string $taskUuid,
        array $actor,
    ): array {
        return $this->actors->runAs(
            $actor,
            function () use ($documentId, $language, $taskUuid): array {
                $definition = $this->taskForRead($documentId, $language, $taskUuid);
                $states = $this->states->statesFor($documentId, [$definition->uuid]);

                return $this->taskDto(
                    $definition,
                    is_array($states[$definition->uuid] ?? null)
                        ? $states[$definition->uuid]
                        : [],
                );
            },
        );
    }

    /**
     * HR: Postavlja status taska idempotentno uz ACL i audit.
     * EN: Sets task state idempotently with ACL and audit checks.
     *
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function setState(
        string $documentId,
        string $language,
        string $taskUuid,
        bool $completed,
        array $actor,
    ): array {
        return $this->actors->runAs(
            $actor,
            function () use ($documentId, $language, $taskUuid, $completed): array {
                try {
                    $definition = $this->access->taskForChange(
                        $documentId,
                        $language,
                        $taskUuid,
                    );
                    $identity = $this->access->auditIdentity();

                    return $this->stateDto(
                        $this->states->setState(
                            $definition,
                            $documentId,
                            $completed,
                            $identity['id'],
                            $identity['display_name'],
                        ),
                    );
                } catch (RuntimeException $runtimeException) {
                    throw $this->domainFailure($runtimeException);
                }
            },
        );
    }

    /**
     * HR: Vraća audit promjena jednog taska nakon read provjere stranice.
     * EN: Returns one task's change audit after checking page read access.
     *
     * @param array<string,mixed> $actor
     * @return list<array<string,mixed>>
     */
    public function history(
        string $documentId,
        string $language,
        string $taskUuid,
        int $limit,
        array $actor,
    ): array {
        return $this->actors->runAs(
            $actor,
            function () use ($documentId, $language, $taskUuid, $limit): array {
                $definition = $this->taskForRead($documentId, $language, $taskUuid);

                return array_map(
                    $this->eventDto(...),
                    $this->states->history($documentId, $definition->uuid, $limit),
                );
            },
        );
    }

    /**
     * HR: Učitava task za read operaciju i ujednačeno mapira grešku.
     * EN: Loads a task for a read operation and consistently maps failures.
     */
    private function taskForRead(
        string $documentId,
        string $language,
        string $taskUuid,
    ): TaskDefinition {
        try {
            return $this->access->taskForRead($documentId, $language, $taskUuid);
        } catch (RuntimeException $runtimeException) {
            throw $this->domainFailure($runtimeException);
        }
    }

    /**
     * HR: Spaja nepromjenjivu definiciju i aktualno stanje u javni DTO.
     * EN: Combines immutable definition and current state into a public DTO.
     *
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function taskDto(TaskDefinition $definition, array $state): array
    {
        return [
            'uuid' => $definition->uuid,
            'list_uuid' => $definition->listUuid,
            'text' => $definition->text,
            'toggle_scope' => $definition->scope,
            'state' => $this->stateDto($state, $definition->uuid, $definition->listUuid),
        ];
    }

    /**
     * HR: Normalizira stanje taska; nepostojeći redak predstavlja nedovršen task.
     * EN: Normalizes task state; a missing row represents an incomplete task.
     *
     * @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function stateDto(
        array $state,
        string $taskUuid = '',
        string $listUuid = '',
    ): array {
        return [
            'task_uuid' => $this->text($state['task_uuid'] ?? $taskUuid),
            'list_uuid' => $this->text($state['task_list_uuid'] ?? $listUuid),
            'completed' => (bool)($state['completed'] ?? $state['is_completed'] ?? false),
            'updated_by_user_id' => is_numeric($state['updated_by_user_id'] ?? null)
                ? (int)$state['updated_by_user_id']
                : null,
            'updated_by_display_name' => $this->text(
                $state['updated_by_display_name'] ?? '',
            ),
            'updated_at' => $this->text($state['updated_at'] ?? ''),
            'version' => max(
                0,
                $this->integer($state['version'] ?? $state['state_version'] ?? null),
            ),
        ];
    }

    /**
     * HR: Normalizira jedan audit događaj.
     * EN: Normalizes one audit event.
     *
     * @param array<string,mixed> $event
     * @return array<string,mixed>
     */
    private function eventDto(array $event): array
    {
        return [
            'uuid' => $this->text($event['uuid'] ?? ''),
            'task_uuid' => $this->text($event['task_uuid'] ?? ''),
            'list_uuid' => $this->text($event['task_list_uuid'] ?? ''),
            'completed' => (bool)($event['is_completed'] ?? false),
            'changed_by_user_id' => is_numeric($event['changed_by_user_id'] ?? null)
                ? (int)$event['changed_by_user_id']
                : null,
            'changed_by_display_name' => $this->text(
                $event['changed_by_display_name'] ?? '',
            ),
            'changed_at' => $this->text($event['created_at'] ?? ''),
        ];
    }

    /**
     * HR: Mapira očekivanu domensku grešku bez otkrivanja internih detalja.
     * EN: Maps an expected domain failure without exposing internal details.
     */
    private function domainFailure(RuntimeException $exception): TaskApiException
    {
        $message = $exception->getMessage();
        if (str_contains($message, 'pravo') || str_contains($message, 'prijav')) {
            return new TaskApiException('task_permission_denied', $message, 403);
        }

        if (str_contains($message, 'postoji') || str_contains($message, 'prona')) {
            return new TaskApiException('task_not_found', $message, 404);
        }

        return new TaskApiException('task_operation_failed', $message, 422);
    }

    /**
     * HR: Sigurno pretvara skalarnu vrijednost u tekst.
     * EN: Safely converts a scalar value into text.
     */
    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    /**
     * HR: Sigurno pretvara brojivu vrijednost u cijeli broj.
     * EN: Safely converts a numeric value into an integer.
     */
    private function integer(mixed $value): int
    {
        return is_numeric($value) ? (int)$value : 0;
    }
}
