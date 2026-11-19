<?php

namespace App\Models;

use Database\Factories\EquipmentLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 */
#[Fillable(['name', 'description', 'is_active'])]
class EquipmentLocation extends Model
{
    public const COMPUTER_LAB_TWO = 'Computer Lab 2';

    /** @use HasFactory<EquipmentLocationFactory> */
    use HasFactory;

    /** @param Builder<EquipmentLocation> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Equipment, $this> */
    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'location_id');
    }
}
