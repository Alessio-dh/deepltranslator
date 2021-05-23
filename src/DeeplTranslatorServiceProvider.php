<?php

namespace Alessiodh\Deepltranslator;

use Alessiodh\Deepltranslator\Commands\DeeplTranslator;
use Illuminate\Support\ServiceProvider;

class DeeplTranslatorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->commands([
            DeeplTranslator::class
        ]);

        $this->publishes([
            __DIR__.'/config/deepltranslator.php' => config_path('/deepltranslator.php')
        ],'config');
    }
}
