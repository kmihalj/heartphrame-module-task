<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Controller;

use AaiEduHr\HeartPhrameModuleTask\Service\TaskDocumentAccess;
use AaiEduHr\HeartPhrameModuleTask\Service\TaskStateService;
use HeartPhrame\Http\ResponseFactory;
use HeartPhrame\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function filter_var;
use function is_array;
use function is_scalar;
use function trim;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOL;

/**
 * HR: Poslužuje male JSON operacije za interaktivno stanje zadataka.
 * EN: Serves compact JSON operations for interactive task state.
 */
final readonly class TaskController
{
    /**
     * HR: Prima HTTP, session, ACL i persistence servise Task modula.
     * EN: Receives the Task module's HTTP, session, ACL, and persistence services.
     */
    public function __construct(
        private ResponseFactory $responseFactory,
        private SessionInterface $session,
        private TaskDocumentAccess $access,
        private TaskStateService $states,
        private ?LoggerInterface $technicalLogger = null,
    ) {
    }

    /**
     * HR: Postavlja željeno checkbox stanje nakon ponovne provjere objave i ACL-a.
     * EN: Sets the desired checkbox state after rechecking publication and ACL.
     */
    public function state(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        $documentId = $this->stringValue($body['document_id'] ?? '');
        $language = $this->stringValue($body['language'] ?? '');
        $taskUuid = $this->stringValue($body['task_uuid'] ?? '');
        $completed = filter_var(
            $body['completed'] ?? null,
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE,
        );
        if ($documentId === '' || $taskUuid === '' || $completed === null) {
            return $this->responseFactory->json([
                'ok' => false,
                'error' => __('Podaci zadatka nisu potpuni.'),
                'csrf' => $this->csrfPayload(),
            ], 422);
        }

        try {
            $task = $this->access->taskForChange($documentId, $language, $taskUuid);
            $identity = $this->access->auditIdentity();
            $state = $this->states->setState(
                $task,
                $documentId,
                $completed,
                $identity['id'],
                $identity['display_name'],
            );
        } catch (Throwable $throwable) {
            $this->technicalLogger?->warning('Task state operation failed.', [
                'module' => 'task',
                'document_id' => $documentId,
                'task_uuid' => $taskUuid,
                'exception' => $throwable,
            ]);

            return $this->responseFactory->json([
                'ok' => false,
                'error' => $throwable->getMessage(),
                'csrf' => $this->csrfPayload(),
            ], 403);
        }

        return $this->responseFactory->json([
            'ok' => true,
            'state' => $state,
            'csrf' => $this->csrfPayload(),
        ]);
    }

    /**
     * HR: Vraća audit događaje samo ako korisnik smije pristupiti aktualnom zadatku.
     * EN: Returns audit events only when the user may access the current task.
     */
    public function history(ServerRequestInterface $request, string $taskUuid): ResponseInterface
    {
        $query = $request->getQueryParams();
        $documentId = $this->stringValue($query['document'] ?? '');
        $language = $this->stringValue($query['lang'] ?? '');

        try {
            $this->access->taskForChange($documentId, $language, $taskUuid);
            $events = $this->states->history($documentId, $taskUuid);
        } catch (Throwable $throwable) {
            $this->technicalLogger?->warning('Task history operation failed.', [
                'module' => 'task',
                'document_id' => $documentId,
                'task_uuid' => $taskUuid,
                'exception' => $throwable,
            ]);

            return $this->responseFactory->json([
                'ok' => false,
                'error' => $throwable->getMessage(),
            ], 403);
        }

        return $this->responseFactory->json(['ok' => true, 'events' => $events]);
    }

    /**
     * HR: Isporučuje aktualni CSRF token za uzastopne AJAX promjene.
     * EN: Supplies the current CSRF token for consecutive AJAX changes.
     */
    public function csrf(): ResponseInterface
    {
        return $this->responseFactory->json(['ok' => true, 'csrf' => $this->csrfPayload()]);
    }

    /**
     * HR: Poslužuje mali CSS asset Task prikaza.
     * EN: Serves the small Task view CSS asset.
     */
    public function css(): ResponseInterface
    {
        return $this->responseFactory->file(
            dirname(__DIR__, 2) . '/resources/assets/tasks.css',
            'text/css; charset=utf-8',
        );
    }

    /**
     * HR: Poslužuje framework-neovisni JavaScript za autosave checkboxova.
     * EN: Serves framework-independent JavaScript for checkbox autosave.
     */
    public function js(): ResponseInterface
    {
        return $this->responseFactory->file(
            dirname(__DIR__, 2) . '/resources/assets/tasks.js',
            'application/javascript; charset=utf-8',
        );
    }

    /**
     * HR: Vraća token payload u istom obliku kao Editorovi AJAX endpointi.
     * EN: Returns the token payload in the same shape as Editor AJAX endpoints.
     *
     * @return array{name:string,token:string}
     */
    private function csrfPayload(): array
    {
        return [
            'name' => $this->session->getCsrfTokenName(),
            'token' => $this->session->getOrGenerateCsrfToken(),
        ];
    }

    /**
     * HR: Sigurno čita samo skalarnu ulaznu vrijednost.
     * EN: Safely reads scalar input values only.
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }
}
