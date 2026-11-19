<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\BorrowItem;
use App\Models\BorrowReturn;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\User;
use App\Policies\EquipmentReferencePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::before(fn (User $user): ?bool => $user->is_active ? null : false);
        Gate::define('access-application', fn (User $user): bool => $user->is_active);
        Gate::policy(EquipmentCategory::class, EquipmentReferencePolicy::class);
        Gate::policy(EquipmentLocation::class, EquipmentReferencePolicy::class);

        Livewire::addPersistentMiddleware([EnsureAccountIsActive::class]);

        Relation::enforceMorphMap([
            'user' => User::class,
            'equipment' => Equipment::class,
            'equipment_category' => EquipmentCategory::class,
            'equipment_location' => EquipmentLocation::class,
            'borrow_transaction' => BorrowTransaction::class,
            'borrow_item' => BorrowItem::class,
            'borrow_return' => BorrowReturn::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
