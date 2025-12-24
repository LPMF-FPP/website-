<?php

namespace App\Providers;

use App\Repositories\DocumentTemplateRepository;
use App\Services\DocumentGeneration\DocumentRenderService;
use App\Services\DocumentTemplates\DocumentTemplateRenderService;
use App\Services\DocumentGeneration\Resolvers\BaPenerimaanContextResolver;
use App\Services\DocumentGeneration\Resolvers\BaPenyerahanContextResolver;
use App\Services\DocumentGeneration\Resolvers\LhuContextResolver;
use Illuminate\Support\ServiceProvider;

class DocumentGenerationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register repository
        $this->app->singleton(DocumentTemplateRepository::class);

        $this->app->singleton(DocumentTemplateRenderService::class, function ($app) {
            return new DocumentTemplateRenderService(
                $app->make(DocumentTemplateRepository::class)
            );
        });

        // Register render service
        $this->app->singleton(DocumentRenderService::class, function ($app) {
            $service = new DocumentRenderService(
                $app->make(DocumentTemplateRepository::class),
                $app->make(DocumentTemplateRenderService::class)
            );

            // Register all context resolvers
            $this->registerResolvers($service);

            return $service;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register all document context resolvers
     */
    private function registerResolvers(DocumentRenderService $service): void
    {
        $service->registerResolver(new BaPenerimaanContextResolver());
        $service->registerResolver(new BaPenyerahanContextResolver());
        $service->registerResolver(new LhuContextResolver());
    }
}
