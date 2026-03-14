<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Plainbooru\App;

$app = App::create();
$app->run();
