# Backup and restore

Task contributes `task` for complete task state/history and `task-workspace` for tasks attached to documents in one workspace. Assignees and actors use Auth portable identities; document references use Editor portable identities.

Workspace scope includes only task rows whose document belongs to the selected tree. Tasks linked to a document outside the selection are not smuggled into the archive. Import order is enforced through provider dependencies.

Operational locks and temporary worker state are not backup data. After restore, verify task ACL through the restored page and create one new transition to confirm notifications and audit hooks on the target installation.
