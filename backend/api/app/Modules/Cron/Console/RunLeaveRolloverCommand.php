<?php

declare(strict_types=1);

namespace App\Modules\Cron\Console;

use App\Modules\Cron\Services\RunLeaveRollover;
use Illuminate\Console\Command;

/**
 * The command-line face of the same job the cron URL runs.
 *
 * Both exist because the crontab currently calls the URL. Nothing lives in
 * either wrapper, so moving the server to the scheduler is a crontab change.
 *
 * --force is what makes a missed 1 January recoverable: the run is idempotent,
 * so re-running it the next morning produces the same balances it would have
 * produced at midnight.
 */
final class RunLeaveRolloverCommand extends Command
{
    protected $signature = 'permedjat:run-leave-rollover
        {--force : Run regardless of the date}
        {--tenant= : Restrict the run to one company}';

    protected $description = 'Carries annual leave balances into the new year for every company that opted in.';

    public function handle(RunLeaveRollover $job): int
    {
        $tenant = $this->option('tenant');

        $report = $job->execute(
            (bool) $this->option('force'),
            $tenant === null ? null : (int) $tenant,
        );

        $this->line((string) json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
