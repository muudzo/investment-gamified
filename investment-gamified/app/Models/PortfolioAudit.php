<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Portfolio Audit (Immutable Ledger)
 *
 * IMMUTABILITY CONTRACT:
 * This model represents an append-only, immutable transaction ledger.
 * Once created, audit records MUST NOT be updated, deleted, or modified by application code.
 *
 * WHY IMMUTABLE?
 * - Prevents accidental data corruption and cover-up of trading errors
 * - Enables forensic analysis of user disputes (refund requests, balance mismatches)
 * - Simplifies compliance audits and regulatory investigations
 * - Guarantees historical data integrity
 *
 * RETENTION POLICY:
 * - Operational default: 730 days (2 years) enforced by 'audit:clean' command
 * - NOT automatically scheduled; admins must configure in production
 * - Compliance requirement override: See CleanOldAudits.php for regulated alternatives
 *
 * TECHNICAL SAFEGUARDS:
 * - Application-level guards: update() and delete() throw exceptions
 * - Mass updates via query builder are blocked
 * - DB-level triggers can be added (optional) for defense-in-depth
 * - Archived partitions: For regulated use, DROP old partitions instead of DELETE rows
 *
 * See: PRODUCTION_SCALE_FIXES_GUIDE.md "Audit Retention vs Compliance Guardrail"
 *      documentation/REFACTOR_02_AUDIT_LEDGER.md
 */
class PortfolioAudit extends Model
{
    use HasFactory;

    protected $table = 'portfolio_audit';

    protected $guarded = [];

    // The table has only a created_at column (populated via useCurrent()).
    // Disabling updated_at prevents Eloquent from writing a non-existent column.
    public $timestamps = true;

    const UPDATED_AT = null;

    // Immutable ledger: prevent accidental updates/deletes at application level.
    public function update(array $attributes = [], array $options = [])
    {
        throw new \Exception('PortfolioAudit is immutable and cannot be updated');
    }

    public function delete()
    {
        throw new \Exception('PortfolioAudit is immutable and cannot be deleted');
    }

    public static function query()
    {
        $query = parent::query();
        // Block mass updates through query builder on this model
        $query->macro('update', function () {
            throw new \Exception('Mass updates are not allowed on PortfolioAudit');
        });

        return $query;
    }
}
