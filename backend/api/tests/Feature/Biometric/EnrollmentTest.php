<?php

declare(strict_types=1);

namespace Tests\Feature\Biometric;

use App\Modules\Auth\Services\FirebaseTokenVerifier;
use App\Modules\Notifications\Domain\PushSender;
use App\Shared\Face\FaceEmbedding;
use App\Shared\Face\FaceEnrollment;
use App\Support\Value;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesFixtures;
use Tests\Support\FakeFirebaseTokenVerifier;
use Tests\Support\FakePushSender;
use Tests\TestCase;

/**
 * The HR side of biometrics: recording a face or fingerprint for somebody else.
 */
final class EnrollmentTest extends TestCase
{
    use CreatesFixtures;
    use DatabaseTransactions;

    private int $tenantId;

    private int $branchId;

    private int $employeeId;

    private FakeFirebaseTokenVerifier $firebase;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firebase = new FakeFirebaseTokenVerifier;
        $this->app->instance(FirebaseTokenVerifier::class, $this->firebase);
        $this->app->instance(PushSender::class, new FakePushSender);

        $this->tenantId = $this->createTenant();
        $this->branchId = (int) DB::table('branches')->insertGetId([
            'tenant_id' => $this->tenantId, 'name' => 'Biometric branch', 'is_active' => 1,
        ]);

        $this->employeeId = (int) DB::table('employees')->insertGetId([
            'tenant_id' => $this->tenantId,
            'branch_id' => $this->branchId,
            'name' => 'Enrollment fixture',
            'status' => 'active',
            'base_salary' => 3000,
            'hire_date' => '2021-01-01',
            'biometric_enrollment_status' => 'not_enrolled',
        ]);

        $this->adminToken = $this->admin('general_manager');
    }

    private function admin(string $role, ?int $branchId = null): string
    {
        $uid = 'uid-'.bin2hex(random_bytes(6));
        DB::table('admins')->insert([
            'firebase_uid' => $uid,
            'tenant_id' => $this->tenantId,
            'branch_id' => $branchId,
            'name' => 'Admin '.$role,
            'role' => $role,
            'is_active' => 1,
        ]);

        return $this->firebase->issue($uid);
    }

    /**
     * @return list<float>
     */
    private static function vector(float $seed = 0.1): array
    {
        return array_fill(0, 128, $seed);
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeRow(): array
    {
        /** @var array<string, mixed> $row */
        $row = (array) DB::table('employees')->where('id', $this->employeeId)->first();

        return $row;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Illuminate\Http\JsonResponse>
     */
    private function sendDelete(string $path, array $payload = [], ?string $token = null): TestResponse
    {
        return $this->withHeader('X-Firebase-Token', $token ?? $this->adminToken)->deleteJson($path, $payload);
    }

    /**
     * Puts a face on file without going through an endpoint.
     *
     * The HR enrollment routes were removed on 2026-09-06 — no client ever
     * called them — so the tests for the routes that remain seed the row the
     * same way the surviving paths do, through the domain.
     */
    private function enrolFace(?int $employeeId = null, float $quality = 0.9): void
    {
        FaceEnrollment::record(
            $employeeId ?? $this->employeeId,
            $this->tenantId,
            self::vector(),
            null,
            $quality,
            FaceEmbedding::MODEL_VERSION,
        );
    }

    /**
     * The columns the retired fingerprint route used to write. Nothing can set
     * them any more, which is why this is spelled out here rather than called.
     */
    private function enrolFingerprint(): void
    {
        DB::update(
            'UPDATE employees SET fingerprint_enrolled_at = NOW(),'
            .' biometric_enrollment_status = CASE'
            ."   WHEN face_embedding IS NOT NULL THEN 'both' ELSE 'fingerprint_only' END"
            .' WHERE id = ? AND tenant_id = ?',
            [$this->employeeId, $this->tenantId],
        );
    }

    public function test_holding_both_templates_is_reflected_in_the_status(): void
    {
        $this->enrolFace();

        $this->enrolFingerprint();

        $this->assertDatabaseHas('employees', [
            'id' => $this->employeeId, 'biometric_enrollment_status' => 'both',
        ]);
    }

    public function test_clearing_the_face_leaves_the_fingerprint_standing(): void
    {
        $this->enrolFace();
        $this->enrolFingerprint();

        $this->sendDelete('/v1/biometric/'.$this->employeeId, ['type' => 'face'])->assertOk()->assertJsonPath('data.deleted_type', 'face');

        $row = $this->employeeRow();
        $this->assertNull($row['face_embedding']);
        $this->assertNull($row['face_enrolled_at']);
        $this->assertNotNull($row['fingerprint_enrolled_at']);
        $this->assertSame('fingerprint_only', Value::string($row['biometric_enrollment_status']));
    }

    public function test_clearing_everything_returns_the_employee_to_unenrolled(): void
    {
        $this->enrolFace();

        $this->sendDelete('/v1/biometric/'.$this->employeeId)->assertOk();

        $this->assertDatabaseHas('employees', [
            'id' => $this->employeeId, 'biometric_enrollment_status' => 'not_enrolled',
        ]);
    }

    public function test_an_unknown_removal_type_is_refused(): void
    {
        $this->sendDelete('/v1/biometric/'.$this->employeeId, ['type' => 'retina'])->assertStatus(422);
    }

    public function test_enrolling_does_not_confer_the_right_to_clear(): void
    {
        // Clearing is what authorises a re-enrollment, so it is the step that
        // could be used to swap somebody's reference face.
        $clerk = $this->admin('attendance');

        $this->enrolFace();

        $this->sendDelete('/v1/biometric/'.$this->employeeId, [], $clerk)
            ->assertStatus(403);
    }

    public function test_the_status_screen_reports_what_is_held(): void
    {
        $this->enrolFace(quality: 0.82);

        $this->withHeader('X-Firebase-Token', $this->adminToken)
            ->getJson('/v1/biometric/status?employee_id='.$this->employeeId)
            ->assertOk()
            ->assertJsonPath('data.biometric_enrollment_status', 'face_only')
            ->assertJsonPath('data.needs_reenrollment', false);
    }

    public function test_an_embedding_from_a_retired_model_is_flagged_for_re_enrollment(): void
    {
        $this->enrolFace();

        DB::table('employees')->where('id', $this->employeeId)
            ->update(['face_model_version' => 'retired_v0']);

        // Otherwise every check-in fails with a mismatch nobody can explain.
        $this->withHeader('X-Firebase-Token', $this->adminToken)
            ->getJson('/v1/biometric/status?employee_id='.$this->employeeId)
            ->assertOk()
            ->assertJsonPath('data.needs_reenrollment', true);
    }

    public function test_an_enrollment_from_before_the_version_column_is_not_flagged(): void
    {
        $this->enrolFace();

        DB::table('employees')->where('id', $this->employeeId)
            ->update(['face_model_version' => null]);

        // The verifier accepts a null version, so telling HR to reset these
        // would send people back through enrollment for nothing.
        $this->withHeader('X-Firebase-Token', $this->adminToken)
            ->getJson('/v1/biometric/status?employee_id='.$this->employeeId)
            ->assertOk()
            ->assertJsonPath('data.needs_reenrollment', false);
    }

    public function test_a_status_request_for_nobody_is_a_404(): void
    {
        $this->withHeader('X-Firebase-Token', $this->adminToken)
            ->getJson('/v1/biometric/status?employee_id=0')
            ->assertStatus(404);
    }
}
