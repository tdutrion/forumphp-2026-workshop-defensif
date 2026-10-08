<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

// No symfony/dotenv: the environment comes from the container and phpunit.dist.xml.
if ($_SERVER['APP_DEBUG'] ?? true) {
    umask(0000);
}
