<?php

namespace App\Models;

use App\Enums\BorrowerType;
use App\Enums\BorrowStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BorrowTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $borrower_name
 * @property BorrowerType $borrower_type
 * @property string|null $borrower_identifier
 * @property string|null $grade_level
 * @property string|null $section
 * @property CarbonImmutable $borrowed_at
 * @property CarbonImmutable $expected_return_at
 * @property CarbonImmutable|null $returned_at
 * @property BorrowStatus $status
 * @property string|null $remarks
 * @property int $recorded_by
 */
#[Fillable(['borrower_name', 'borrower_type', 'borrower_identifier', 'grade_level', 'section', 'borrowed_at', 'expected_return_at', 'remarks'])]
class BorrowTransaction extends Model
{
    /** @use HasFactory<BorrowTransactionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'borrower_type' => BorrowerType::class,
            'status' => BorrowStatus::class,
            'borrowed_at' => 'immutable_datetime',
            'expected_return_at' => 'immutable_datetime',
            'returned_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return HasMany<BorrowItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BorrowItem::class);
    }

    public function isOverdue(): bool
    {
        return $this->expected_return_at->isPast() && ($this->relationLoaded('items')
            ? $this->items->contains(fn (BorrowItem $item): bool => $item->outstandingQuantity() > 0)
            : $this->items()->whereColumn('returned_quantity', '<', 'quantity')->exists());
    }

    public function returnStatus(): BorrowStatus
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $quantity = (int) $items->sum('quantity');
        $returned = (int) $items->sum('returned_quantity');

        return match (true) {
            $quantity > 0 && $returned >= $quantity => BorrowStatus::Returned,
            $returned > 0 => BorrowStatus::PartiallyReturned,
            default => BorrowStatus::Borrowed,
        };
    }

    public function displayStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->returnStatus()->value;
    }

    public function statusLabel(): string
    {
        return $this->isOverdue() ? 'Overdue' : $this->returnStatus()->label();
    }

    public function outstandingQuantity(): int
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return (int) $items->sum(fn (BorrowItem $item): int => $item->outstandingQuantity());
    }
}
