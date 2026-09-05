<?php

declare(strict_types=1);

namespace App\Shared\Approvals;

use Illuminate\Support\Facades\DB;

/**
 * Multi-step approval — retired.
 *
 * The chain tables (`approval_chains`, `approval_chain_steps`,
 * `approval_request_steps`) were dropped on 2026-09-05: no endpoint any client
 * calls could ever create a chain, so no tenant ever had one and routing always
 * fell through to the single-approver path below. That fall-through was the
 * only behaviour anyone ever saw, and it is now the only behaviour there is.
 *
 * The class stays because callers depend on its shape: a request waits for
 * whoever holds the permission, exactly as before. If multi-step approval is
 * wanted again, build it against a schema fitted to the requirement then —
 * do not resurrect these tables.
 */
final class ApprovalRouter
{
    public const ENTITY_TYPES = ['leave', 'loan', 'bonus', 'warning', 'document', 'generic'];

    /**
     * Always null: there are no chains to route to.
     *
     * The signature is unchanged so callers keep reading as intent ("route this
     * if the company configured a chain") rather than needing to know it never
     * does.
     *
     * @return int|null The request id, or null when nothing was routed.
     */
    public function route(
        int $tenantId,
        string $entityType,
        int $entityId,
        ?float $amount = null,
        ?int $branchId = null,
        ?int $byAdminId = null,
        ?int $byEmployeeId = null,
    ): ?int {
        return null;
    }

    public function isPending(int $tenantId, string $entityType, int $entityId): bool
    {
        return DB::table('approval_requests')
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Close any open chain for this entity.
     *
     * Called when somebody with the permission decides directly from the
     * management screen: that decision stands, and leaving the chain open would
     * park the request in an approver's inbox forever.
     */
    public function cancelFor(int $tenantId, string $entityType, int $entityId): void
    {
        DB::table('approval_requests')
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled', 'decided_at' => DB::raw('NOW()')]);
    }

}
