<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Service;

/**
 * HR: Definira najmanji pristup dokumentu koji Task API treba za čitanje i promjenu zadataka.
 * EN: Defines the smallest document-access contract required by the Task API for reads and changes.
 *
 * Početnici / Beginners:
 * HR: Implementacija i dalje koristi Editor i opcionalni Workspace ACL. Sučelje
 * samo čini granicu jasnom i omogućuje izolirano testiranje API ugovora.
 * EN: The implementation still uses Editor and optional Workspace ACL rules. The
 * interface only makes that boundary explicit and allows isolated API-contract tests.
 */
interface TaskApiDocumentAccessInterface
{
    /**
     * HR: Vraća HTML aktualne objavljene verzije nakon provjere prava čitanja.
     * EN: Returns current published-version HTML after checking read access.
     */
    public function publishedHtml(string $documentId, string $language): string;

    /**
     * HR: Vraća jedan zadatak nakon provjere prava čitanja dokumenta.
     * EN: Returns one task after checking document read access.
     */
    public function taskForRead(
        string $documentId,
        string $language,
        string $taskUuid,
    ): TaskDefinition;

    /**
     * HR: Vraća jedan zadatak samo kada ga aktualni korisnik smije mijenjati.
     * EN: Returns one task only when the current user may change it.
     */
    public function taskForChange(
        string $documentId,
        string $language,
        string $taskUuid,
    ): TaskDefinition;

    /**
     * HR: Vraća stabilan identitet aktualnog korisnika za audit promjene.
     * EN: Returns the current user's stable identity for the change audit.
     *
     * @return array{id:int,display_name:string}
     */
    public function auditIdentity(): array;
}
