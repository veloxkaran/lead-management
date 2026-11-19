<?php

use App\Providers\AppServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\SlackWriteActivityServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    SlackWriteActivityServiceProvider::class,
];
