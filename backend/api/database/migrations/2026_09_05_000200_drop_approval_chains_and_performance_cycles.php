<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retires multi-step approval and performance review cycles.
 *
 * Both were half-built: the engine that read them ran on every leave request,
 * but no endpoint any client calls could create a chain or a cycle, so both
 * tables stayed empty in every tenant and the code always took the fall-through
 * path. Held back from the 000100 cleanup pending a decision; the decision is
 * to remove them rather than keep the schema on standby.
 *
 * The code that read these tables was changed first, in the same commit:
 * ApprovalRouter::route() now returns null without a query, and
 * PerformanceReviews::cycleExists() is gone — otherwise the first leave request
 * after this migration would hit a missing table and 500.
 *
 * Two things deliberately survive:
 *   - `approval_requests` keeps its rows and its API. With no chains left it can
 *     never gain a new one, so it is now an empty table with no writer. It was
 *     not on the approved list; drop it in a later pass if that is wanted.
 *   - `performance_reviews.cycle_id` stays as a nullable column that can only
 *     ever be null. Harmless, and dropping it was not approved.
 *
 * A separate file from 000100 on purpose: that batch is its own reviewed unit,
 * and keeping them apart means this can be rolled back without reverting it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // approval_requests survives, so its pointer at the chains must go first.
        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->dropForeign('approval_requests_ibfk_2');
        });

        Schema::drop('approval_request_steps');
        Schema::drop('approval_chain_steps');
        Schema::drop('approval_chains');

        // performance_goals also points here, but 000100 drops that table first.
        // Guarded so this migration stands on its own if that order ever changes.
        if (Schema::hasTable('performance_goals')) {
            Schema::table('performance_goals', function (Blueprint $table): void {
                $table->dropForeign('performance_goals_ibfk_3');
            });
        }

        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->dropForeign('performance_reviews_ibfk_3');
        });

        Schema::drop('performance_cycles');

        Schema::table('holidays', function (Blueprint $table): void {
            $table->dropColumn('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table): void {
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        // Table bodies copied verbatim from the original create migrations.
        Schema::create('performance_cycles', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->enum('period_type', ['monthly', 'quarterly', 'semi_annual', 'annual', 'custom'])->default('quarterly');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->integer('created_by')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->index(['tenant_id', 'status'], 'idx_pcycle_tenant_status');
            $table->index(['created_by'], 'performance_cycles_ibfk_2');
        });

        Schema::create('approval_chains', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->string('request_type', 40)->comment('leave|expense|loan|bonus|warning|document|generic');
            $table->boolean('is_active')->default(1);
            $table->decimal('min_amount', 14, 2)->nullable()->comment('Condition: context amount >= this (NULL=no min)');
            $table->integer('branch_id')->unsigned()->nullable()->comment('Condition: request branch = this (NULL=all branches)');
            $table->integer('priority')->default(0)->comment('Higher wins when multiple chains match');
            $table->integer('created_by')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            $table->index(['created_by'], 'approval_chains_ibfk_3');
            $table->index(['branch_id'], 'idx_chain_branch');
            $table->index(['tenant_id', 'request_type', 'is_active'], 'idx_chain_tenant_type_active');
        });

        Schema::create('approval_chain_steps', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->integer('chain_id')->unsigned();
            $table->tinyInteger('step_order')->unsigned()->comment('Starts at 1, sequential');
            $table->enum('approver_type', ['role', 'admin'])->default('role');
            $table->string('approver_role', 40)->nullable()->comment('When approver_type=role');
            $table->integer('approver_admin_id')->unsigned()->nullable()->comment('When approver_type=admin');
            $table->string('label', 120)->nullable()->comment('Descriptive name for the step');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['approver_admin_id'], 'idx_step_admin');
            $table->index(['tenant_id'], 'idx_step_tenant');
            $table->unique(['chain_id', 'step_order'], 'uniq_chain_step_order');
        });

        Schema::create('approval_request_steps', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->integer('request_id')->unsigned();
            $table->tinyInteger('step_order')->unsigned();
            $table->enum('approver_type', ['role', 'admin']);
            $table->string('approver_role', 40)->nullable();
            $table->integer('approver_admin_id')->unsigned()->nullable();
            $table->string('label', 120)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'skipped'])->default('pending');
            $table->integer('decided_by')->unsigned()->nullable()->comment('admins.id who decided this step');
            $table->timestamp('decided_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->index(['approver_admin_id'], 'idx_reqstep_admin');
            $table->index(['tenant_id', 'status'], 'idx_reqstep_tenant_status');
            $table->unique(['request_id', 'step_order'], 'uniq_reqstep_order');
        });

        // Foreign keys, matching 2026_01_01_999999_add_foreign_keys.php.
        Schema::table('performance_cycles', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'performance_cycles_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['created_by'], 'performance_cycles_ibfk_2')->references(['id'])->on('admins')->nullOnDelete();
        });
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->foreign(['cycle_id'], 'performance_reviews_ibfk_3')->references(['id'])->on('performance_cycles')->nullOnDelete();
        });
        if (Schema::hasTable('performance_goals')) {
            Schema::table('performance_goals', function (Blueprint $table): void {
                $table->foreign(['cycle_id'], 'performance_goals_ibfk_3')->references(['id'])->on('performance_cycles')->nullOnDelete();
            });
        }

        Schema::table('approval_chains', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'approval_chains_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['branch_id'], 'approval_chains_ibfk_2')->references(['id'])->on('branches')->cascadeOnDelete();
            $table->foreign(['created_by'], 'approval_chains_ibfk_3')->references(['id'])->on('admins')->nullOnDelete();
        });
        Schema::table('approval_chain_steps', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'approval_chain_steps_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['chain_id'], 'approval_chain_steps_ibfk_2')->references(['id'])->on('approval_chains')->cascadeOnDelete();
            $table->foreign(['approver_admin_id'], 'approval_chain_steps_ibfk_3')->references(['id'])->on('admins')->nullOnDelete();
        });
        Schema::table('approval_request_steps', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'approval_request_steps_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
            $table->foreign(['request_id'], 'approval_request_steps_ibfk_2')->references(['id'])->on('approval_requests')->cascadeOnDelete();
            $table->foreign(['approver_admin_id'], 'approval_request_steps_ibfk_3')->references(['id'])->on('admins')->nullOnDelete();
        });
        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->foreign(['chain_id'], 'approval_requests_ibfk_2')->references(['id'])->on('approval_chains')->nullOnDelete();
        });
    }
};
