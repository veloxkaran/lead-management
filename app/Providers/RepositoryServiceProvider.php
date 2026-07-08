<?php

namespace App\Providers;

use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\LeadRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Interface => concrete implementation bindings for the repository layer.
     * New modules register their binding here as they are built.
     */
    public array $bindings = [
        LeadRepositoryInterface::class => LeadRepository::class,
    ];
}
