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

        Schema::create($tables['roles'], function (Blueprint $table) use ($pk) {
            $this->primaryKey($table, $pk);
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('guard_name')->default('web');
            $table->string('scope_type')->default('global');
            $table->string('scope_id')->default('');
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'guard_name', 'scope_type', 'scope_id'], 'roles_name_guard_scope_unique');
            $table->index(['scope_type', 'scope_id']);
            $table->index('is_active');
            $table->index(['slug', 'guard_name']);
        });

        Schema::create($tables['permissions'], function (Blueprint $table) use ($pk) {
            $this->primaryKey($table, $pk);
            $table->string('name');
            $table->string('slug');
            $table->string('resource')->nullable();
            $table->string('action')->nullable();
            $table->string('group')->nullable();
            $table->text('description')->nullable();
            $table->string('guard_name')->default('web');
            $table->string('scope_type')->default('global');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_dangerous')->default(false);
            $table->string('risk_level')->default('LOW');
            $table->boolean('requires_audit')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'guard_name'], 'permissions_name_guard_unique');
            $table->index(['resource', 'action']);
            $table->index('group');
            $table->index('scope_type');
            $table->index('is_active');
            $table->index('is_dangerous');
        });

        Schema::create($tables['role_permissions'], function (Blueprint $table) use ($pk, $tables) {
            $this->primaryKey($table, $pk);
            $this->foreignKey($table, $pk, 'role_id');
            $this->foreignKey($table, $pk, 'permission_id');
            $table->string('effect')->default('allow');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['role_id', 'permission_id'], 'role_permissions_unique');
            $table->index('permission_id');
            $table->index(['role_id', 'effect']);

            $table->foreign('role_id')
                ->references('id')
                ->on($tables['roles'])
                ->cascadeOnDelete();
            $table->foreign('permission_id')
                ->references('id')
                ->on($tables['permissions'])
                ->cascadeOnDelete();
        });

        Schema::create($tables['user_roles'], function (Blueprint $table) use ($pk, $userKey, $tables) {
            $this->primaryKey($table, $pk);
            $table->string('user_type');
            $this->userKey($table, $userKey, 'user_id');
            $this->foreignKey($table, $pk, 'role_id');
            $table->string('scope_type')->default('global');
            $table->string('scope_id')->default('');
            $this->userKey($table, $userKey, 'assigned_by', true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_type', 'user_id', 'role_id', 'scope_type', 'scope_id'],
                'user_roles_unique'
            );
            $table->index(['user_type', 'user_id']);
            $table->index('role_id');
            $table->index(['scope_type', 'scope_id']);
            $table->index(['user_type', 'user_id', 'scope_id']);
            $table->index('expires_at');

            $table->foreign('role_id')
                ->references('id')
                ->on($tables['roles'])
                ->cascadeOnDelete();
        });

        Schema::create($tables['user_permissions'], function (Blueprint $table) use ($pk, $userKey, $tables) {
            $this->primaryKey($table, $pk);
            $table->string('user_type');
            $this->userKey($table, $userKey, 'user_id');
            $this->foreignKey($table, $pk, 'permission_id');
            $table->string('effect')->default('allow');
            $table->string('scope_type')->default('global');
            $table->string('scope_id')->default('');
            $this->userKey($table, $userKey, 'assigned_by', true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_type', 'user_id', 'permission_id', 'scope_type', 'scope_id'],
                'user_permissions_unique'
            );
            $table->index('permission_id');
            $table->index(['user_type', 'user_id']);
            $table->index('scope_id');
            $table->index('expires_at');
            $table->index('effect');

            $table->foreign('permission_id')
                ->references('id')
                ->on($tables['permissions'])
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        Schema::dropIfExists($tables['user_permissions']);
        Schema::dropIfExists($tables['user_roles']);
        Schema::dropIfExists($tables['role_permissions']);
        Schema::dropIfExists($tables['permissions']);
        Schema::dropIfExists($tables['roles']);
    }

    private function primaryKey(Blueprint $table, string $type): void
    {
        match ($type) {
            'uuid' => $table->uuid('id')->primary(),
            'ulid' => $table->ulid('id')->primary(),
            default => $table->id(),
        };
    }

    private function foreignKey(Blueprint $table, string $type, string $name)
    {
        return match ($type) {
            'uuid' => $table->uuid($name),
            'ulid' => $table->ulid($name),
            default => $table->unsignedBigInteger($name),
        };
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
