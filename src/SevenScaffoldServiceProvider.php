<?php

namespace Seven\Scaffold;

use Illuminate\Support\ServiceProvider;
use Seven\Scaffold\Commands\InstallCommand;
use Seven\Scaffold\Commands\ScaffoldCommand;

class SevenScaffoldServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ScaffoldCommand::class,
                InstallCommand::class,
            ]);
        }
    }
}