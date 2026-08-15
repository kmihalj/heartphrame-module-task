# Task API integration

Croatian version: [api_hr.md](api_hr.md)

## Ownership and scopes

Task owns mutable completion state and its immutable audit trail. The task-list
definition remains in the published HTML Editor document.

- `task:read` lists tasks, reads one task, and reads its audit history.
- `task:write` sets the desired completion value.

The key owner must also be allowed to read the published page. For an
`editors` list, changing state additionally requires page edit permission. For
a `viewers` list, every authenticated reader may change state.

## Public service

`AaiEduHr\HeartPhrameModuleTask\Api\TaskApiService` is transport-neutral and
does not depend on the API module. It supports:

- all tasks in the current published page;
- one task with current state;
- idempotent state update;
- newest audit events with actor identity and time.

The service always reloads the published definition. Changing task state does
not create a page version and repeated requests with the same value do not
create duplicate audit events.

## Editor relationship

Create, remove, rename, and reorder task lists through an HTML Editor page
write. The Editor structured `content` contract accepts any number of
`task_list` blocks. Use Task API routes only for operational checkbox state.

`TaskApiExtension` owns the four HTTP route declarations and
`TaskResourceController` adapts this service only when API is present.
