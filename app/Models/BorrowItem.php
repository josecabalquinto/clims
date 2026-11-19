<?php

namespace App\Models;

use Database\Factories\BorrowItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $borrow_transaction_id
 * @property int $equipment_id
 * @property int $quantity
 * @property int $returned_quantity
 */
#[Fillable([])]
class BorrowItem extends Model
{
    /** @use HasFactory<BorrowItemFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<BorrowTransaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BorrowTransaction::class, 'borrow_transaction_id');
    }

    /** @return BelongsTo<Equipment, $this> */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /** @return HasMany<BorrowReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(BorrowReturn::class);
    }

    public function outstandingQuantity(): int
    {
        return $this->quantity - $this->returned_quantity;
    }
}
