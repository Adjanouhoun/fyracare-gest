<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

$testDatabaseDirectory = dirname(__DIR__).'/var/test';
if (!is_dir($testDatabaseDirectory)) {
    mkdir($testDatabaseDirectory, 0770, true);
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}
