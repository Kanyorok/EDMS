<?php

namespace App\Providers;

use App\Http\Middleware\Authenticate;
use App\Models\Auth\PersonalAccessToken;
use App\Models\Auth\User;
use App\Models\Core\Branch;
use App\Models\DMS\DMSSignature;
use App\Models\DMS\DMSTags;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\Document;
use App\Models\DMS\DocumentReview;
use App\Models\DMS\DocumentValidation;
use App\Models\DMS\DocumentValidationType;
use App\Models\DMS\EventType;
use App\Models\DMS\LegalHold;
use App\Models\DMS\LinkedToType;
use App\Models\DMS\Registry;
use App\Models\DMS\Repository;
use App\Models\DMS\SourceSystem;
use App\Models\DMS\StorageLocation;
use App\Models\HRM\Department;
use App\Models\HRM\Employee;
use App\Policies\DMS\DMSSignaturePolicy;
use App\Policies\DMS\DMSTagPolicy;
use App\Policies\DMS\DMSDocumentTypePolicy;
use App\Policies\DMS\DocumentPolicy;
use App\Policies\DMS\DocumentReviewPolicy;
use App\Policies\DMS\DocumentValidationPolicy;
use App\Policies\DMS\DocumentValidationTypePolicy;
use App\Policies\DMS\EventTypePolicy;
use App\Policies\DMS\LegalHoldPolicy;
use App\Policies\DMS\LinkedToTypePolicy;
use App\Policies\DMS\RegistryPolicy;
use App\Policies\DMS\RepositoryPolicy;
use App\Policies\DMS\SourceSystemPolicy;
use App\Policies\DMS\StorageLocationPolicy;
use App\Policies\RolePolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmployeePolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->app->singleton(\Illuminate\Auth\Middleware\Authenticate::class, Authenticate::class);

        Event::listen(\Illuminate\Console\Events\CommandStarting::class, function ($event): void {
            if (in_array($event->command, ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'], true)
                && ! filter_var(env('ALLOW_DATABASE_WIPE', false), FILTER_VALIDATE_BOOL)) {
                $event->output->writeln('<error>Database-wiping commands are disabled in this application.</error>');
                exit(1);
            }
        });

        foreach (['Read' => 'read', 'Write' => 'write', 'Update' => 'update', 'Delete' => 'delete', 'Approve' => 'approve'] as $directive => $action) {
            Blade::if('can'.$directive, static function (string $submodule) use ($action): bool {
                $user = auth()->user();
                return $user !== null && \App\Services\Core\PermissionResolver::can($user, $submodule, $action);
            });
        }

        Relation::morphMap([
            'UserID' => User::class,
            Branch::getPrimaryKey() => Branch::class,
            Document::getPrimaryKey() => Document::class,
            Repository::getPrimaryKey() => Repository::class,
            Registry::getPrimaryKey() => Registry::class,
            StorageLocation::getPrimaryKey() => StorageLocation::class,
            DocumentValidation::getPrimaryKey() => DocumentValidation::class,
        ]);

        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Repository::class, RepositoryPolicy::class);
        Gate::policy(Registry::class, RegistryPolicy::class);
        Gate::policy(StorageLocation::class, StorageLocationPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(DMSTags::class, DMSTagPolicy::class);
        Gate::policy(DMSSignature::class, DMSSignaturePolicy::class);
        Gate::policy(DocumentValidationType::class, DocumentValidationTypePolicy::class);
        Gate::policy(DocumentValidation::class, DocumentValidationPolicy::class);
        Gate::policy(DocumentReview::class, DocumentReviewPolicy::class);
        Gate::policy(LinkedToType::class, LinkedToTypePolicy::class);
        Gate::policy(SourceSystem::class, SourceSystemPolicy::class);
        Gate::policy(EventType::class, EventTypePolicy::class);
        Gate::policy(DMSDocumentType::class, DMSDocumentTypePolicy::class);
        Gate::policy(LegalHold::class, LegalHoldPolicy::class);

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
