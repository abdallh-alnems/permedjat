<?php

declare(strict_types=1);

namespace Tests\Feature\Cron;

use App\Modules\Cron\Services\RunLeaveRollover;
use App\Shared\Time\TenantClock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesFixtures;
use Tests\TestCase;

/**
 * The year-end leave rollover, as a scheduled job.
 *
 * The old backend ran this from the crontab and this application did not: the
 * service that closes a leave year existed, but only an authenticated admin
 * endpoint could reach it. Retiring the old backend would therefore have
 * removed automatic rollover silently — nothing would fail until 1 January.
 */
final class LeaveRolloverCronTest extends TestCase
{
    use CreatesFixtures;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantClock::flush();
    }

    public function test_a_company_that_did_not_opt_in_is_left_alone(): void
    {
        $this->createTenant(['auto_rollover_enabled' => 0]);

        $report = app(RunLeaveRollover::class)->execute(force: true);

        $this->assertSame(0, $report['totals']['tenants']);
    }

    public function test_an_opted_in_company_is_rolled_over_when_forced(): void
    {
        $tenantId = $this->createTenant(['auto_rollover_enabled' => 1]);

        $report = app(RunLeaveRollover::class)->execute(force: true);

        $this->assertSame(1, $report['totals']['tenants']);
        $this->assertArrayHasKey($tenantId, $report['by_tenant']);
        $this->assertArrayNotHasKey('error', $report['by_tenant'][$tenantId]);
        $this->assertSame(
            (int) TenantClock::now($tenantId)->format('Y') - 1,
            $report['by_tenant'][$tenantId]['from_year'],
        );
    }

    public function test_only_the_named_company_runs_when_one_is_named(): void
    {
        $wanted = $this->createTenant(['auto_rollover_enabled' => 1]);
        $other = $this->createTenant(['auto_rollover_enabled' => 1]);

        $report = app(RunLeaveRollover::class)->execute(force: true, onlyTenant: $wanted);

        $this->assertArrayHasKey($wanted, $report['by_tenant']);
        $this->assertArrayNotHasKey($other, $report['by_tenant']);
    }

    /**
     * The guard that lets this run nightly. Without it the job would roll every
     * company's balances every single night.
     */
    public function test_nothing_runs_on_an_ordinary_day(): void
    {
        $this->createTenant(['auto_rollover_enabled' => 1]);

        $report = app(RunLeaveRollover::class)->execute();

        // The suite does not run on 1 January; on that one day a year this
        // asserts the opposite, so it is skipped rather than made flaky.
        if (date('m-d') === '01-01') {
            $this->markTestSkipped('It is 1 January — the guard is meant to open today.');
        }

        $this->assertSame(0, $report['totals']['tenants']);
    }

    /**
     * The date is each company's own, not the server's. Two companies whose
     * zones straddle midnight on 31 December must not roll on the same night.
     */
    public function test_the_new_year_is_decided_in_the_company_timezone(): void
    {
        $cairo = $this->createTenant(['auto_rollover_enabled' => 1, 'timezone' => 'Africa/Cairo']);
        $apia = $this->createTenant(['auto_rollover_enabled' => 1, 'timezone' => 'Pacific/Apia']);

        $this->assertNotSame(
            TenantClock::now($cairo)->format('Y-m-d H'),
            TenantClock::now($apia)->format('Y-m-d H'),
            'the two zones must differ, or this test proves nothing',
        );

        $report = app(RunLeaveRollover::class)->execute(force: true);

        // Forced, so both run — what matters is that each was asked about its
        // own year rather than the server's.
        foreach ([$cairo, $apia] as $tenantId) {
            $this->assertSame(
                (int) TenantClock::now($tenantId)->format('Y') - 1,
                $report['by_tenant'][$tenantId]['from_year'],
            );
        }
    }

    public function test_one_company_failing_does_not_stop_the_others(): void
    {
        $broken = $this->createTenant(['auto_rollover_enabled' => 1]);
        $healthy = $this->createTenant(['auto_rollover_enabled' => 1]);

        // A tenant row the rollover cannot resolve a policy for: deleting it
        // after the id list is read is not reproducible, so break the timezone
        // instead, which TenantClock reads first.
        DB::table('tenants')->where('id', $broken)->update(['timezone' => 'Not/AZone']);

        $report = app(RunLeaveRollover::class)->execute(force: true);

        $this->assertArrayHasKey($healthy, $report['by_tenant']);
        $this->assertArrayNotHasKey('error', $report['by_tenant'][$healthy]);
    }
}
