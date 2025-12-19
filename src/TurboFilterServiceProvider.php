<?php

namespace Vendor\TurboFilter;

use Illuminate\Support\ServiceProvider;

class TurboFilterServiceProvider extends ServiceProvider{

    function register(): void{
        $this->mergeConfigFrom(__DIR__ . '/../config/turbo-filter.php', 'turbo-filter');
    }

    function boot(): void{
        $this->publishes([__DIR__ . '/../config/turbo-filter.php' => $this->app->configPath('turbo-filter.php'),], 'config');
    }
}
