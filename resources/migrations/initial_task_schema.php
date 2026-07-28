<?php

declare(strict_types=1);

use AaiEduHr\HeartPhrameModuleOrm\Database\Database;
use AaiEduHr\HeartPhrameModuleOrm\Database\Migration\ReversibleMigrationInterface;
use AaiEduHr\HeartPhrameModuleOrm\Database\Schema\Blueprint;
use AaiEduHr\HeartPhrameModuleTask\ModuleTask;

return new class implements ReversibleMigrationInterface {
    /**
     * HR: Kreira prenosive tablice aktualnog stanja zadataka i nepromjenjivog
     *     audit traga bez SQL-a specifičnog za pojedinu bazu.
     * EN: Creates portable current-task-state and immutable audit-trail tables
     *     without database-specific SQL.
     */
    public function up(Database $db): void
    {
        $schema = $db->schema();
        if (!$schema->hasTable(ModuleTask::TABLE_STATES)) {
            $schema->create(ModuleTask::TABLE_STATES, static function (Blueprint $table): void {
                $table->id();
                $table->string('task_uuid', 36)->index();
                $table->string('task_list_uuid', 36)->index();
                $table->string('document_id', 190)->index();
                $table->boolean('is_completed')->default(false)->index();
                $table->bigInteger('updated_by_user_id')->unsigned()->nullable()->index();
                $table->string('updated_by_display_name', 255)->nullable();
                $table->bigInteger('state_version')->unsigned()->default(1);
                $table->timestamps();

                $table->index(
                    ['document_id', 'task_list_uuid'],
                    'task_state_document_list_idx',
                );
                $table->unique(
                    ['document_id', 'task_uuid'],
                    'task_state_document_task_uq',
                );
            });
        }

        if (!$schema->hasTable(ModuleTask::TABLE_EVENTS)) {
            $schema->create(ModuleTask::TABLE_EVENTS, static function (Blueprint $table): void {
                $table->id();
                $table->string('uuid', 36)->unique();
                $table->string('task_uuid', 36)->index();
                $table->string('task_list_uuid', 36)->index();
                $table->string('document_id', 190)->index();
                $table->boolean('is_completed')->default(false)->index();
                $table->bigInteger('changed_by_user_id')->unsigned()->nullable()->index();
                $table->string('changed_by_display_name', 255)->nullable();
                $table->timestamp('created_at')->index();

                $table->index(
                    ['document_id', 'task_uuid', 'created_at'],
                    'task_event_document_task_created_idx',
                );
            });
        }
    }

    /**
     * HR: Uklanja isključivo tablice u vlasništvu Task modula.
     * EN: Drops only tables owned by the Task module.
     */
    public function down(Database $db): void
    {
        $db->schema()->dropIfExists(ModuleTask::TABLE_EVENTS);
        $db->schema()->dropIfExists(ModuleTask::TABLE_STATES);
    }
};
