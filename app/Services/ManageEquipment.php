<?php

namespace App\Services;

use App\Enums\EquipmentCondition;
use App\Models\BorrowItem;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageEquipment
{
    public function __construct(private ActivityLogger $logger) {}

    /** @param array<string, mixed> $data */
    public function save(User $actor, array $data, ?Equipment $equipment = null): Equipment
    {
        try {
            return DB::transaction(function () use ($actor, $data, $equipment): Equipment {
                $actor->refresh();
                Gate::forUser($actor)->authorize($equipment ? 'update' : 'create', $equipment ?? Equipment::class);
                $equipment = $equipment
                    ? Equipment::whereKey($equipment->id)->lockForUpdate()->firstOrFail()
                    : new Equipment;

                foreach (['asset_tag', 'name', 'serial_number', 'description', 'remarks'] as $field) {
                    if (is_string($data[$field] ?? null)) {
                        $data[$field] = trim($data[$field]);
                    }
                }
                if (is_string($data['asset_tag'] ?? null)) {
                    $data['asset_tag'] = Str::upper($data['asset_tag']);
                }
                foreach (['serial_number', 'description', 'remarks', 'date_acquired'] as $field) {
                    if (($data[$field] ?? null) === '') {
                        $data[$field] = null;
                    }
                }

                $validated = Validator::make($data, [
                    'asset_tag' => ['required', 'string', 'max:100', Rule::unique(Equipment::class, 'asset_tag')->ignore($equipment->getKey())],
                    'name' => ['required', 'string', 'max:255'],
                    'category_id' => ['required', 'integer', 'min:1'],
                    'description' => ['nullable', 'string', 'max:5000'],
                    'serial_number' => ['nullable', 'string', 'max:255', Rule::prohibitedIf((int) ($data['quantity'] ?? 0) !== 1)],
                    'quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
                    'defective_quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
                    'condition' => ['required', Rule::enum(EquipmentCondition::class)],
                    'date_acquired' => ['nullable', 'date_format:Y-m-d'],
                    'remarks' => ['nullable', 'string', 'max:5000'],
                ], [
                    'serial_number.prohibited' => 'A serial number can only be assigned to an individually tracked item with quantity 1.',
                ])->validate();

                // Catalog changes and equipment assignments take the same row locks.
                // An inactive reference may only be kept on its existing equipment.
                $category = EquipmentCategory::whereKey($validated['category_id'])->lockForUpdate()->first();
                $location = EquipmentLocation::where('name', EquipmentLocation::COMPUTER_LAB_TWO)->lockForUpdate()->first();
                if (! $category || (! $category->is_active && $equipment->category_id !== $category->id)) {
                    throw ValidationException::withMessages(['category_id' => 'Select an active equipment category.']);
                }
                if (! $location || ! $location->is_active) {
                    throw ValidationException::withMessages(['category_id' => 'Computer Lab 2 is not configured. Run the latest database migration.']);
                }

                // Use locking reads for borrow rows too: an earlier validation or
                // actor read may already have established a MySQL MVCC snapshot.
                // Borrowing/return operations must also lock equipment first.
                $outstanding = $equipment->exists
                    ? (int) $equipment->borrowItems()
                        ->whereColumn('returned_quantity', '<', 'quantity')
                        ->orderBy('id')->lockForUpdate()->get(['id', 'quantity', 'returned_quantity'])
                        ->sum(fn (BorrowItem $item): int => $item->outstandingQuantity())
                    : 0;
                $quantity = (int) $validated['quantity'];
                $defective = (int) $validated['defective_quantity'];
                if ($quantity < $outstanding) {
                    throw ValidationException::withMessages(['quantity' => "Total quantity cannot be lower than the {$outstanding} units currently borrowed."]);
                }
                if ($defective > $quantity - $outstanding) {
                    throw ValidationException::withMessages(['defective_quantity' => 'Defective quantity cannot exceed the units currently on hand.']);
                }

                $creating = ! $equipment->exists;
                $old = $equipment->attributesToArray();
                // Counter inputs are deliberately excluded from mass assignment.
                $equipment->fill(collect($validated)->except(['quantity', 'defective_quantity'])->all());
                $equipment->location_id = $location->id;
                $equipment->quantity = $quantity;
                $equipment->defective_quantity = $defective;
                $equipment->available_quantity = $quantity - $outstanding - $defective;

                if ($creating || $equipment->isDirty()) {
                    $changed = array_keys($equipment->getDirty());
                    $equipment->save();
                    $new = $equipment->attributesToArray();
                    $this->logger->record($actor, $creating ? 'equipment.created' : 'equipment.updated', $equipment,
                        $creating ? [] : array_intersect_key($old, array_flip($changed)),
                        $creating ? $new : array_intersect_key($new, array_flip($changed)));
                }

                return $equipment;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['asset_tag' => 'This asset tag is already in use.']);
        }
    }
}
