<?php

namespace ScrapyardIO\Tubes\Windows\Providers;

use Fabricate\NutsAndBolts\Contracts\DeferrableProvider;
use Fabricate\NutsAndBolts\ServiceProvider;
use ScrapyardIO\Tubes\Contracts\Windows\WindowFactory;
use ScrapyardIO\Tubes\Windows\WindowManager;

class WindowsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/windows.php',
            'windows',
        );

        $this->container->singleton('window', function ($app) {
            $manager = new WindowManager(
                $app->bound('config') ? $app->make('config')->get('windows', []) : [],
            );

            if (method_exists($app, 'configPath')) {
                $manager->registerFromConfigDirectory($app->configPath('windows'));
            }

            return $manager;
        });

        $this->container->singleton(WindowManager::class, fn ($app) => $app->make('window'));
        $this->container->singleton(WindowFactory::class, fn ($app) => $app->make('window'));
    }

    public function boot(): void
    {
        if ($this->container->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/windows.php' => $this->container->configPath('windows.php'),
            ], 'tubes-windows-config');
        }
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'window',
            WindowManager::class,
            WindowFactory::class,
        ];
    }
}
