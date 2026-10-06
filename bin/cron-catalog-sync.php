<?php

// Scheduled catalog sync for shared hosting whose cron runs a PHP file without arguments nor
// environment (OVHcloud, see docs/self-hosting.md): reads the SetEnv lines of public/.htaccess,
// then runs "bin/console catalog:sync" in this same process (no exec(), often disabled).
$app = dirname(__DIR__);
foreach (file($app.'/public/.htaccess', FILE_IGNORE_NEW_LINES) as $line) {
    if (preg_match('/^\s*SetEnv\s+(\w+)\s+(?:"([^"]*)"|(\S+))\s*$/', $line, $m)) {
        $value = '' !== $m[2] ? $m[2] : ($m[3] ?? '');
        putenv($m[1].'='.$value);
        $_ENV[$m[1]] = $_SERVER[$m[1]] = $value;
    }
}
chdir($app);
$_SERVER['argv'] = ['bin/console', 'catalog:sync', '--no-interaction'];
$_SERVER['argc'] = 3;
// Symfony Runtime runs the script named here: the console, not this file.
$_SERVER['SCRIPT_FILENAME'] = $app.'/bin/console';
require $app.'/bin/console';
