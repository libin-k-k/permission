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

        Schema::create($tables['role_inheritances'] ?? 'role_inheritances', function (Blueprint $table) use ($pk, $tables) {
            $this->primaryKey($table, $pk);
            $this->foreignKey($table, $pk, 'parent_role_id');
            $this->foreignKey($table, $pk, 'child_role_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['parent_role_id', 'child_role_id'], 'role_inheritances_unique');
            $table->index('child_role_id');

            $table->foreign('parent_role_id')
                ->references('id')
                ->on($tables['roles'])
                ->cascadeOnDelete();
            $table->foreign('child_role_id')
                ->references('id')
                ->on($tables['roles'])
                ->cascadeOnDelete();
        });

        Schema::create($tables['permission_conditions'] ?? 'permission_conditions', function (Blueprint $table) use ($pk, $tables) {
            $this->primaryKey($table, $pk);
            $this->foreignKey($table, $pk, 'permission_id');
            $table->string('name');
            $table->string('type')->default('custom');
            $table->string('operator')->nullable();
            $table->text('value')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['permission_id', 'is_active']);
            $table->index('name');
            $table->index('type');

            $table->foreign('permission_id')
                ->references('id')
                ->on($tables['permissions'])
                ->cascadeOnDelete();
        });

        Schema::create($tables['permission_condition_values'] ?? 'permission_condition_values', function (Blueprint $table) use ($pk, $tables) {
            $this->primaryKey($table, $pk);
            $this->foreignKey($table, $pk, 'condition_id');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('value_type')->default('string');
            $table->timestamps();

            $table->unique(['condition_id', 'key'], 'permission_condition_values_unique');

            $table->foreign('condition_id')
                ->references('id')
                ->on($tables['permission_conditions'] ?? 'permission_conditions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        Schema::dropIfExists($tables['permission_condition_values'] ?? 'permission_condition_values');
        Schema::dropIfExists($tables['permission_conditions'] ?? 'permission_conditions');
        Schema::dropIfExists($tables['role_inheritances'] ?? 'role_inheritances');
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
};
