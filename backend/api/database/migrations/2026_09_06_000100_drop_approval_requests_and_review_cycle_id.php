<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the two things 000200 held back.
 *
 * `approval_requests` was left standing because it was not on the approved list
 * at the time. With the chains gone it could never gain another row — `route()`
 * was its only writer and now returns null without a query — so it was an empty
 * table with no writer and no reader. It is empty in every tenant: production
 * has held 0 rows since the feature was written.
 *
 * `performance_reviews.cycle_id` was left as a nullable column that could only
 * ever be null, once `performance_cycles` was dropped. No client ever sent it —
 * zero references across all five frontends — so removing it takes nothing away
 * from any caller.
 *
 * The code that read both was changed first, in the same commit:
 * ApprovalRouter::isPending() returns false and ::cancelFor() is a no-op
 * (neither touches a table now), and `cycle_id` is gone from ReviewController
 * and PerformanceReviews. The `cancelFor()` call sites in LeaveAdminController
 * and MyLeaveController stay: a leave's own state has always lived in `leaves`,
 * so nothing about approving, rejecting or withdrawing one depended on this
 * table.
 *
 * `down()` restores the shape, never the contents — which costs nothing here,
 * because there are no contents. It must recreate `approval_requests` before
 * 000200's own down() runs, since that one re-adds this table's `chain_id`
 * foreign key and the `approval_request_steps` key pointing back at it. A
 * reverse-order rollback gives exactly that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::drop('approval_requests');

        // Dropping the column takes `idx_prev_cycle` with it: it indexed nothing
        // else. The foreign key was already dropped by 000200.
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->dropColumn('cycle_id');
        });
    }

    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table): void {
            $table->integer('cycle_id')->unsigned()->nullable()->after('employee_id');
            $table->index(['cycle_id'], 'idx_prev_cycle');
        });

        // Body copied verbatim from the original create migration.
        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('tenant_id')->unsigned();
            $table->integer('chain_id')->unsigned()->nullable()->comment('Referential; SET NULL if chain deleted (steps are snapshot)');
            $table->string('entity_type', 40);
            $table->integer('entity_id')->unsigned();
            $table->integer('requested_by_admin_id')->unsigned()->nullable();
            $table->integer('requested_by_employee_id')->unsigned()->nullable();
            $table->decimal('context_amount', 14, 2)->nullable()->comment('Amount used for conditional matching (audit)');
            $table->tinyInteger('current_step')->unsigned()->default(1);
            $table->tinyInteger('total_steps')->unsigned();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('decided_at')->nullable()->comment('Final decision timestamp');
            $table->index(['chain_id'], 'approval_requests_ibfk_2');
            $table->index(['tenant_id', 'entity_type', 'entity_id'], 'idx_req_entity');
            $table->index(['tenant_id', 'status'], 'idx_req_tenant_status');
        });

        // Only the tenant key: `approval_requests_ibfk_2` is 000200's to re-add,
        // and it runs after this one on the way back.
        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->foreign(['tenant_id'], 'approval_requests_ibfk_1')->references(['id'])->on('tenants')->cascadeOnDelete();
        });
    }
};
