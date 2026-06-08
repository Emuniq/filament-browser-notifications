<?php

namespace Emuniq\FilamentBrowserNotifications\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'browser-notifications:install
                            {--force : Overwrite existing VAPID keys}';

    protected $description = 'Install Filament Browser Notifications: generate VAPID keys, run migration, patch User model.';

    public function handle(): int
    {
        $this->info('Installing Filament Browser Notifications...');
        $this->newLine();

        $this->generateVapidKeys();
        $this->publishAndRunMigrations();
        $this->patchUserModel();

        $this->newLine();
        $this->components->info('Installation complete!');
        $this->newLine();
        $this->line('  The plugin auto-registers on all Filament panels.');
        $this->line('  The service worker is served automatically from <fg=green>/sw.js</>.');
        $this->line('  No further configuration needed — every <fg=green>sendToDatabase()</> now triggers a browser push.');

        return self::SUCCESS;
    }

    protected function generateVapidKeys(): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            $this->warn('.env not found — generate VAPID keys manually:');
            $this->line('  php artisan tinker --execute="echo json_encode(\Minishlink\WebPush\VAPID::createVapidKeys());"');
            return;
        }

        $envContent = File::get($envPath);
        $hasValues = (bool) preg_match('/VAPID_PUBLIC_KEY=\S+/', $envContent);

        if ($hasValues && ! $this->option('force')) {
            $this->comment('VAPID keys already configured — skipping (use --force to regenerate).');
            return;
        }

        $this->components->task('Generating VAPID keys', function () use ($envPath) {
            $keys = \Minishlink\WebPush\VAPID::createVapidKeys();

            $this->setEnvValue($envPath, 'VAPID_PUBLIC_KEY', $keys['publicKey']);
            $this->setEnvValue($envPath, 'VAPID_PRIVATE_KEY', $keys['privateKey']);
            $this->setEnvValue($envPath, 'VAPID_SUBJECT', config('app.url', 'https://localhost'));

            return true;
        });
    }

    protected function publishAndRunMigrations(): void
    {
        $this->components->task('Publishing push_subscriptions migration', function () {
            Artisan::call('vendor:publish', [
                '--provider' => 'NotificationChannels\WebPush\WebPushServiceProvider',
                '--tag' => 'migrations',
            ]);

            return true;
        });

        $this->components->task('Running migrations', function () {
            Artisan::call('migrate', ['--force' => true]);

            return true;
        });
    }

    protected function patchUserModel(): void
    {
        $candidates = [
            app_path('Models/User.php'),
            app_path('Models/Users/User.php'),
            app_path('User.php'),
        ];

        $userFile = null;
        foreach ($candidates as $path) {
            if (File::exists($path)) {
                $userFile = $path;
                break;
            }
        }

        if (! $userFile) {
            $this->comment('User model not found at common paths.');
            $this->printManualTraitInstructions();
            return;
        }

        $content = File::get($userFile);

        // Only skip when the trait is actually applied in the class body — not
        // when it is merely imported. A half-finished install can leave just
        // the FQCN import behind; treating that as "already present" would lock
        // the model into a broken state on every re-run.
        if (static::usesPushTrait($content)) {
            $this->comment('HasPushSubscriptions already present in User model.');
            return;
        }

        $patched = static::applyPushTrait($content);

        if ($patched === null) {
            $this->comment('Could not find the Notifiable trait in the User model.');
            $this->printManualTraitInstructions();
            return;
        }

        $written = false;

        $this->components->task('Adding HasPushSubscriptions trait to User model', function () use ($userFile, $patched, &$written) {
            // Degrade gracefully when the model is read-only (e.g. a Docker
            // image where app/Models is not writable by the PHP process)
            // instead of surfacing a raw filesystem exception.
            if (! is_writable($userFile)) {
                return false;
            }

            try {
                $written = File::put($userFile, $patched) !== false;
            } catch (\Throwable) {
                $written = false;
            }

            return $written;
        });

        if (! $written) {
            $this->components->warn("Could not write to {$userFile} — it may be read-only.");
            $this->printManualTraitInstructions();
        }
    }

    /**
     * Print copy-paste instructions for wiring the trait up by hand, used
     * whenever the User model cannot be located or written automatically.
     */
    protected function printManualTraitInstructions(): void
    {
        $this->line('  Add the trait to your User model manually:');
        $this->line('    • import: <fg=green>use NotificationChannels\\WebPush\\HasPushSubscriptions;</>');
        $this->line('    • trait:  add <fg=green>HasPushSubscriptions</> to the model\'s <fg=green>use ...;</> traits, next to Notifiable.');
    }

    /**
     * Whether the User model source already *uses* the HasPushSubscriptions
     * trait inside the class body. A bare, short-name reference in a `use ...;`
     * trait statement counts; the fully-qualified import on its own does not.
     */
    public static function usesPushTrait(string $content): bool
    {
        return (bool) preg_match(
            '/(?:\n|^)[ \t]*use\s+(?:[A-Za-z0-9_]+\s*,\s*)*HasPushSubscriptions\s*[,;]/',
            $content,
        );
    }

    /**
     * Add the HasPushSubscriptions import and apply the trait next to the
     * Notifiable trait usage in the class body, regardless of its position in
     * the trait list (`use Notifiable;`, `use Notifiable, HasRoles;`,
     * `use HasFactory, Notifiable;`, ...). Returns the patched source, or null
     * when no Notifiable trait usage can be found.
     */
    public static function applyPushTrait(string $content): ?string
    {
        // The preceding names are matched as bare short names (no backslash),
        // so the fully-qualified `use Illuminate\Notifications\Notifiable;`
        // import is never mistaken for the in-class trait declaration.
        $patched = preg_replace(
            '/((?:\n|^)[ \t]*use\s+(?:[A-Za-z0-9_]+\s*,\s*)*)Notifiable(\s*[,;])/',
            '${1}HasPushSubscriptions, Notifiable${2}',
            $content,
            1,
            $count,
        );

        if ($count === 0) {
            return null;
        }

        // Add the FQCN import once, before the first top-level use statement.
        if (! str_contains($patched, 'use NotificationChannels\\WebPush\\HasPushSubscriptions;')) {
            $useStatement = "use NotificationChannels\\WebPush\\HasPushSubscriptions;\n";

            if (preg_match('/^use\s+[^;]+;\s*\n/m', $patched, $match, PREG_OFFSET_CAPTURE)) {
                $patched = substr_replace($patched, $useStatement, $match[0][1], 0);
            }
        }

        return $patched;
    }

    protected function setEnvValue(string $envPath, string $key, string $value): void
    {
        $content = File::get($envPath);
        $pattern = "/^{$key}=.*$/m";

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, "{$key}={$value}", $content);
        } else {
            $content .= "\n{$key}={$value}";
        }

        File::put($envPath, $content);
    }
}
