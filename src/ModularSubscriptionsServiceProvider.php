<?php

namespace NewTags\FilamentModularSubscriptions;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use NewTags\FilamentModularSubscriptions\Commands\MakeModuleCommand;
use NewTags\FilamentModularSubscriptions\Commands\ScheduleInvoiceGeneration;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ModularSubscriptionsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-modular-subscriptions';

    public static string $viewNamespace = 'filament-modular-subscriptions';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->copyAndRegisterServiceProviderInApp()
                    ->askToStarRepoOnGitHub('hoceineel/filament-modular-subscriptions');
                if ($this->app->runningInConsole()) {
                    $this->commands([
                        MakeModuleCommand::class,
                        ScheduleInvoiceGeneration::class,
                    ]);
                }
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void
    {
        // Register the helpers file
        // require_once __DIR__ . '/helpers.php';
    }

    public function packageBooted(): void
    {
        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());
    }

    protected function getAssetPackageName(): ?string
    {
        return 'hoceineel/filament-modular-subscriptions';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            // AlpineComponent::make('skeleton', __DIR__ . '/../resources/dist/components/skeleton.js'),
            Css::make('saudi-riyal-styles', __DIR__ . '/../public/css/saudi-riyal.css'),
            // Js::make('skeleton-scripts', __DIR__ . '/../resources/dist/skeleton.js'),
        ];
    }

    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            '1_create_plans_table',
            '2_create_subscriptions_table',
            '3_create_modules_table',
            '4_create_module_usages_table',
            '5_create_plan_modules_table',
            '6_create_invoices_table',
            '7_create_invoice_items_table',
            '8_create_payments_table',
            'create_subscription_logs_table',
        ];
    }

    public function bootingPackage()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../database/seeders' => database_path('seeders'),
                __DIR__ . '/../public/fonts/saudi_riyal' => public_path('fonts/saudi_riyal'),
                __DIR__ . '/../public/css/saudi-riyal.css' => public_path('css/saudi-riyal.css'),
            ], 'filament-modular-subscriptions-assets');
        }
    }
}
