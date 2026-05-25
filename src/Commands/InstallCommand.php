<?php

namespace Emuniq\FilamentBrowserNotifications\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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
            $this->comment('User model not found at common paths — add HasPushSubscriptions trait manually.');
            return;
        }

        $content = File::get($userFile);

        if (Str::contains($content, 'HasPushSubscriptions')) {
            $this->comment('HasPushSubscriptions already present in User model.');
            return;
        }

        $this->components->task('Adding HasPushSubscriptions trait to User model', function () use ($userFile, $content) {
            $useStatement = "use NotificationChannels\\WebPush\\HasPushSubscriptions;\n";

            if (preg_match('/^(use\s+[^;]+;\s*\n)/m', $content, $match, PREG_OFFSET_CAPTURE)) {
                $insertPos = $match[0][1];
                $content = substr_replace($content, $useStatement, $insertPos, 0);
            }

            if (preg_match('/use\s+HasFactory\s*,\s*/', $content)) {
                $content = preg_replace(
                    '/use\s+HasFactory\s*,/',
                    'use HasFactory, HasPushSubscriptions,',
                    $content,
                    1,
                );
            } elseif (preg_match('/use\s+Notifiable\s*[;,]/', $content)) {
                $content = preg_replace(
                    '/use\s+Notifiable\s*;/',
                    'use HasPushSubscriptions, Notifiable;',
                    $content,
                    1,
                );
            }

            File::put($userFile, $content);

            return true;
        });
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
