<?php

namespace CesarFerreira\EncryptWireEvents;
use Illuminate\Support\ServiceProvider;
use Livewire\Mechanisms\HandleComponents\HandleComponents;

class EncryptWireServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(HandleComponents::class, CustomHandleComponents::class);
    }

    public function boot()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/encrypt-wire.php', 'encrypt-wire'
        );

        $this->publishes([
            __DIR__.'/../config/encrypt-wire.php' => config_path('encrypt-wire.php'),
        ], 'encrypt-wire-config');
    }
}