<?php

use Emuniq\FilamentBrowserNotifications\Commands\InstallCommand;

/**
 * Build a minimal User model source whose class body declares the given
 * trait-usage line, alongside the fully-qualified Notifiable import that
 * Laravel ships by default.
 */
function userModelWith(string $traitLine): string
{
    return "<?php\n\nnamespace App\\Models;\n\n"
        . "use Illuminate\\Notifications\\Notifiable;\n"
        . "use Illuminate\\Foundation\\Auth\\User as Authenticatable;\n\n"
        . "class User extends Authenticatable\n{\n    {$traitLine}\n}\n";
}

dataset('trait_lines', [
    'notifiable only' => ['use Notifiable;'],
    'notifiable then role' => ['use Notifiable, HasRoles;'],
    'factory then notifiable' => ['use HasFactory, Notifiable;'],
    'api tokens then notifiable' => ['use HasApiTokens, Notifiable;'],
    'notifiable in the middle' => ['use HasFactory, Notifiable, HasRoles;'],
]);

it('applies the trait into the class body for every Notifiable arrangement', function (string $traitLine) {
    $source = userModelWith($traitLine);

    // The original source must not be considered "already patched".
    expect(InstallCommand::usesPushTrait($source))->toBeFalse();

    $patched = InstallCommand::applyPushTrait($source);

    expect($patched)->not->toBeNull()
        // FQCN import is added once.
        ->toContain('use NotificationChannels\\WebPush\\HasPushSubscriptions;')
        // The default Notifiable import is left untouched.
        ->toContain('use Illuminate\\Notifications\\Notifiable;');

    // The trait is now actually used in the class body, not just imported.
    expect(InstallCommand::usesPushTrait($patched))->toBeTrue();

    // The result is syntactically valid PHP.
    expect(php_lint($patched))->toBeTrue();
})->with('trait_lines');

it('does not re-apply or duplicate the trait when already present', function () {
    $patched = InstallCommand::applyPushTrait(userModelWith('use HasFactory, Notifiable;'));

    // Idempotency guard now recognises the applied trait.
    expect(InstallCommand::usesPushTrait($patched))->toBeTrue();

    // Re-running on the same content would short-circuit before patching again.
    expect(substr_count($patched, 'HasPushSubscriptions, Notifiable'))->toBe(1);
});

it('treats a lone import (the previously broken half-install) as not yet applied', function () {
    $halfInstalled = "<?php\n\nnamespace App\\Models;\n\n"
        . "use NotificationChannels\\WebPush\\HasPushSubscriptions;\n"
        . "use Illuminate\\Notifications\\Notifiable;\n\n"
        . "class User extends Authenticatable\n{\n    use Notifiable;\n}\n";

    // Only the import is present, so the install can still be completed.
    expect(InstallCommand::usesPushTrait($halfInstalled))->toBeFalse();

    $patched = InstallCommand::applyPushTrait($halfInstalled);

    expect(InstallCommand::usesPushTrait($patched))->toBeTrue()
        // The import is not duplicated.
        ->and(substr_count($patched, 'use NotificationChannels\\WebPush\\HasPushSubscriptions;'))->toBe(1);
});

it('returns null when the model has no Notifiable trait to anchor to', function () {
    $source = "<?php\n\nclass User extends Authenticatable\n{\n    use HasFactory;\n}\n";

    expect(InstallCommand::applyPushTrait($source))->toBeNull();
});

/**
 * Lint a PHP source string without writing it to the project tree.
 */
function php_lint(string $source): bool
{
    $tmp = tempnam(sys_get_temp_dir(), 'bn_lint_') . '.php';
    file_put_contents($tmp, $source);

    exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $output, $exitCode);

    @unlink($tmp);

    return $exitCode === 0;
}
