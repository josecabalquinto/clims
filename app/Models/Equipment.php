<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $category_id
 * @property int $location_id
 * @property string $asset_tag
 * @property string $name
 * @property string|null $description
 * @property string|null $serial_number
 * @property int $quantity
 * @property int $available_quantity
 * @property int $defective_quantity
 * @property string $condition
 * @property CarbonImmutable|null $date_acquired
 * @property string|null $remarks
 */
#[Fillable(['category_id', 'location_id', 'asset_tag', 'name', 'description', 'serial_number', 'condition', 'date_acquired', 'remarks'])]
class Equipment extends Model
{
    public const QR_PREFIX = 'CLIMS:equipment:';

    public function qrPayload(): string
    {
        return self::QR_PREFIX.$this->id;
    }

    /** @use HasFactory<EquipmentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'available_quantity' => 'integer',
            'defective_quantity' => 'integer',
            'date_acquired' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<EquipmentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'category_id');
    }

    /** @return BelongsTo<EquipmentLocation, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(EquipmentLocation::class, 'location_id');
    }

    /** @return HasMany<BorrowItem, $this> */
    public function borrowItems(): HasMany
    {
        return $this->hasMany(BorrowItem::class);
    }

    public function outstandingQuantity(): int
    {
        return $this->quantity - $this->available_quantity - $this->defective_quantity;
    }
}
