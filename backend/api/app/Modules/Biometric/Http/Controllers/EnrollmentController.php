<?php

declare(strict_types=1);

namespace App\Modules\Biometric\Http\Controllers;

use App\Exceptions\ApiFailure;
use App\Models\Admin;
use App\Models\Employee;
use App\Modules\Audit\Domain\AuditLog;
use App\Modules\Biometric\Domain\BiometricEnrollment;
use App\Shared\Http\ApiResponse;
use App\Support\Value;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ports of api/app/biometric/{enroll_face,enroll_fingerprint,delete,status}.php.
 *
 * The HR side of biometrics: recording a face or fingerprint for somebody else,
 * clearing one, and reading what is held.
 */
final class EnrollmentController
{
    /**
     * Clearing an enrollment is also how a re-enrollment is authorised: the
     * self-service path is one-time, so this is the only way back to the
     * camera.
     */
    public function delete(Request $request, int $id): JsonResponse
    {
        $tenantId = Value::int($request->attributes->get('tenant_id'));
        $admin = self::admin($request);

        $type = Value::string($request->input('type'), 'both') ?: 'both';

        if (! in_array($type, BiometricEnrollment::TYPES, true)) {
            throw new ApiFailure('type must be one of: face, fingerprint, both', 422, 'invalid_type');
        }

        $employeeId = $id;
        $employee = Employee::query()->where('id', $employeeId)->where('tenant_id', $tenantId)->first();

        if ($employee === null) {
            throw new ApiFailure(__('messages.employee_not_found'), 404, 'employee_not_found');
        }

        if ($type === 'face' || $type === 'both') {
            BiometricEnrollment::clearFace($employee->id, $tenantId);
        }

        if ($type === 'fingerprint' || $type === 'both') {
            BiometricEnrollment::clearFingerprint($employee->id, $tenantId);
        }

        AuditLog::record($tenantId, $admin->id, 'biometric.delete', 'employee', $employee->id);

        return ApiResponse::success(['employee_id' => $employee->id, 'deleted_type' => $type]);
    }

    public function status(Request $request): JsonResponse
    {
        $tenantId = Value::int($request->attributes->get('tenant_id'));
        $status = BiometricEnrollment::status(Value::int($request->query('employee_id')), $tenantId);

        if ($status === null) {
            throw new ApiFailure(__('messages.employee_not_found'), 404, 'employee_not_found');
        }

        return ApiResponse::success($status);
    }

    private static function admin(Request $request): Admin
    {
        $admin = $request->attributes->get('admin');

        if (! $admin instanceof Admin) {
            throw new ApiFailure(__('messages.authentication_required'), 401);
        }

        return $admin;
    }
}
