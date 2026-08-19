<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Service;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use HeartPhrame\Routing\UrlGenerator;
use Throwable;

use function array_column;
use function array_values;
use function dirname;
use function file_get_contents;
use function implode;
use function is_array;
use function is_scalar;
use function is_string;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function trim;

use const LIBXML_HTML_NODEFDTD;
use const LIBXML_HTML_NOIMPLIED;

/**
 * HR: Pretvara deklarativni task-list HTML u pristupačne interaktivne
 *     checkboxove i pritom sva stanja učitava jednim upitom.
 * EN: Turns declarative task-list HTML into accessible interactive checkboxes
 *     while loading all mutable states with one query.
 */
final readonly class TaskHtmlRenderer
{
    /**
     * HR: Prima parser, spremište stanja, ACL kontekst i generator ruta.
     * EN: Receives the parser, state store, ACL context, and route generator.
     */
    public function __construct(
        private TaskDefinitionParser $definitions,
        private TaskStateService $states,
        private TaskDocumentAccess $access,
        private UrlGenerator $urlGenerator,
    ) {
    }

    /**
     * HR: Renderira sve liste u dokumentu. Interaktivnost se može isključiti za
     *     povijest, nacrt i ZIP export, ali se spremljeno stanje i dalje vidi.
     * EN: Renders every list in a document. Interactivity may be disabled for
     *     history, drafts, and ZIP export while persisted state remains visible.
     */
    public function render(
        string $html,
        string $documentId,
        string $language,
        bool $interactive = true,
    ): string {
        $definitions = $this->definitions->parse($html);
        if ($definitions === []) {
            return $html;
        }

        [$document, $root] = $this->document($html);
        if (!$root instanceof DOMElement) {
            return $html;
        }

        $stateRows = $this->states->statesFor(
            $documentId,
            array_values(array_column($definitions, 'uuid')),
        );
        $byUuid = [];
        $byListUuid = [];
        $latestStateByListUuid = [];
        foreach ($definitions as $definition) {
            $byUuid[$definition->uuid] = $definition;
            $byListUuid[$definition->listUuid][] = $definition;

            $candidate = is_array($stateRows[$definition->uuid] ?? null)
                ? $stateRows[$definition->uuid]
                : [];
            $current = $latestStateByListUuid[$definition->listUuid] ?? null;
            if ($candidate !== [] && $this->isNewerState($candidate, is_array($current) ? $current : null)) {
                $latestStateByListUuid[$definition->listUuid] = $candidate;
            }
        }

        $xpath = new DOMXPath($document);
        $items = $xpath->query('.//*[@data-task-uuid]', $root);
        if ($items === false) {
            return $html;
        }

        $nodes = [];
        foreach ($items as $item) {
            if ($item instanceof DOMElement) {
                $nodes[] = $item;
            }
        }

        foreach ($nodes as $item) {
            $uuid = trim($item->getAttribute('data-task-uuid'));
            $definition = $byUuid[$uuid] ?? null;
            if (!$definition instanceof TaskDefinition) {
                continue;
            }

            $state = is_array($stateRows[$uuid] ?? null) ? $stateRows[$uuid] : [];
            $completed = (bool)($state['is_completed'] ?? false);
            $canToggle = $interactive
                && $this->access->isAuthenticated()
                && ($definition->scope === 'viewers' || $this->access->canEditDocument($documentId));
            $this->renderItem(
                $document,
                $item,
                $definition,
                $completed,
                $canToggle,
                $documentId,
                $language,
            );
        }

        $lists = $xpath->query('.//*[@data-editor-html-task-list="1"]', $root);
        if ($lists !== false) {
            $labels = $this->clientLabels($language);
            foreach ($lists as $list) {
                if (!$list instanceof DOMElement) {
                    continue;
                }

                $listUuid = trim($list->getAttribute('data-task-list-uuid'));
                $listDefinitions = $byListUuid[$listUuid] ?? [];
                $firstDefinition = $listDefinitions[0] ?? null;
                if (!$firstDefinition instanceof TaskDefinition) {
                    continue;
                }

                $listLabel = trim($firstDefinition->listLabel) !== ''
                    ? trim($firstDefinition->listLabel)
                    : $labels['list'];
                $list->setAttribute('class', 'editor-task-list');
                $list->setAttribute('data-task-state-url', $this->statePath());
                $list->setAttribute('data-task-csrf-url', $this->csrfPath());
                $list->setAttribute('data-task-document', $documentId);
                $list->setAttribute('data-task-language', $language);
                $list->setAttribute('data-task-list-label', $listLabel);
                $list->setAttribute('data-task-last-changed-label', $labels['last_changed']);
                $list->setAttribute('data-task-close-label', $labels['close']);
                $list->setAttribute('data-task-csrf-error', $labels['csrf_error']);
                $list->setAttribute('data-task-save-error', $labels['save_error']);

                /*
                 * HR: Jedna lista dobiva jedan zajednički audit red. Najnovije
                 *     stanje svih njezinih zadataka predstavlja zadnju promjenu.
                 * EN: One list receives one shared audit row. The newest state
                 *     across its tasks represents the list's last change.
                 */
                $meta = $document->createElement('div');
                $meta->setAttribute('class', 'editor-task-list-meta');
                $audit = $document->createElement('small');
                $audit->setAttribute('class', 'editor-task-list-audit');
                $auditText = $this->auditText(
                    is_array($latestStateByListUuid[$listUuid] ?? null)
                        ? $latestStateByListUuid[$listUuid]
                        : [],
                    $language,
                );
                if ($auditText !== '') {
                    $audit->appendChild($document->createTextNode($auditText));
                } else {
                    $audit->setAttribute('hidden', 'hidden');
                }

                $meta->appendChild($audit);

                $this->insertListMeta($xpath, $list, $meta);

                if (
                    $interactive
                    && $this->access->isAuthenticated()
                    && $this->urlGenerator->namedRouteExists('simbioza-user.toggle')
                    && $this->urlGenerator->namedRouteExists('simbioza-user.status')
                ) {
                    /*
                     * HR: Task modul izlaže neutralne DOM podatke samo kada je
                     *     aplikacijski modul praćenja stvarno uključen.
                     * EN: The Task module exposes neutral DOM data only when the
                     *     application following module is actually enabled.
                     */
                    $list->setAttribute(
                        'data-task-follow-toggle-url',
                        $this->urlGenerator->getPathFor('simbioza-user.toggle'),
                    );
                    $list->setAttribute(
                        'data-task-follow-status-url',
                        $this->urlGenerator->getPathFor('simbioza-user.status'),
                    );
                    $list->setAttribute('data-task-follow-label', $labels['follow']);
                    $list->setAttribute('data-task-unfollow-label', $labels['unfollow']);
                }
            }
        }

        return trim($this->innerHtml($root));
    }

    /**
     * HR: Brzo provjerava postoji li u HTML-u task-list marker.
     * EN: Quickly checks whether HTML contains a task-list marker.
     */
    public function hasTasks(string $html): bool
    {
        return str_contains($html, 'data-editor-html-task-list="1"')
            || str_contains($html, "data-editor-html-task-list='1'");
    }

    /**
     * HR: Zamjenjuje sadržaj jednog LI elementa isključivo checkboxom i tekstom zadatka.
     * EN: Replaces one LI element's content with only the task checkbox and text.
     */
    private function renderItem(
        DOMDocument $document,
        DOMElement $item,
        TaskDefinition $definition,
        bool $completed,
        bool $canToggle,
        string $documentId,
        string $language,
    ): void {
        while ($item->firstChild instanceof DOMNode) {
            $item->removeChild($item->firstChild);
        }

        $item->setAttribute(
            'class',
            $completed ? 'editor-task-item editor-task-item-completed' : 'editor-task-item',
        );

        $label = $document->createElement('label');
        $label->setAttribute('class', 'editor-task-control');

        $input = $document->createElement('input');
        $input->setAttribute('type', 'checkbox');
        $input->setAttribute('class', 'form-check-input editor-task-checkbox');
        $input->setAttribute('data-task-uuid', $definition->uuid);
        $input->setAttribute('data-task-document', $documentId);
        $input->setAttribute('data-task-language', $language);
        if ($completed) {
            $input->setAttribute('checked', 'checked');
        }

        if (!$canToggle) {
            $input->setAttribute('disabled', 'disabled');
        }

        $text = $document->createElement('span');
        $text->setAttribute('class', 'editor-task-text');
        $text->appendChild($document->createTextNode($definition->text));

        $label->appendChild($input);
        $label->appendChild($text);

        $item->appendChild($label);
    }

    /**
     * HR: Umeće zajednički red neposredno iza naslova liste, odnosno na početak liste bez naslova.
     * EN: Inserts the shared row immediately after the list heading, or first in a heading-less list.
     */
    private function insertListMeta(DOMXPath $xpath, DOMElement $list, DOMElement $meta): void
    {
        $headings = $xpath->query('./*[self::h2 or self::h3 or self::h4][1]', $list);
        $heading = $headings === false ? null : $headings->item(0);
        if ($heading instanceof DOMNode) {
            $next = $heading->nextSibling;
            if ($next instanceof DOMNode) {
                $list->insertBefore($meta, $next);

                return;
            }

            $list->appendChild($meta);

            return;
        }

        $first = $list->firstChild;
        if ($first instanceof DOMNode) {
            $list->insertBefore($meta, $first);

            return;
        }

        $list->appendChild($meta);
    }

    /**
     * HR: Uspoređuje audit retke prenosivim ISO vremenom i verzijom kao rezervom.
     * EN: Compares audit rows by portable ISO time and version as a fallback.
     *
     * @param array<string,mixed> $candidate
     * @param array<string,mixed>|null $current
     */
    private function isNewerState(array $candidate, ?array $current): bool
    {
        if ($current === null) {
            return true;
        }

        $candidateTime = is_scalar($candidate['updated_at'] ?? null)
            ? trim((string)$candidate['updated_at'])
            : '';
        $currentTime = is_scalar($current['updated_at'] ?? null)
            ? trim((string)$current['updated_at'])
            : '';
        if ($candidateTime !== $currentTime) {
            return $candidateTime > $currentTime;
        }

        $candidateVersion = is_scalar($candidate['state_version'] ?? null)
            ? (int)$candidate['state_version']
            : 0;
        $currentVersion = is_scalar($current['state_version'] ?? null)
            ? (int)$current['state_version']
            : 0;

        return $candidateVersion > $currentVersion;
    }

    /**
     * HR: Gradi lokaliziranu rečenicu o zadnjoj promjeni statusa.
     * EN: Builds a localized sentence describing the latest status change.
     *
     * @param array<string, mixed> $state
     */
    private function auditText(array $state, string $language): string
    {
        $name = is_scalar($state['updated_by_display_name'] ?? null)
            ? trim((string)$state['updated_by_display_name'])
            : '';
        $date = is_scalar($state['updated_at'] ?? null)
            ? trim((string)$state['updated_at'])
            : '';
        $date = $this->localizedDate($date, $language);
        if ($name === '' && $date === '') {
            return '';
        }

        $croatian = str_starts_with(strtolower(trim($language)), 'hr');
        $label = $croatian ? 'Zadnja promjena' : 'Last changed';
        $parts = [];
        if ($name !== '') {
            $parts[] = $name;
        }

        if ($date !== '') {
            $parts[] = $date;
        }

        return $label . ': ' . implode(' · ', $parts);
    }

    /**
     * HR: Formatira audit datum u uobičajeni hrvatski ili engleski prikaz.
     * EN: Formats the audit date using the customary Croatian or English layout.
     */
    private function localizedDate(string $value, string $language): string
    {
        if ($value === '') {
            return '';
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Throwable) {
            return $value;
        }

        return str_starts_with(strtolower(trim($language)), 'hr')
            ? $date->format('d.m.Y. H:i:s')
            : $date->format('Y-m-d H:i:s');
    }

    /**
     * HR: Vraća kratke lokalizirane oznake koje JavaScript treba nakon promjene stanja.
     * EN: Returns compact localized labels needed by JavaScript after a state change.
     *
     * @return array{list:string,last_changed:string,close:string,csrf_error:string,save_error:string,follow:string,unfollow:string}
     */
    private function clientLabels(string $language): array
    {
        if (str_starts_with(strtolower(trim($language)), 'hr')) {
            return [
                'list' => 'Lista zadataka',
                'last_changed' => 'Zadnja promjena',
                'close' => 'Zatvori',
                'csrf_error' => 'CSRF token nije dostupan.',
                'save_error' => 'Stanje zadatka nije moguće spremiti.',
                'follow' => 'Prati listu zadataka',
                'unfollow' => 'Prestani pratiti listu zadataka',
            ];
        }

        return [
            'list' => 'Task list',
            'last_changed' => 'Last changed',
            'close' => 'Close',
            'csrf_error' => 'CSRF token is unavailable.',
            'save_error' => 'Task state could not be saved.',
            'follow' => 'Follow task list',
            'unfollow' => 'Unfollow task list',
        ];
    }

    /**
     * HR: Vraća CSS potreban za statični standalone i ZIP prikaz taskova.
     * EN: Returns the CSS required by static standalone and ZIP task views.
     */
    public function standaloneCss(): string
    {
        $content = file_get_contents(dirname(__DIR__, 2) . '/resources/assets/tasks.css');

        return is_string($content) ? $content : '';
    }

    /**
     * HR: Parsira fragment unutar stabilnog root elementa.
     * EN: Parses a fragment inside a stable root element.
     *
     * @return array{DOMDocument, DOMElement|null}
     */
    private function document(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>'
            . '<div id="task-render-root">' . $html . '</div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('task-render-root');

        return [$document, $root instanceof DOMElement ? $root : null];
    }

    /**
     * HR: Vraća HTML svih potomaka bez pomoćnog root elementa.
     * EN: Returns all child HTML without the helper root element.
     */
    private function innerHtml(DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $root->ownerDocument?->saveHTML($child) ?? '';
        }

        return $html;
    }

    /**
     * HR: Vraća named state rutu uz siguran fallback.
     * EN: Returns the named state route with a safe fallback.
     */
    private function statePath(): string
    {
        return $this->pathFor('task.state', '/tasks/state');
    }

    /**
     * HR: Vraća named CSRF rutu uz siguran fallback.
     * EN: Returns the named CSRF route with a safe fallback.
     */
    private function csrfPath(): string
    {
        return $this->pathFor('task.csrf', '/tasks/csrf-token');
    }

    /**
     * HR: Generira named rutu ili fallback ispod aplikacijskog base patha.
     * EN: Generates a named route or a fallback below the application base path.
     */
    private function pathFor(string $name, string $fallback): string
    {
        return $this->urlGenerator->namedRouteExists($name)
            ? $this->urlGenerator->getPathFor($name)
            : rtrim($this->urlGenerator->getBasePath(), '/') . $fallback;
    }
}
