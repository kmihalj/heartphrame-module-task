# Task Module Guide

Croatian version: [index_hr.md](index_hr.md)

## 1. Mental Model

The module separates a task's **versioned definition** from its **current
operational state**.

The HTML document owns:

- list and task UUIDs;
- task labels and order;
- the `editors` or `viewers` toggle scope.

The Task module owns:

- the latest completion value;
- who changed it and when;
- an immutable event for every real transition.

This means publishing or restoring an HTML version changes the available task
definitions without rewriting the operational audit log.

## 2. Dependencies and Optional Integrations

The required dependency chain is:

`Framework -> ORM -> Auth -> HTML Editor -> Task`

Task uses only public services from these packages. Workspace is detected
through the HTML Editor's public integration bridge and remains optional.
Notification is suggested for future assignments and due-date alerts, but Task
does not require it to load or save state.

Without Workspace, standalone HTML Editor administrators can change
`editors`-scope tasks. With Workspace, inherited document ACL decides who may
read and edit the published page.

## 3. Portable Data Model

The single initial ORM migration creates:

| Table | Responsibility |
| --- | --- |
| `task_item_states` | Latest state per document and task UUID |
| `task_item_events` | Immutable audit transitions |

State is unique per `document_id + task_uuid`. Translations of the same
document therefore share completion state, while copied or imported documents
cannot accidentally modify the source document even if their task UUIDs match.

The schema uses the ORM only and is portable across SQLite, PostgreSQL, and
MySQL/MariaDB. The migration contains no seed or test data.

## 4. Security and State Changes

The browser sends the desired state, document ID, language, task UUID, and a
CSRF token. The server does not trust HTML attributes or hidden controls.
For every change it:

1. requires an authenticated user;
2. reloads the current published document;
3. confirms that the task UUID still exists;
4. verifies document read access;
5. verifies the task's current toggle scope;
6. sets the desired state idempotently;
7. creates an audit event only when the value changes.

The response returns the normalized state and the identity and timestamp shown
below the checkbox. Errors are localized through the standard HeartPhrame
module translation files.

## 5. Rendering and Version Behavior

Published views are interactive only when the current user has permission.
Draft previews, document history, filesystem snapshots, and ZIP exports are
static: they show the state captured while rendering and never expose active
state-changing controls.

The renderer batch-loads all task states in one query. A page with many tasks
therefore does not issue one database query per checkbox.

The HTML Editor remains the owner of:

- authoring controls and source normalization;
- HTML versions and drafts;
- attachments and exports;
- standalone and Workspace document views.

Task contributes a small renderer, stylesheet, JavaScript controller, JSON
endpoints, and persistence service. The editor detects this integration
optionally, so it still works when Task is not installed.

## 6. Installation and Verification

```bash
composer require aaieduhr/heartphrame-module-task
vendor/bin/hph task:install-migration
vendor/bin/hph orm-migrate:up
composer on-commit
```

After enabling the module after HTML Editor, create a task list from the editor
toolbar, publish the page, toggle a task as an authorized user, and verify that
the audit label changes. Repeat as a reader, editor, and guest to verify the
selected scope.

Uninstalling or disabling Task does not prevent the HTML Editor from loading.
The declarative list remains HTML content, while interactive state controls
are simply no longer rendered.

## 7. API integration

The optional API module exposes ACL-aware task reads, state changes, and audit.
Definitions remain part of Editor page writes. See [API integration](api_en.md).

See [Backup and restore](backup_en.md) for complete and workspace-scoped task
state and audit history.
