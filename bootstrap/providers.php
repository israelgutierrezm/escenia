<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\MediaServiceProvider;

return [
    AppServiceProvider::class,
    DomainServiceProvider::class,
    MediaServiceProvider::class,
];
