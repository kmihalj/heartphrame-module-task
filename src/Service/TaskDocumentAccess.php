<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Service;

use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorApiActorContext;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorDocument;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorDocumentVersion;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorService;
use AaiEduHr\HeartPhrameModuleEditorHtml\Service\EditorWorkspaceIntegration;
use HeartPhrame\Authn\AuthnHandlerInterface;
use RuntimeException;

use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function trim;

/**
 * HR: Razrješava objavljeni HTML dokument i provjerava pravo promjene zadatka.
 *     Task modul tako ne duplicira Editorova ni Workspaceova ACL pravila.
 * EN: Resolves published HTML documents and checks task-change permission.
 *     This keeps the Task module from duplicating Editor or Workspace ACL rules.
 */
final readonly class TaskDocumentAccess implements TaskApiDocumentAccessInterface
{
    /**
     * HR: Prima Editor kao izvor dokumenta, auth kontekst i opcionalni Workspace most.
     * EN: Receives Editor as the document source, auth context, and the optional Workspace bridge.
     */
    public function __construct(
        private EditorService $editor,
        private AuthnHandlerInterface $authnHandler,
        private EditorWorkspaceIntegration $workspace,
        private TaskDefinitionParser $definitions,
        private EditorApiActorContext $apiActors,
    ) {
    }

    /**
     * HR: Vraća objavljeni HTML koji čitatelji trenutačno vide.
     * EN: Returns the published HTML currently visible to readers.
     */
    public function publishedHtml(string $documentId, string $language): string
    {
        $documentId = $this->editor->normalizeDocumentId($documentId);
        $language = $this->editor->normalizeLanguage($language);
        $document = $documentId !== '' ? $this->editor->loadPublic($documentId, $language) : null;
        if (!$document instanceof EditorDocument) {
            throw new RuntimeException(__('Dokument nije pronađen.'));
        }

        if ($this->workspace->ownsDocument($documentId)) {
            if (!$this->workspace->canReadDocument($documentId)) {
                throw new RuntimeException(__('Nemate pravo pregleda ovog dokumenta.'));
            }

            $versionNumber = $this->workspace->publicationVersion($documentId, $language);
            if ($versionNumber === null || $versionNumber <= 0) {
                throw new RuntimeException(__('Dokument još nije objavljen.'));
            }

            $version = $this->editor->loadVersion($documentId, $language, $versionNumber);
            if (!$version instanceof EditorDocumentVersion) {
                throw new RuntimeException(__('Objavljena verzija dokumenta nije pronađena.'));
            }

            return $version->html;
        }

        return $document->html;
    }

    /**
     * HR: Pronalazi zadatak u aktualnoj objavi i potvrđuje da ga prijavljeni
     *     korisnik smije označiti ili odznačiti.
     * EN: Finds a task in the current publication and confirms that the
     *     authenticated user may check or uncheck it.
     */
    public function taskForChange(
        string $documentId,
        string $language,
        string $taskUuid,
    ): TaskDefinition {
        $user = $this->currentUser();
        if ($user === null) {
            throw new RuntimeException(__('Za promjenu zadatka potrebna je prijava.'));
        }

        $definition = $this->definitions->find(
            $this->publishedHtml($documentId, $language),
            trim($taskUuid),
        );
        if (!$definition instanceof TaskDefinition) {
            throw new RuntimeException(__('Zadatak ne postoji u aktualnoj objavi dokumenta.'));
        }

        if ($definition->scope === 'viewers') {
            return $definition;
        }

        $isAdmin = (bool)($user['is_admin'] ?? false);
        $canEdit = $this->workspace->ownsDocument($documentId)
            ? $this->workspace->canEditDocument($documentId)
            : $isAdmin;
        if (!$canEdit) {
            throw new RuntimeException(__('Nemate pravo mijenjanja ovog zadatka.'));
        }

        return $definition;
    }

    /**
     * HR: Pronalazi task u aktualnoj objavi nakon provjere prava čitanja dokumenta.
     * EN: Finds a task in the current publication after checking document read access.
     */
    public function taskForRead(
        string $documentId,
        string $language,
        string $taskUuid,
    ): TaskDefinition {
        $definition = $this->definitions->find(
            $this->publishedHtml($documentId, $language),
            trim($taskUuid),
        );
        if (!$definition instanceof TaskDefinition) {
            throw new RuntimeException(__('Zadatak ne postoji u aktualnoj objavi dokumenta.'));
        }

        return $definition;
    }

    /**
     * HR: Vraća stabilni identitet korisnika za audit zapisa.
     * EN: Returns the stable current-user identity used by audit records.
     *
     * @return array{id:int,display_name:string}
     */
    public function auditIdentity(): array
    {
        $user = $this->currentUser();
        if ($user === null) {
            return ['id' => 0, 'display_name' => ''];
        }

        $displayName = '';
        foreach (['display_name', 'name', 'login_identifier', 'email'] as $key) {
            if (is_scalar($user[$key] ?? null) && trim((string)$user[$key]) !== '') {
                $displayName = trim((string)$user[$key]);
                break;
            }
        }

        return [
            'id' => is_numeric($user['id'] ?? null) ? (int)$user['id'] : 0,
            'display_name' => $displayName,
        ];
    }

    /**
     * HR: Provjerava može li aktualni korisnik mijenjati zadatke ograničene na uređivače.
     * EN: Checks whether the current user may change editor-only tasks.
     */
    public function canEditDocument(string $documentId): bool
    {
        $user = $this->currentUser();
        if ($user === null) {
            return false;
        }

        return $this->workspace->ownsDocument($documentId)
            ? $this->workspace->canEditDocument($documentId)
            : (bool)($user['is_admin'] ?? false);
    }

    /**
     * HR: Provjerava postoji li prijavljeni korisnik.
     * EN: Checks whether an authenticated user exists.
     */
    public function isAuthenticated(): bool
    {
        return $this->currentUser() !== null;
    }

    /**
     * HR: Normalizira auth payload i odbacuje anonimni ili nevaljani identitet.
     * EN: Normalizes the auth payload and rejects an anonymous or invalid identity.
     *
     * @return array<string, mixed>|null
     */
    private function currentUser(): ?array
    {
        $user = $this->apiActors->actor() ?? $this->authnHandler->userData();
        if (!is_array($user) || !is_numeric($user['id'] ?? null)) {
            return null;
        }

        $normalized = [];
        foreach ($user as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
