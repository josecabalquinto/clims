<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BorrowReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $borrow_item_id
 * @property int $quantity
 * @property int $defective_quantity
 * @property int $recorded_by
 * @property CarbonImmutable $returned_at
 * @property string|null $remarks
 */
#[Fillable([])]
class BorrowReturn extends Model
{
    /** @use HasFactory<BorrowReturnFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'defective_quantity' => 'integer',
            'returned_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<BorrowItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(BorrowItem::class, 'borrow_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
