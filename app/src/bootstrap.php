<?php
declare(strict_types=1);

namespace Batons;

const VERSION = '0.8.1';

// Autoloader minimal : Batons\Foo -> src/Foo.php (pas de Composer nécessaire).
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Batons\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 7)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require __DIR__ . '/functions.php';

date_default_timezone_set((string) Config::get('timezone'));
mb_internal_encoding('UTF-8');
