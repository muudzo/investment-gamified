<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Relax the portfolio_audit immutability model so retention can function.
 *
 * The original migration installed BOTH an UPDATE-blocking and a DELETE-blocking
 * trigger. That made the documented retention command (audit:clean) impossible to
 * run — it could never delete anything. This migration resolves that contradiction:
 *
 *   - UPDATE remains blocked (content is immutable / tamper-proof — no edits, ever).
 *   - DELETE is allowed, so the scheduled retention job can purge rows past the
 *     retention window. Application code still cannot delete individual records via
 *     the Eloquent model (PortfolioAudit::delete() throws); only the query-builder
 *     retention path can.
 *
 * This is a common WORM-with-retention design for audit ledgers.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS portfolio_audit_no_delete');
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS portfolio_audit_no_delete');
        } elseif ($driver === 'pgsql' || $driver === 'postgres') {
            DB::unprepared('DROP TRIGGER IF EXISTS portfolio_audit_no_delete ON portfolio_audit');
            DB::unprepared('DROP FUNCTION IF EXISTS portfolio_audit_no_delete()');
        }
    }

    public function down(): void
    {
        $driver = DB::getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS portfolio_audit_no_delete');
            DB::unprepared("CREATE TRIGGER portfolio_audit_no_delete BEFORE DELETE ON portfolio_audit FOR EACH ROW BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'portfolio_audit is immutable'; END;");
        } elseif ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER IF NOT EXISTS portfolio_audit_no_delete BEFORE DELETE ON portfolio_audit BEGIN SELECT RAISE(ABORT, 'portfolio_audit is immutable'); END;");
        } elseif ($driver === 'pgsql' || $driver === 'postgres') {
            DB::unprepared("CREATE OR REPLACE FUNCTION portfolio_audit_no_delete() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'portfolio_audit is immutable'; END; $$ LANGUAGE plpgsql;");
            DB::unprepared('CREATE TRIGGER portfolio_audit_no_delete BEFORE DELETE ON portfolio_audit FOR EACH ROW EXECUTE PROCEDURE portfolio_audit_no_delete();');
        }
    }
};
