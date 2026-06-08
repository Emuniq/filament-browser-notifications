<?php

namespace Emuniq\FilamentBrowserNotifications\Tests;

use Emuniq\FilamentBrowserNotifications\BrowserNotificationsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            BrowserNotificationsServiceProvider::class,
        ];
    }
}
