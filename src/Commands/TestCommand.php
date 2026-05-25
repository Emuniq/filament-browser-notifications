<?php

namespace Emuniq\FilamentBrowserNotifications\Commands;

use Emuniq\FilamentBrowserNotifications\Jobs\SendDatabaseNotificationWebPush;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'browser-notifications:test
                            {user : User ID to send the test push to}
                            {--sync : Send synchronously instead of queuing}';

    protected $description = 'Send a test push notification to a user.';

    public function handle(): int
    {
        $userModel = config('auth.providers.users.model');
        $user = $userModel::find($this->argument('user'));

        if (! $user) {
            $this->error("User #{$this->argument('user')} not found.");
            return self::FAILURE;
        }

        if (! method_exists($user, 'pushSubscriptions')) {
            $this->error('User model does not have HasPushSubscriptions trait.');
            return self::FAILURE;
        }

        $count = $user->pushSubscriptions()->count();

        if ($count === 0) {
            $this->error("User \"{$user->name}\" has no push subscriptions.");
            $this->comment('The user needs to visit the app and enable browser notifications first.');
            return self::FAILURE;
        }

        $this->info("Sending test push to \"{$user->name}\" ({$count} device(s))...");

        $job = new SendDatabaseNotificationWebPush(
            $user,
            __('filament-browser-notifications::prompt.test_title'),
            __('filament-browser-notifications::prompt.test_body'),
            '/admin/profile',
        );

        if ($this->option('sync')) {
            dispatch_sync($job);
            $this->info('Push sent synchronously.');
        } else {
            dispatch($job);
            $this->info('Push job dispatched to queue.');
        }

        return self::SUCCESS;
    }
}
