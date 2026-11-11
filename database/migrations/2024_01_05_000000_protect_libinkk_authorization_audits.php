<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('permission.table_names.authorization_audits', 'authorization_audits');

        if (! Schema::hasTable($table)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'sqlite' => $this->sqliteTriggers($table),
            'mysql', 'mariadb' => $this->mysqlTriggers($table),
            'pgsql' => $this->pgsqlTriggers($table),
            default => null,
        };
    }

    public function down(): void
    {
        $table = config('permission.table_names.authorization_audits', 'authorization_audits');
        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'sqlite' => $this->dropSqliteTriggers($table),
            'mysql', 'mariadb' => $this->dropMysqlTriggers($table),
            'pgsql' => $this->dropPgsqlTriggers($table),
            default => null,
        };
    }

    private function sqliteTriggers(string $table): void
    {
        DB::unprepared("
            CREATE TRIGGER IF NOT EXISTS libinkk_authorization_audits_no_update
            BEFORE UPDATE ON \"{$table}\"
            BEGIN
                SELECT RAISE(ABORT, 'authorization_audits rows are append-only');
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER IF NOT EXISTS libinkk_authorization_audits_no_delete
            BEFORE DELETE ON \"{$table}\"
            BEGIN
                SELECT RAISE(ABORT, 'authorization_audits rows are append-only');
            END;
        ");
    }

    private function dropSqliteTriggers(string $table): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS libinkk_authorization_audits_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS libinkk_authorization_audits_no_delete');
    }

    private function mysqlTriggers(string $table): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS libinkk_authorization_audits_no_update");
        DB::unprepared("DROP TRIGGER IF EXISTS libinkk_authorization_audits_no_delete");

        DB::unprepared("
            CREATE TRIGGER libinkk_authorization_audits_no_update
            BEFORE UPDATE ON `{$table}`
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'authorization_audits rows are append-only'
        ");

        DB::unprepared("
            CREATE TRIGGER libinkk_authorization_audits_no_delete
            BEFORE DELETE ON `{$table}`
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'authorization_audits rows are append-only'
        ");
    }

    private function dropMysqlTriggers(string $table): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS libinkk_authorization_audits_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS libinkk_authorization_audits_no_delete');
    }

    private function pgsqlTriggers(string $table): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION libinkk_authorization_audits_immutable()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'authorization_audits rows are append-only';
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::unprepared("DROP TRIGGER IF EXISTS libinkk_authorization_audits_no_update ON \"{$table}\"");
        DB::unprepared("DROP TRIGGER IF EXISTS libinkk_authorization_audits_no_delete ON \"{$table}\"");

        DB::unprepared("
            CREATE TRIGGER libinkk_authorization_audits_no_update
            BEFORE UPDATE ON \"{$table}\"
            FOR EACH ROW EXECUTE PROCEDURE libinkk_authorization_audits_immutable()
        ");

        DB::unprepared("
            CREATE TRIGGER libinkk_authorization_audits_no_delete
            BEFORE DELETE ON \"{$table}\"
            FOR EACH ROW EXECUTE PROCEDURE libinkk_authorization_audits_immutable()
        ");
    }

    private function dropPgsqlTriggers(string $table): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS libinkk_authorization_audits_no_update ON \"{$table}\"");
        DB::unprepared("DROP TRIGGER IF EXISTS libinkk_authorization_audits_no_delete ON \"{$table}\"");
        DB::unprepared('DROP FUNCTION IF EXISTS libinkk_authorization_audits_immutable()');
    }
};
