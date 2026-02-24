<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

App\Bootstrap::boot()
    ->createContainer()
    ->getByType(Nette\Application\Application::class)
    ->run();
