<?php

declare(strict_types=1);

namespace App\Modules\Cron\Services;

use App\Modules\Leave\Services\YearRollover;
use App\Shared\Time\TenantClock;
use App\Support\Value;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Port of scripts/cron_leave_rollover.php.
 *
 * The old backend ran this from the crontab twice a night; nothing in this
 * application did. The port has a service that closes a leave year
 * (YearRollover), but it was reachable only from an authenticated admin
 * endpoint — so retiring the old backend would have removed automatic rollover
 * without removing anything that looked like it did the job. The gap is
 * invisible for eleven months and then, on 1 January, no company's balances
 * roll.
 *
 * The date check is per tenant, through TenantClock, rather than once against
 * the server. Companies in Casablanca and Dubai do not enter the new year in
 * the same hour, and a single server-time "is it 1 January" test rolls one of
 * them a day early or a day late — which for a leave balance is a real number
 * somebody is owed.
 *
 * Idempotent: YearRollover upserts on unique keys, so a re-run refreshes the
 * same figures rather than doubling them. That is what makes it safe to run
 * twice a night, and to re-run a day that was missed.
 */
final class RunLeaveRollover
{
    public function __construct(private readonly YearRollover $rollover) {}

    /**
     * @param  bool  $force  run regardless of the date, for a missed day or a test
     * @param  int|null  $onlyTenant  restrict the run to one company
     * @return array{status: string, totals: array{tenants: int, carried: int, encashed: int}, by_tenant: array<int, array<string, mixed>>}
     */
    public function execute(bool $force = false, ?int $onlyTenant = null): array
    {
        $report = [];
        $totals = ['tenants' => 0, 'carried' => 0, 'encashed' => 0];

        $tenants = DB::table('tenants')
            ->where('is_active', 1)
            ->where('auto_rollover_enabled', 1)
            ->when(
                $onlyTenant !== null,
                fn (QueryBuilder $q): QueryBuilder => $q->where('id', $onlyTenant),
            )
            ->pluck('id');

        foreach ($tenants as $id) {
            $tenantId = Value::int($id);

            try {
                $today = TenantClock::now($tenantId);

                if (! $force && $today->format('m-d') !== '01-01') {
                    continue;
                }

                // The year being closed is the one that has just ended.
                $result = $this->rollover->run($tenantId, ((int) $today->format('Y')) - 1);
            } catch (Throwable $e) {
                // One company's failure must not stop the rest of the run.
                Log::warning('Leave rollover failed', ['tenant_id' => $tenantId, 'exception' => $e]);
                $report[$tenantId] = ['error' => $e->getMessage()];

                continue;
            }

            $report[$tenantId] = $result;
            $totals['tenants']++;
            $totals['carried'] += $result['total_carried'];
            $totals['encashed'] += $result['total_encashed'];
        }

        return ['status' => 'ok', 'totals' => $totals, 'by_tenant' => $report];
    }
}
