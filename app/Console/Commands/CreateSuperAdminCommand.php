<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:create-super-admin 
                            {email : The email address of the super admin}
                            {--name=Super Admin : The display name}
                            {--password= : Optional password (otherwise generated)}
                            {--force : Overwrite existing admin without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision or update the central platform super admin with two-factor authentication (2FA) enforced';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string $email */
        $email = $this->argument('email');
        /** @var string $name */
        $name = $this->option('name') ?: 'Super Admin';
        /** @var string|null $password */
        $password = $this->option('password');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email address: [{$email}].");

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user && ! $this->option('force')) {
            if (! $this->confirm("User [{$email}] already exists. Update to Super Admin and rotate 2FA secret?")) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $generatedPassword = false;
        if (! $password) {
            $password = Str::password(16);
            $generatedPassword = true;
        }

        // Generate RFC 4648 Base32 2FA Secret (16 bytes = 32 Base32 characters)
        $base32Alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $base32Alphabet[random_int(0, 31)];
        }

        // Generate 8 recovery codes
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = sprintf(
                '%s-%s',
                strtoupper(Str::random(5)),
                strtoupper(Str::random(5))
            );
        }

        if (! $user) {
            $user = new User;
            $user->email = $email;
        }

        $user->forceFill([
            'name' => $name,
            'password' => bcrypt($password),
            'tenant_id' => null,
            'is_platform_admin' => true,
            'email_verified_at' => now(),
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);
        $user->save();

        $this->info('====================================================');
        $this->info('  ✔️ Platform Super Admin Configured with 2FA');
        $this->info('====================================================');
        $this->line(" Name:           {$name}");
        $this->line(" Email:          {$email}");
        if ($generatedPassword) {
            $this->warn(" Password:       {$password}");
            $this->comment(' (Please save this password immediately; it is not stored in plaintext)');
        }
        $this->line(" 2FA Secret:     {$secret}");
        $this->line(' 2FA OTPAuth:    '.sprintf('otpauth://totp/AffiliateOS:%s?secret=%s&issuer=AffiliateOS', urlencode($email), $secret));
        $this->info('----------------------------------------------------');
        $this->info(' Recovery Codes (Store securely in password manager):');
        foreach ($recoveryCodes as $code) {
            $this->line("   - {$code}");
        }
        $this->info('====================================================');

        return self::SUCCESS;
    }
}
