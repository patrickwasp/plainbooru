#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Plainbooru\Config;
use Plainbooru\Db;

Config::load();

echo "Initializing database at: " . Config::dbPath() . PHP_EOL;

$pdo = Db::get(); // Migrations run automatically

echo "Database initialized successfully." . PHP_EOL;
echo "Tables: media, tags, media_tags, pools, pool_items" . PHP_EOL;
