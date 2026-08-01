# HeartPhrame Task Module

[Hrvatska verzija](README_hr.md)

The Task module adds interactive, audited task lists to versioned HeartPhrame
HTML documents.

## Dependencies

Required, in enable order:

1. `aaieduhr/heartphrame-framework` (`dev-main`)
2. `aaieduhr/heartphrame-module-orm` (`dev-main`)
3. `aaieduhr/heartphrame-module-auth` (`dev-main`)
4. `aaieduhr/heartphrame-module-editor-html` (`dev-main`)
5. `aaieduhr/heartphrame-module-task` (`dev-main`)

Optional integrations:

- Workspace supplies inherited view/edit permissions.
- API exposes published task state and immutable audit endpoints.
- Notification is reserved for assignment and due-date notification workflows.

```bash
composer require aaieduhr/heartphrame-module-task:dev-main
vendor/bin/hph task:install-migration
vendor/bin/hph orm-migrate:up
```

Croatian documentation: [README_hr.md](README_hr.md)

## Features

- task-list creation and editing through the optional HTML Editor toolbar
- current completion state stored separately from versioned HTML
- immutable audit events with user identity and timestamp
- per-list `editors` or `viewers` toggle permission
- inherited Workspace ACL when a document belongs to a Workspace
- accessible checkboxes with automatic, CSRF-protected persistence
- static and safe draft, history, filesystem snapshot, and ZIP export output
- portable ORM schema for SQLite, PostgreSQL, and MySQL/MariaDB
- no external JavaScript framework and no seed or sample data
- optional ACL-aware HTTP API for task state and audit

Task definitions, labels, order, and permission scope remain in the document.
The database contains only mutable completion state and its audit trail.

## Requirements

- PHP 8.2 or newer with DOM
- `aaieduhr/heartphrame-framework`
- `aaieduhr/heartphrame-module-auth`
- `aaieduhr/heartphrame-module-orm`
- `aaieduhr/heartphrame-module-editor-html`

Workspace and Notification are optional integrations. The module works with
the standalone HTML Editor when they are absent.

## Installation

```bash
composer require aaieduhr/heartphrame-module-task
vendor/bin/hph task:install-migration
vendor/bin/hph orm-migrate:up
```

Enable the module after ORM, Auth, and HTML Editor:

```php
'aaieduhr/heartphrame-module-orm',
'aaieduhr/heartphrame-module-auth',
'aaieduhr/heartphrame-module-editor-html',
'aaieduhr/heartphrame-module-task',
```

The single initial migration creates the complete schema. It deliberately
contains no users, task lists, states, events, or other test data.

## Permission Scopes

- `editors`: only standalone Editor administrators or users with inherited
  Workspace edit permission may change task state.
- `viewers`: every authenticated user who may read the published document may
  change task state.
- Guests may read public lists but cannot create attributed state changes.

Every POST operation reloads the published document, confirms that the task
still exists, rechecks read and toggle permissions, and writes an event only
when the state actually changes.

## Documentation

The detailed architecture, storage model, integration contract, and operating
guide are in [docs/index_en.md](docs/index_en.md). See also
[API integration](docs/api_en.md).

## Licence

This work is published under the
[European Union Public License (EUPL) v1.2](LICENSE).

## Dependency policy

The Framework and internal HeartPhrame modules are required from the moving
`dev-main` branch. This module does not commit `composer.lock`; CI resolves
the latest development heads and runs the complete `composer on-commit` suite.
