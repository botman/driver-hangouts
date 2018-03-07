<?php

namespace BotMan\Drivers\Hangouts\Providers;

use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Hangouts\HangoutsDriver;
use BotMan\Studio\Providers\StudioServiceProvider;
use Illuminate\Support\ServiceProvider;

class HangoutsServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        if (!$this->isRunningInBotManStudio()) {
            $this->loadDrivers();

            $this->publishes([
                __DIR__.'/../../stubs/hangouts.php' => config_path('botman/hangouts.php'),
            ]);

            $this->mergeConfigFrom(__DIR__.'/../../stubs/hangouts.php', 'botman.hangouts');
        }
    }

    /**
     * Load BotMan drivers.
     */
    protected function loadDrivers()
    {
        DriverManager::loadDriver(HangoutsDriver::class);
    }

    /**
     * @return bool
     */
    protected function isRunningInBotManStudio()
    {
        return class_exists(StudioServiceProvider::class);
    }
}
