<?php

declare(strict_types=1);

namespace App\Modules\SuperAdmin\Console;

use App\Modules\SuperAdmin\Domain\SuperAdminAudit;
use App\Support\Value;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Console operator from the command line.
 *
 * `POST /v1/admin/operators` is the normal way to add one, and it stays that
 * way — but it cannot make the first. The route sits behind
 * `auth.super:superadmin`, which resolves an `X-Admin-Token` against a row in
 * `super_admins`, and the controller records who did it. With the table empty
 * there is no token to present and nobody to record, so the endpoint is closed
 * on itself. This opens it once; after that the panel creates its own.
 *
 * Interactive only, and there is deliberately no --password option. A password
 * passed as an argument is written to the shell history of whoever ran it and
 * to the process list of everyone on the box while it runs, which is a worse
 * exposure than the one this command exists to avoid. It is reachable only with
 * shell access to the server, which is the boundary the credential already sits
 * behind.
 *
 * The rules below mirror DirectoryController::createOperator so an account made
 * here and one made through the panel are the same thing. If either side gains
 * a rule, the other needs it too.
 */
final class CreateOperatorCommand extends Command
{
    protected $signature = 'permedjat:create-operator';

    protected $description = 'Creates a Console operator interactively — including the first, which no endpoint can';

    private const ROLES = ['readonly', 'admin', 'superadmin'];

    private const MIN_USERNAME_LENGTH = 3;

    private const MIN_PASSWORD_LENGTH = 6;

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('This command asks for a password and must be run interactively.');
            $this->line('Run it from a terminal on the server, without --no-interaction.');

            return self::FAILURE;
        }

        $existing = (int) DB::table('super_admins')->count();

        $this->newLine();
        $this->line($existing === 0
            ? 'No operators exist yet — this will be the first, and it can create the rest from the panel.'
            : "There are already {$existing} operator(s). Adding another from here.");
        $this->newLine();

        $username = $this->askForUsername();
        if ($username === null) {
            return self::FAILURE;
        }

        $email = $this->askForEmail();
        if ($email === false) {
            return self::FAILURE;
        }

        $displayName = trim(Value::string($this->ask('Display name (optional)', ''))) ?: null;

        /** @var string $role */
        $role = $this->choice('Role', self::ROLES, $existing === 0 ? 'superadmin' : 'admin');

        $password = $this->askForPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        $id = (int) DB::table('super_admins')->insertGetId([
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $displayName,
            'role' => $role,
            'is_active' => 1,
        ]);

        // admin_id is null on purpose: nobody in the panel did this, and saying
        // the new operator created themselves would be a lie in the one log
        // that exists to say who did what.
        SuperAdminAudit::record(null, 'super_admin.bootstrap', 'super_admin', $id, [
            'username' => $username,
            'role' => $role,
            'via' => 'console',
        ]);

        $this->newLine();
        $this->info("Operator #{$id} created: {$username} ({$role})");
        $this->line('Sign in from the Console app with that username and password.');

        return self::SUCCESS;
    }

    private function askForUsername(): ?string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $username = trim(Value::string($this->ask('Username')));

            if (mb_strlen($username) < self::MIN_USERNAME_LENGTH) {
                $this->error('Username must be at least '.self::MIN_USERNAME_LENGTH.' characters.');

                continue;
            }

            if (DB::table('super_admins')->where('username', $username)->exists()) {
                $this->error('That username is taken.');

                continue;
            }

            return $username;
        }

        $this->error('Giving up after three attempts. Nothing was created.');

        return null;
    }

    /**
     * @return string|null|false The address, null for none, or false to give up.
     */
    private function askForEmail(): string|null|false
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $email = trim(Value::string($this->ask('Email (optional, press enter to skip)', '')));

            if ($email === '') {
                return null;
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $this->error('That is not a valid email address.');

                continue;
            }

            if (DB::table('super_admins')->where('email', $email)->exists()) {
                $this->error('That email is already on another operator.');

                continue;
            }

            return $email;
        }

        $this->error('Giving up after three attempts. Nothing was created.');

        return false;
    }

    private function askForPassword(): ?string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $password = Value::string($this->secret('Password (not shown)'));

            if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
                $this->error('Password must be at least '.self::MIN_PASSWORD_LENGTH.' characters.');

                continue;
            }

            if ($password !== Value::string($this->secret('Confirm password'))) {
                $this->error('The two passwords do not match.');

                continue;
            }

            return $password;
        }

        $this->error('Giving up after three attempts. Nothing was created.');

        return null;
    }
}
