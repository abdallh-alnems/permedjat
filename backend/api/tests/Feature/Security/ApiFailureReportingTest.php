<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Exceptions\ApiFailure;
use App\Modules\Auth\Services\FirebaseTokenVerifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\Support\FakeFirebaseTokenVerifier;
use Tests\TestCase;

/**
 * A refusal is an answer, not a fault.
 *
 * ApiFailure carries both: a 401 for a missing token, and a 500 for something
 * that genuinely broke. Only the second belongs in the error log — the first
 * was writing 30MB and 4,200 stack traces a day, all of it from an uptime probe
 * that asks for an endpoint without credentials on purpose, and it was 100% of
 * the log. This pins which of the two reports.
 */
final class ApiFailureReportingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Routes that raise each kind, so the assertion runs through the real
        // handler rather than calling report() by hand.
        Route::get('_test/refusal', fn () => throw new ApiFailure('nope', 401, 'unauthorized'));
        Route::get('_test/forbidden', fn () => throw new ApiFailure('nope', 403, 'forbidden'));
        Route::get('_test/broken', fn () => throw new ApiFailure('broke', 500, 'server_error'));

        // The admin guard resolves a Firebase verifier before it can refuse.
        // Without this the test environment dies on a missing credentials file
        // instead of producing the refusal the probe actually receives.
        $this->app->instance(FirebaseTokenVerifier::class, new FakeFirebaseTokenVerifier);

        // RequireAppSecret disables itself when the secret is unset, which it is
        // everywhere but production — and it is the gate the probe actually
        // meets, so the end-to-end case sets one to reproduce that path.
        config(['permedjat.app_secret.user' => 'probe-user', 'permedjat.app_secret.key' => 'probe-key']);
    }

    public function test_a_401_is_answered_but_not_logged(): void
    {
        $log = Log::spy();

        $this->getJson('/_test/refusal')
            ->assertStatus(401)
            ->assertJsonPath('message', 'nope')
            ->assertJsonPath('error_code', 'unauthorized');

        $log->shouldNotHaveReceived('error');
    }

    public function test_a_403_is_answered_but_not_logged(): void
    {
        $log = Log::spy();

        $this->getJson('/_test/forbidden')->assertStatus(403);

        $log->shouldNotHaveReceived('error');
    }

    public function test_a_500_still_reaches_the_log(): void
    {
        $log = Log::spy();

        $this->getJson('/_test/broken')->assertStatus(500);

        $log->shouldHaveReceived('error');
    }

    /**
     * The probe's own request, end to end: no credentials, a real endpoint.
     * It must still refuse — the monitoring reads 401 as proof the guard works.
     */
    public function test_the_uptime_probe_still_gets_its_401(): void
    {
        $log = Log::spy();

        $this->getJson('/v1/employees')->assertStatus(401);

        $log->shouldNotHaveReceived('error');
    }
}
