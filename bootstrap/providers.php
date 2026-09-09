<?php

declare(strict_types=1);

use App\Providers\AiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\ContentServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\MediaServiceProvider;

return [
    AiServiceProvider::class,
    AppServiceProvider::class,
    ContentServiceProvider::class,
    DomainServiceProvider::class,
    MediaServiceProvider::class,
];
