<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes schema that no client can reach.
 *
 * Every table and column here was checked against one rule: does a route in
 * `routes/api.php` that some frontend actually calls read or write it? Not "is
 * it mentioned in the backend" — the employee app, Central, Console, Kiosk and
 * the web port were each walked from their endpoint constants through to the
 * method that issues the request, because a constant nobody calls is not a
 * caller. 273 of 296 routes survived that test; what follows is the schema left
 * behind by the 23 that did not, plus columns no surviving route touches.
 *
 * `down()` restores the shape, never the contents. Three of these carried rows
 * — 55 in onboarding_tasks, 20 in onboarding_templates, and real day counts in
 * three payroll rows — and rolling back leaves those empty. The pre-cleanup dump
 * is the only way back to the data:
 *   /root/db_backups/permedjat_pre_cleanup_2026-09-05_0733.sql.gz
 *
 * Held back deliberately, though they meet the same rule: bonus_rules, holidays,
 * approval_chains, approval_chain_steps and performance_cycles are pending a
 * separate decision, and device_commands.result_code / completed_at are written
 * by DeviceCommands on every terminal reply through IclockController — dropping
 * those two would break the punch terminals at the first command result.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Children before parents, so no drop trips a foreign key.
        Schema::table('onboarding_tasks', function (Blueprint $table): void {
            $table->dropForeign('onboarding_tasks_ibfk_3');
        });
        Schema::drop('onboarding_tasks');
        Schema::drop('onboarding_templates');

        Schema::table('open_shift_claims', function (Blueprint $table): void {
            $table->dropForeign('open_shift_claims_ibfk_2');
        });
        Schema::drop('open_shift_claims');
        Schema::drop('open_shifts');

        Schema::drop('performance_goals');
        Schema::drop('employee_availability');

        // candidates.job_opening_id is the only thing pinning job_openings down.
        Schema::table('candidates', function (Blueprint $table): void {
            $table->dropForeign('candidates_ibfk_2');
        });
        Schema::drop('candidates');
        Schema::drop('job_openings');

        Schema::drop('device_protocol_logs');

        // Superseded by employee_categories; null in all 16 rows.
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn(['employee_code', 'department']);
        });

        // 183 attempts recorded, reason blank on every one.
        Schema::table('login_attempts', function (Blueprint $table): void {
            $table->dropColumn('failure_reason');
        });

        // Replaced by kiosk_checkin_idem_key / kiosk_checkout_idem_key.
        Schema::table('attendance', function (Blueprint $table): void {
            $table->dropUnique('uniq_att_kiosk_idem');
            $table->dropColumn('kiosk_idempotency_key');
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropColumn(['assigned_super_admin_id', 'updated_at']);
        });

        Schema::table('asset_custody', function (Blueprint $table): void {
            $table->dropColumn('return_photo_url');
        });

        // Not employee_categories.color, which company settings still reads.
        Schema::table('shifts', function (Blueprint $table): void {
            $table->dropColumn(['color', 'updated_at']);
        });

        Schema::table('payroll', function (Blueprint $table): void {
            $table->dropColumn(['present_days', 'absent_days']);
        });

        // Columns on tables that hold no rows at all.
        Schema::table('performance_reviews', function (Blueprint $t): void {
            $t->dropColumn('updated_at');
        });
        Schema::table('leave_year_balances', function (Blueprint $t): void {
            $t->dropColumn('carryover_expires_on');
        });
        Schema::table('device_punches', function (Blueprint $t): void {
            $t->dropColumn('received_at');
        });
        Schema::table('device_users', function (Blueprint $t): void {
            $t->dropColumn('updated_at');
        });
        Schema::table('branch_qr_uses', function (Blueprint $t): void {
            $t->dropColumn('used_at');
        });
        Schema::table('branch_qr_challenges', function (Blueprint $t): void {
            $t->dropColumn('created_at');
        });
        Schema::table('employee_shift_schedule', function (Blueprint $t): void {
            $t->dropColumn('updated_at');
        });
        Schema::table('employee_web_credentials', function (Blueprint $t): void {
            $t->dropColumn('created_at');
        });
        Schema::table('kiosk_auth_tokens', function (Blueprint $t): void {
            $t->dropColumn('issued_at');
        });
        Schema::table('super_admin_devices', function (Blueprint $t): void {
            $t->dropColumn(['created_at', 'updated_at']);
        });

        // Timestamps MySQL maintained on its own and no endpoint ever read.
        Schema::table('employee_auth_tokens', function (Blueprint $t): void {
            $t->dropColumn('issued_at');
        });
        Schema::table('manager_invitations', function (Blueprint $t): void {
            $t->dropColumn('updated_at');
        });
        Schema::table('attendance_devices', function (Blueprint $t): void {
            $t->dropColumn('updated_at');
        });
        Schema::table('desktop_auth_codes', function (Blueprint $t): void {
            $t->dropColumn('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('desktop_auth_codes', function (Blueprint $table): void {
            $table->dateTime('created_at')->useCurrent();
        });
        Schema::table('attendance_devices', function (Blueprint $table): void {
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
        Schema::table('manager_invitations', function (Blueprint $table): void {
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
        Schema::table('employee_auth_tokens', function (Blueprint $table): void {
            $table->timestamp('issued_at')->nullable()->useCurrent();
        });

        Schema::table('super_admin_devices', function (Blueprint $table): void {
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
        Schema::table('kiosk_auth_tokens', function (Blueprint $table): void {
            $table->timestamp('issued_at')->nullable()->useCurrent();
        });
        Schema::table('employee_web_credentials', function (Blueprint $table): void {
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
        Schema::table('employee_shift_schedule', function (Blueprint $table): void {
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
        Schema::table('branch_qr_challenges', function (Blueprint $table): void {
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
        Schema::table('branch_qr_uses', function (Blueprint $table): void {
            $table->timestamp('used_at')->nullable()->useCurrent();
        });
        Schema::table('device_users', function (Blueprint $table): void {
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
        Schema::table('device_punches', function (Blueprint $table): void {
            $table->timestamp('received_at')->nullable()->useCurrent();
        });
        Schema::table('leave_year_balances', function (Blueprint $table): void {
            $table->date('carryover_expires_on')->nullable();
        });
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::table('payroll', function (Blueprint $table): void {
            $table->integer('present_days')->unsigned()->default(0);
            $table->integer('absent_days')->unsigned()->default(0);
        });
        Schema::table('shifts', function (Blueprint $table): void {
            $table->string('color', 7)->nullable()->comment('Hex color for UI badge');
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
        Schema::table('asset_custody', function (Blueprint $table): void {
            $table->string('return_photo_url', 512)->nullable();
        });
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->integer('assigned_super_admin_id')->unsigned()->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });
        Schema::table('attendance', function (Blueprint $table): void {
            $table->char('kiosk_idempotency_key', 36)->nullable();
            $table->unique(['kiosk_idempotency_key'], 'uniq_att_kiosk_idem');
        });
        Schema::table('login_attempts', function (Blueprint $table): void {
            $table->string('failure_reason', 100)->nullable();
        });
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('employee_code', 30)->nullable();
            $table->string('department', 100)->nullable();
        });

        // Table bodies copied verbatim from the original create migrations.
        Schema::create('device_protocol_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->integer('device_id')->unsigned()->nullable();
            $table->string('serial_number', 64)->nullable();
            $table->string('method', 8)->nullable();
            $table->string('path', 120)->nullable();
            $table->string('query_string', 500)->nullable();
            $table->text('body')->nullable();
            $table->text('response')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['created_at'], 'idx_protocol_created');
            $table->index(['device_id', 'id'], 'idx_protocol_device');
        });

        Schema::create('job_openings', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->integer('branch_id')->unsigned()->nullable();
            $table->string('title', 150);
            $table->string('department', 100)->nullable();
            $table->text('description')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'temporary'])->default('full_time');
            $table->integer('openings_count')->unsigned()->default(1);
            $table->enum('status', ['open', 'on_hold', 'closed'])->default('open');
            $table->integer('created_by')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->index(['branch_id'], 'idx_job_branch');
            $table->index(['tenant_id', 'status'], 'idx_job_tenant_status');
            $table->index(['created_by'], 'job_openings_ibfk_3');
        });

        Schema::create('candidates', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->integer('job_opening_id')->unsigned()->nullable();
            $table->string('name', 150);
            $table->string('email', 190)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('cv_url', 512)->nullable();
            $table->string('source', 80)->nullable()->comment('referral|walk_in|agency|manual...');
            $table->enum('stage', ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'])->default('applied');
            $table->decimal('expected_salary', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->integer('converted_employee_id')->unsigned()->nullable()->comment('set when stage=hired and converted');
            $table->integer('created_by')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->index(['created_by'], 'candidates_ibfk_4');
            $table->index(['converted_employee_id'], 'idx_cand_emp');
            $table->index(['job_opening_id'], 'idx_cand_job');
            $table->index(['tenant_id', 'stage'], 'idx_cand_tenant_stage');
        });

        Schema::create('employee_availability', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->integer('employee_id')->unsigned();
            $table->enum('kind', ['weekly', 'date'])->default('weekly');
            $table->tinyInteger('day_of_week')->unsigned()->nullable()->comment('0=Sun..6=Sat');
            $table->date('specific_date')->nullable()->comment('for kind=date');
            $table->enum('availability', ['available', 'preferred', 'unavailable'])->default('available');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->index(['employee_id'], 'employee_availability_ibfk_2');
            $table->index(['specific_date'], 'idx_avail_date');
            $table->index(['tenant_id', 'employee_id', 'kind'], 'idx_avail_tenant_emp');
        });

        Schema::create('performance_goals', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->integer('employee_id')->unsigned();
            $table->integer('cycle_id')->unsigned()->nullable()->comment('optional: link goal to a cycle');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('metric', 150)->nullable()->comment('measurement unit / indicator, free text');
            $table->decimal('target_value', 14, 2)->nullable();
            $table->decimal('current_value', 14, 2)->default(0.00);
            $table->tinyInteger('weight')->unsigned()->default(0)->comment('goal weight % (0-100)');
            $table->tinyInteger('progress')->unsigned()->default(0)->comment('completion % 0-100');
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'cancelled'])->default('not_started');
            $table->date('due_date')->nullable();
            $table->integer('created_by')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->index(['cycle_id'], 'idx_pgoal_cycle');
            $table->index(['tenant_id', 'employee_id', 'status'], 'idx_pgoal_tenant_emp');
            $table->index(['employee_id'], 'performance_goals_ibfk_2');
            $table->index(['created_by'], 'performance_goals_ibfk_4');
        });

        Schema::create('open_shifts', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->integer('branch_id')->unsigned()->nullable()->comment('NULL = all branches eligible');
            $table->integer('shift_id')->unsigned();
            $table->date('work_date');
            $table->tinyInteger('slots')->unsigned()->default(1);
            $table->tinyInteger('slots_filled')->unsigned()->default(0);
            $table->enum('status', ['open', 'filled', 'cancelled'])->default('open');
            $table->string('notes', 255)->nullable();
            $table->integer('created_by')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['branch_id'], 'idx_openshift_branch');
            $table->index(['tenant_id', 'status', 'work_date'], 'idx_openshift_tenant_status');
            $table->index(['shift_id'], 'open_shifts_ibfk_2');
            $table->index(['created_by'], 'open_shifts_ibfk_4');
        });

        Schema::create('open_shift_claims', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->integer('open_shift_id')->unsigned();
            $table->integer('employee_id')->unsigned();
            $table->enum('status', ['pending', 'approved', 'rejected', 'withdrawn'])->default('pending');
            $table->integer('decided_by')->unsigned()->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['employee_id'], 'idx_claim_employee');
            $table->index(['tenant_id', 'status'], 'idx_claim_tenant_status');
            $table->index(['decided_by'], 'open_shift_claims_ibfk_4');
            $table->unique(['open_shift_id', 'employee_id'], 'uniq_claim_shift_emp');
        });

        Schema::create('onboarding_templates', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->string('title', 200);
            $table->string('title_ar', 200)->nullable();
            $table->enum('task_type', ['document', 'asset', 'account', 'generic'])->default('generic');
            $table->text('description')->nullable();
            $table->integer('sort_order')->unsigned()->default(0);
            $table->boolean('is_active')->default(1);
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['tenant_id', 'is_active', 'sort_order'], 'idx_onbtpl_tenant');
        });

        Schema::create('onboarding_tasks', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->integer('employee_id')->unsigned();
            $table->integer('template_id')->unsigned()->nullable()->comment('source template row, NULL for manually added');
            $table->string('title', 200);
            $table->enum('task_type', ['document', 'asset', 'account', 'generic'])->default('generic');
            $table->enum('status', ['pending', 'completed', 'skipped'])->default('pending');
            $table->integer('sort_order')->unsigned()->default(0);
            $table->integer('completed_by')->unsigned()->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['tenant_id', 'employee_id', 'status'], 'idx_onbtask_tenant_emp');
            $table->index(['employee_id'], 'onboarding_tasks_ibfk_2');
            $table->index(['template_id'], 'onboarding_tasks_ibfk_3');
            $table->index(['completed_by'], 'onboarding_tasks_ibfk_4');
        });

        // Foreign keys, matching 2026_01_01_999999_add_foreign_keys.php.
        Schema::table('job_openings', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'job_openings_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['branch_id'], 'job_openings_ibfk_2')->references(['id'])->on('branches')->nullOnDelete();
            $table->foreign(['created_by'], 'job_openings_ibfk_3')->references(['id'])->on('admins')->nullOnDelete();
        });
        Schema::table('candidates', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'candidates_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['job_opening_id'], 'candidates_ibfk_2')->references(['id'])->on('job_openings')->nullOnDelete();
            $table->foreign(['converted_employee_id'], 'candidates_ibfk_3')->references(['id'])->on('employees')->nullOnDelete();
            $table->foreign(['created_by'], 'candidates_ibfk_4')->references(['id'])->on('admins')->nullOnDelete();
        });
        Schema::table('employee_availability', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'employee_availability_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['employee_id'], 'employee_availability_ibfk_2')->references(['id'])->on('employees')->cascadeOnDelete();
        });
        Schema::table('performance_goals', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'performance_goals_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['employee_id'], 'performance_goals_ibfk_2')->references(['id'])->on('employees')->cascadeOnDelete();
            $table->foreign(['cycle_id'], 'performance_goals_ibfk_3')->references(['id'])->on('performance_cycles')->nullOnDelete();
            $table->foreign(['created_by'], 'performance_goals_ibfk_4')->references(['id'])->on('admins')->nullOnDelete();
        });
        Schema::table('open_shifts', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'open_shifts_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['shift_id'], 'open_shifts_ibfk_2')->references(['id'])->on('shifts')->cascadeOnDelete();
            $table->foreign(['branch_id'], 'open_shifts_ibfk_3')->references(['id'])->on('branches')->cascadeOnDelete();
            $table->foreign(['created_by'], 'open_shifts_ibfk_4')->references(['id'])->on('admins')->nullOnDelete();
        });
        Schema::table('open_shift_claims', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'open_shift_claims_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['open_shift_id'], 'open_shift_claims_ibfk_2')->references(['id'])->on('open_shifts')->cascadeOnDelete();
            $table->foreign(['employee_id'], 'open_shift_claims_ibfk_3')->references(['id'])->on('employees')->cascadeOnDelete();
            $table->foreign(['decided_by'], 'open_shift_claims_ibfk_4')->references(['id'])->on('admins')->nullOnDelete();
        });
        Schema::table('onboarding_templates', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'onboarding_templates_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
        });
        Schema::table('onboarding_tasks', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'onboarding_tasks_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['employee_id'], 'onboarding_tasks_ibfk_2')->references(['id'])->on('employees')->cascadeOnDelete();
            $table->foreign(['template_id'], 'onboarding_tasks_ibfk_3')->references(['id'])->on('onboarding_templates')->nullOnDelete();
            $table->foreign(['completed_by'], 'onboarding_tasks_ibfk_4')->references(['id'])->on('admins')->nullOnDelete();
        });
    }
};
