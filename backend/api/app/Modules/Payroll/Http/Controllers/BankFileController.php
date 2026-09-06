<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Controllers;

use App\Exceptions\ApiFailure;
use App\Modules\Payroll\Domain\Export\BankExporterRegistry;
use App\Modules\Payroll\Domain\PayrollLedger;
use App\Shared\Http\ApiResponse;
use App\Support\Value;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Port of api/app/payroll/bank_file_preview.php and export_bank_file.php.
 *
 * Only approved slips are payable, and only those with somewhere to pay to. The
 * preview exists so nobody discovers the second half at the bank: it names the
 * people with no account on file rather than quietly dropping them from the
 * transfer.
 */
final class BankFileController
{
    public function __construct(private readonly PayrollLedger $ledger) {}

    public function preview(Request $request): JsonResponse
    {
        $tenantId = Value::int($request->attributes->get('tenant_id'));
        $month = self::month($request);
        $branchId = Value::int($request->query('branch_id')) ?: null;

        $rows = $this->ledger->approvedForBankFile($tenantId, $month, $branchId);
        [$ready, $missing] = self::split($rows);

        $total = 0.0;
        foreach ($ready as $row) {
            $total += Value::float($row['net_salary'] ?? null);
        }

        return ApiResponse::success([
            'month' => $month,
            'total_employees' => count($rows),
            'total_amount' => round($total, 2),
            'ready_count' => count($ready),
            'missing_bank_count' => count($missing),
            'missing' => $missing,
            'available_exporters' => BankExporterRegistry::availableFor(self::tenant($tenantId)),
        ]);
    }

    /**
     * Payable rows and unpayable people, kept apart.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array<string, mixed>>, 1: list<array{id: mixed, name: mixed}>}
     */
    private static function split(array $rows): array
    {
        $ready = [];
        $missing = [];

        foreach ($rows as $row) {
            $account = Value::string($row['bank_account_number'] ?? null);
            $iban = Value::string($row['bank_iban'] ?? null);

            if ($account !== '' || $iban !== '') {
                $ready[] = $row;
            } else {
                $missing[] = ['id' => $row['employee_id'] ?? null, 'name' => $row['employee_name'] ?? null];
            }
        }

        return [$ready, $missing];
    }

    /**
     * @return array<string, mixed>
     */
    private static function tenant(int $tenantId): array
    {
        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        if ($tenant === null) {
            throw new ApiFailure(__('messages.tenant_not_found'), 404, 'not_found');
        }

        /** @var array<string, mixed> $columns */
        $columns = (array) $tenant;

        return $columns;
    }

    private static function month(Request $request): string
    {
        $month = Value::string($request->query('month'));

        if ($month === '') {
            throw new ApiFailure('month is required', 422, 'month_required');
        }

        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            throw new ApiFailure('Invalid month format. Use YYYY-MM', 400, 'invalid_month_format_yyyy_mm');
        }

        return $month;
    }
}
