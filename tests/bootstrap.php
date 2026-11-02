<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Register the Livewire and Laravel stubs as if they were real installed packages,
// so fixture classes that `use Livewire\Component` or extend an Eloquent model
// resolve during reflection-based tests.
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Livewire\\') || str_starts_with($class, 'Illuminate\\')) {
        $path = __DIR__ . '/Stubs/' . str_replace('\\', '/', $class) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

// Register the test-fixture namespace. The package's autoload-dev mapping
// covers tests/ at composer-install time, but PHPStan's RuleTestCase boots
// a separate container that doesn't see the consumer project's autoload-dev.
spl_autoload_register(function (string $class): void {
    $prefix = 'BoringO11y\\Wirestan\\Tests\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, \strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
