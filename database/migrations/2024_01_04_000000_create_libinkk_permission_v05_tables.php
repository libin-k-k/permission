<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');
        $pk = config('permission.database.primary_key', 'bigint');
        $userKey = config('permission.database.user_key', 'bigint');

        Schema::create($tables['permission_delegations'] ?? 'permission_delegations', function (Blueprint $table) use ($pk, $userKey, $tables) {
            $this->primaryKey($table, $pk);
            $table->string('from_user_type');
            $this->userKey($table, $userKey, 'from_user_id');
            $table->string('to_user_type');
            $this->userKey($table, $userKey, 'to_user_id');
            $this->foreignKey($table, $pk, 'permission_id');
            $this->foreignKey($table, $pk, 'scope_id', true);
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->timestamp('revoked_at')->nullable();

            $table->index(['to_user_type', 'to_user_id', 'status'], 'permission_delegations_to_status_index');
            $table->index(['from_user_type', 'from_user_id'], 'permission_delegations_from_index');
            $table->index(['permission_id', 'status'], 'permission_delegations_permission_status_index');
            $table->index(['starts_at', 'expires_at'], 'permission_delegations_window_index');
            $table->index(['resource_type', 'resource_id'], 'permission_delegations_resource_index');

            $table->foreign('permission_id')
                ->references('id')
                ->on($tables['permissions'])
                ->cascadeOnDelete();
        });

        Schema::create($tables['permission_versions'] ?? 'permission_versions', function (Blueprint $table) use ($pk, $tables) {
            $this->primaryKey($table, $pk);
            $this->foreignKey($table, $pk, 'permission_id');
            $table->unsignedInteger('version');
            $table->json('definition');
            $table->string('changed_by')->nullable();
            $table->string('change_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['permission_id', 'version'], 'permission_versions_unique');
            $table->index('permission_id');

            $table->foreign('permission_id')
                ->references('id')
                ->on($tables['permissions'])
                ->cascadeOnDelete();
        });

        Schema::create($tables['authorization_audits'] ?? 'authorization_audits', function (Blueprint $table) use ($pk, $userKey) {
            $this->primaryKey($table, $pk);
            $table->string('user_type')->nullable();
            $this->userKey($table, $userKey, 'user_id', true);
            $this->foreignKey($table, $pk, 'permission_id', true);
            $this->foreignKey($table, $pk, 'role_id', true);
            $this->foreignKey($table, $pk, 'scope_id', true);
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable();
            $table->string('result')->nullable();
            $table->string('reason_code')->nullable();
            $table->text('reason')->nullable();
            $table->string('decision_source')->nullable();
            $table->string('request_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_type', 'user_id', 'created_at'], 'authorization_audits_user_created_index');
            $table->index(['permission_id', 'created_at'], 'authorization_audits_permission_created_index');
            $table->index(['result', 'reason_code'], 'authorization_audits_result_reason_index');
            $table->index('request_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        Schema::dropIfExists($tables['authorization_audits'] ?? 'authorization_audits');
        Schema::dropIfExists($tables['permission_versions'] ?? 'permission_versions');
        Schema::dropIfExists($tables['permission_delegations'] ?? 'permission_delegations');
    }

    private function primaryKey(Blueprint $table, string $type): void
    {
        match ($type) {
            'uuid' => $table->uuid('id')->primary(),
            'ulid' => $table->ulid('id')->primary(),
            default => $table->id(),
        };
    }

    private function foreignKey(Blueprint $table, string $type, string $name, bool $nullable = false)
    {
        $column = match ($type) {
            'uuid' => $table->uuid($name),
            'ulid' => $table->ulid($name),
            default => $table->unsignedBigInteger($name),
        };

        if ($nullable) {
            $column->nullable();
        }

        return $column;
    }

    private function userKey(Blueprint $table, string $type, string $name, bool $nullable = false)
    {
        $column = match ($type) {
            'uuid' => $table->uuid($name),
            'ulid' => $table->ulid($name),
            default => $table->unsignedBigInteger($name),
        };

        if ($nullable) {
            $column->nullable();
        }

        return $column;
    }
};
