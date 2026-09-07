<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the last two tables held back from the 000100 cleanup.
 *
 * Both differ from the chains and the review cycles in one way that mattered
 * enough to hold them back: the code reading them is live, not disabled. It
 * still resolves to nothing, because both tables are empty in every tenant and
 * neither has a writer — no endpoint any client calls can create a holiday or a
 * bonus rule, and none ever could.
 *
 *   `holidays`     read by AttendanceCalendar, which turned a matching date into
 *                  a 'holiday' day, and by AbsenceBackfill, which skipped one.
 *                  With no rows, neither branch was ever taken.
 *
 *   `bonus_rules`  read by PayrollCalculator for one key, `overtime_multiplier`,
 *                  and by the rules panel on the financial tab. With no rows the
 *                  calculator took its 1.5 default, which is therefore the only
 *                  overtime rate any tenant has ever been paid.
 *
 * The code changed first, in the same commit, so nothing queries a table that is
 * about to go: the two holiday lookups are gone, and the multiplier is now
 * PayrollCalculator::OVERTIME_MULTIPLIER — the same 1.5, as a constant.
 *
 * Two things this deliberately does not touch:
 *   - The 'holiday' attendance status. It is a separate feature: a value in the
 *     `attendance.status` enum that an administrator sets for one employee-day
 *     through POST /v1/attendance/day-status. Those rows are real, the clients
 *     render them, and none of that goes through this table.
 *   - `deduction_rules`, which looks like `bonus_rules` and is not: it has a
 *     controller, a route, a permission, and rows in production.
 *
 * `down()` restores the shape, which costs nothing here because there are no
 * contents. `holidays` comes back without `updated_at`: 000200 dropped that
 * column and re-adds it on the way back, and a reverse-order rollback runs this
 * one first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::drop('holidays');
        Schema::drop('bonus_rules');
    }

    public function down(): void
    {
        // Bodies copied verbatim from the original create migrations, less the
        // `holidays.updated_at` that 000200 owns.
        Schema::create('bonus_rules', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->string('rule_key', 50);
            $table->enum('rule_type', ['numeric', 'text', 'boolean'])->default('numeric');
            $table->string('rule_value', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(1);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->unique(['tenant_id', 'rule_key'], 'uniq_bonus_rule');
        });

        Schema::create('holidays', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->integer('branch_id')->unsigned()->nullable()->comment('Null = all branches');
            $table->string('name', 100);
            $table->date('date');
            $table->text('notes')->nullable();
            $table->integer('created_by')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['branch_id'], 'branch_id');
            $table->index(['created_by'], 'created_by');
            $table->index(['date'], 'idx_holiday_date');
            $table->unique(['tenant_id', 'branch_id', 'date'], 'uniq_holiday_branch_date');
        });

        Schema::table('bonus_rules', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'bonus_rules_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
        });
        Schema::table('holidays', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'holidays_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['branch_id'], 'holidays_ibfk_2')->references(['id'])->on('branches')->cascadeOnDelete();
            $table->foreign(['created_by'], 'holidays_ibfk_3')->references(['id'])->on('admins')->nullOnDelete();
        });
    }
};
