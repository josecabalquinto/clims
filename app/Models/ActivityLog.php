<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string $subject_type
 * @property int $subject_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property CarbonImmutable $created_at
 */
#[Fillable([])]
class ActivityLog extends Model
{
    /** @return list<string> */
    public static function allowedFields(string $type): array
    {
        return match ($type) {
            'user' => ['name', 'email', 'role', 'is_active'],
            'equipment' => ['category_id', 'location_id', 'asset_tag', 'name', 'description', 'serial_number', 'quantity', 'available_quantity', 'defective_quantity', 'condition', 'date_acquired', 'remarks'],
            'equipment_category', 'equipment_location' => ['name', 'description', 'is_active'],
            'borrow_transaction' => ['borrower_name', 'borrower_type', 'borrower_identifier', 'borrowed_at', 'expected_return_at', 'returned_at', 'status', 'recorded_by', 'remarks'],
            'borrow_item' => ['borrow_transaction_id', 'equipment_id', 'quantity', 'returned_quantity'],
            'borrow_return' => ['borrow_item_id', 'quantity', 'defective_quantity', 'recorded_by', 'returned_at', 'remarks'],
            default => [],
        };
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'equipment.created' => 'Equipment registered',
            'equipment.updated' => 'Equipment updated',
            'equipment.borrowed' => 'Equipment borrowed',
            'equipment.returned' => 'Equipment returned',
            'equipment_category.created' => 'Category added',
            'equipment_category.updated' => 'Category updated',
            'equipment_category.deleted' => 'Category deleted',
            'equipment_location.created' => 'Location added',
            'equipment_location.updated' => 'Location updated',
            'equipment_location.deleted' => 'Location deleted',
            'borrow_transaction.created' => 'Borrowing recorded',
            'borrow_transaction.return_recorded' => 'Return processed',
            'borrow_item.created' => 'Borrow item recorded',
            'borrow_item.updated' => 'Borrow item updated',
            'borrow_return.created' => 'Equipment return recorded',
            'user.created' => 'User created',
            'user.updated' => 'User updated',
            'user.access_updated' => 'User access updated',
            default => ucfirst(str_replace(['.', '_'], ' ', $action)),
        };
    }

    public function subjectLabel(): string
    {
        $type = match ($this->subject_type) {
            'user' => 'User', 'equipment' => 'Equipment',
            'equipment_category' => 'Category', 'equipment_location' => 'Location',
            'borrow_transaction' => 'Borrowing', 'borrow_item' => 'Borrow item',
            'borrow_return' => 'Return', default => 'Record',
        };
        foreach (['name', 'borrower_name', 'asset_tag'] as $key) {
            if (! in_array($key, self::allowedFields($this->subject_type), true)) {
                continue;
            }
            $value = $this->new_values[$key] ?? $this->old_values[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $type.' #'.$this->subject_id.' — '.$value;
            }
        }

        return $type.' #'.$this->subject_id;
    }

    /** @return list<array{field: string, before: string, after: string}> */
    public function displayChanges(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $changes = [];
        foreach (self::allowedFields($this->subject_type) as $field) {
            if (! array_key_exists($field, $old) && ! array_key_exists($field, $new)) {
                continue;
            }
            $changes[] = [
                'field' => ucfirst(str_replace('_', ' ', $field)),
                'before' => array_key_exists($field, $old) ? $this->displayValue($field, $old[$field]) : '—',
                'after' => array_key_exists($field, $new) ? $this->displayValue($field, $new[$field]) : '—',
            ];
        }

        return $changes;
    }

    private function displayValue(string $field, mixed $value): string
    {
        if ($value === null) {
            return 'Not set';
        }
        if (! is_scalar($value)) {
            return 'Structured value omitted';
        }
        if ($field === 'is_active') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Active' : 'Inactive';
        }
        if ($field === 'role') {
            return match ($value) {
                'super_admin' => 'Admin', 'lab_staff' => 'Former staff account', default => (string) $value
            };
        }
        if (in_array($field, ['status', 'borrower_type', 'condition'], true)) {
            return ucfirst(str_replace('_', ' ', (string) $value));
        }
        if (in_array($field, ['borrowed_at', 'expected_return_at', 'returned_at', 'date_acquired'], true) && is_string($value) && $value !== '') {
            try {
                $date = CarbonImmutable::parse($value, 'UTC');

                return $field === 'date_acquired' ? $date->format('M j, Y')
                    : $date->setTimezone(config('clims.display_timezone'))->format('M j, Y · g:i A');
            } catch (InvalidFormatException) {
                return $value;
            }
        }

        return (string) $value;
    }

    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Activity logs are append-only.'));
        static::deleting(fn () => throw new \LogicException('Activity logs are append-only.'));
    }
}
