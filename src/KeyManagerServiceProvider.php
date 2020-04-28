<?php

namespace ID\KeyManager;

use Auth\Repositories\KeyRepository;
use Illuminate\Support\ServiceProvider;

class KeyManagerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/manager.php' => config_path('manager.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/manager.php', 'manager');
        $this->mergeConfigFrom(__DIR__ . '/../config/filesystem.php', 'filesystems');
    }
}
