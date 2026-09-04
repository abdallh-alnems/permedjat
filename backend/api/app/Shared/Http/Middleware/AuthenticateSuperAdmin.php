<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Exceptions\ApiFailure;
use App\Modules\SuperAdmin\Domain\SuperAdminSession;
use App\Support\Value;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The support desk's own guard.
 *
 * A fourth principal beside the administrator, the employee and the kiosk, and
 * the only one not scoped to a company. It carries a bearer token from its own
 * session table rather than a Firebase credential, because the panel signs in
 * with a username and password that no company controls.
 */
final class AuthenticateSuperAdmin
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  string  $minRole  readonly, admin or superadmin. Satisfied by that
     *                           rung or anything above it.
     */
    public function handle(Request $request, Closure $next, string $minRole = 'readonly'): Response
    {
        // X-Admin-Token first, because Authorization is already spoken for.
        // Every published build carries the shared app secret as HTTP Basic in
        // Authorization (RequireAppSecret), and one header cannot hold both a
        // Basic credential and a Bearer token — so a panel that authenticated
        // through Authorization could never get past the gate in front of it.
        // The other three principals already avoid this the same way, with
        // X-Employee-Token, X-Firebase-Token and X-Kiosk-Token.
        //
        // bearerToken() stays as a fallback: it is what the tests and any
        // curl-based tooling send, and it still works wherever the app secret
        // is unset — which is every local checkout.
        $token = Value::string($request->header('X-Admin-Token'))
            ?: Value::string($request->bearerToken());

        $admin = SuperAdminSession::resolve($token);

        if ($admin === null) {
            throw new ApiFailure(__('messages.admin_token_required'), 401, 'admin_token_required');
        }

        if ($admin->is_active !== 1) {
            throw new ApiFailure(__('messages.admin_account_disabled'), 403, 'admin_disabled');
        }

        if (! $admin->outranks($minRole)) {
            throw new ApiFailure(__('messages.insufficient_permissions'), 403, 'insufficient_permissions');
        }

        $request->attributes->set('super_admin', $admin);

        return $next($request);
    }
}
