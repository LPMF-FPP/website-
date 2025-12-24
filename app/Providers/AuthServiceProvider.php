<?php

namespace App\Providers;

use App\Models\Document as LegacyDocument;
use App\Models\Investigator;
use App\Models\Person;
use App\Models\Search\Document;
use App\Policies\DocumentPolicy;
use App\Policies\InvestigatorDocumentPolicy;
use App\Policies\InvestigatorPolicy;
use App\Policies\PersonPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Person::class => PersonPolicy::class,
        Document::class => DocumentPolicy::class,
        LegacyDocument::class => InvestigatorDocumentPolicy::class,
        Investigator::class => InvestigatorPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
