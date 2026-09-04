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

        Schema::create($tables['scopes'] ?? 'scopes', function (Blueprint $table) use ($pk) {
            $this->primaryKey($table, $pk);
            $table->string('type')->default('tenant');
            $table->string('name');
            $table->string('key')->nullable();
            $this->foreignKey($table, $pk, 'parent_id', true);
            $table->string('scopeable_type')->nullable();
            $table->string('scopeable_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'key']);
            $table->index('parent_id');
            $table->index(['scopeable_type', 'scopeable_id']);
        });

        Schema::create($tables['role_scopes'] ?? 'role_scopes', function (Blueprint $table) use ($pk, $tables) {
            $this->primaryKey($table, $pk);
            $this->foreignKey($table, $pk, 'role_id');
            $this->foreignKey($table, $pk, 'scope_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['role_id', 'scope_id'], 'role_scopes_unique');

            $table->foreign('role_id')->references('id')->on($tables['roles'])->cascadeOnDelete();
            $table->foreign('scope_id')->references('id')->on($tables['scopes'] ?? 'scopes')->cascadeOnDelete();
        });

        Schema::create($tables['permission_scopes'] ?? 'permission_scopes', function (Blueprint $table) use ($pk, $tables) {
            $this->primaryKey($table, $pk);
            $this->foreignKey($table, $pk, 'permission_id');
            $this->foreignKey($table, $pk, 'scope_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['permission_id', 'scope_id'], 'permission_scopes_unique');

            $table->foreign('permission_id')->references('id')->on($tables['permissions'])->cascadeOnDelete();
            $table->foreign('scope_id')->references('id')->on($tables['scopes'] ?? 'scopes')->cascadeOnDelete();
        });

        Schema::create($tables['user_scopes'] ?? 'user_scopes', function (Blueprint $table) use ($pk, $userKey, $tables) {
            $this->primaryKey($table, $pk);
            $table->string('user_type');
            $this->userKey($table, $userKey, 'user_id');
            $this->foreignKey($table, $pk, 'scope_id');
            $table->timestamps();

            $table->unique(['user_type', 'user_id', 'scope_id'], 'user_scopes_unique');
            $table->index(['user_type', 'user_id']);

            $table->foreign('scope_id')->references('id')->on($tables['scopes'] ?? 'scopes')->cascadeOnDelete();
        });

        Schema::create($tables['tenants'] ?? 'tenants', function (Blueprint $table) use ($pk) {
            $this->primaryKey($table, $pk);
            $table->string('name');
            $table->string('slug')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('slug');
            $table->index('is_active');
        });

        Schema::create($tables['tenant_users'] ?? 'tenant_users', function (Blueprint $table) use ($pk, $userKey, $tables) {
            $this->primaryKey($table, $pk);
            $this->foreignKey($table, $pk, 'tenant_id');
            $table->string('user_type');
            $this->userKey($table, $userKey, 'user_id');
            $table->timestamps();

            $table->unique(['tenant_id', 'user_type', 'user_id'], 'tenant_users_unique');
            $table->index(['user_type', 'user_id']);

            $table->foreign('tenant_id')->references('id')->on($tables['tenants'] ?? 'tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        Schema::dropIfExists($tables['tenant_users'] ?? 'tenant_users');
        Schema::dropIfExists($tables['tenants'] ?? 'tenants');
        Schema::dropIfExists($tables['user_scopes'] ?? 'user_scopes');
        Schema::dropIfExists($tables['permission_scopes'] ?? 'permission_scopes');
        Schema::dropIfExists($tables['role_scopes'] ?? 'role_scopes');
        Schema::dropIfExists($tables['scopes'] ?? 'scopes');
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
