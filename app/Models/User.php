<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => \App\Enums\Role::class,
            'status' => \App\Enums\UserStatus::class,
        ];
    }

    /**
     * Determine if the user has the given role.
     */
    public function hasRole(\App\Enums\Role|string $role): bool
    {
        if ($role instanceof \App\Enums\Role) {
            return $this->role === $role;
        }

        return $this->role->value === $role;
    }

    /**
     * Determine if the user has the given status.
     */
    public function hasStatus(\App\Enums\UserStatus|string $status): bool
    {
        if ($status instanceof \App\Enums\UserStatus) {
            return $this->status === $status;
        }

        return $this->status->value === $status;
    }

    /**
     * User integrations relationship.
     */
    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function productCatalogs(): HasMany
    {
        return $this->hasMany(ProductCatalog::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function manufacturers(): HasMany
    {
        return $this->hasMany(Manufacturer::class);
    }

    public function warehouseLocations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class);
    }

    public function warehouseDocuments(): HasMany
    {
        return $this->hasMany(WarehouseDocument::class);
    }

    public function warehouseStockTotals(): HasMany
    {
        return $this->hasMany(WarehouseStockTotal::class);
    }

    public function contractors(): HasMany
    {
        return $this->hasMany(Contractor::class);
    }

    public function warehouseDocumentSettings(): HasMany
    {
        return $this->hasMany(WarehouseDocumentSetting::class);
    }

    public function integrationTaskRuns()
    {
        return IntegrationTaskRun::query()
            ->join('integration_tasks', 'integration_task_runs.task_id', '=', 'integration_tasks.id')
            ->join('integrations', 'integration_tasks.integration_id', '=', 'integrations.id')
            ->where('integrations.user_id', $this->id)
            ->select('integration_task_runs.*');
    }
}
