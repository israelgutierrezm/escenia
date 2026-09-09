<?php

declare(strict_types=1);

use App\Providers\AiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\ContentServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\EnterpriseServiceProvider;
use App\Providers\MediaServiceProvider;
use App\Providers\ScaleServiceProvider;

return [
    AiServiceProvider::class,
    AppServiceProvider::class,
    ContentServiceProvider::class,
    DomainServiceProvider::class,
    EnterpriseServiceProvider::class,
    MediaServiceProvider::class,
    ScaleServiceProvider::class,
];
