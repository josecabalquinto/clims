<?php

namespace App\Services;

use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageEquipmentReference
{
    public function __construct(private ActivityLogger $logger) {}

    public function delete(User $actor, EquipmentCategory|EquipmentLocation $reference): void
    {
        DB::transaction(function () use ($actor, $reference): void {
            // Assignments take the same parent lock. Read related equipment
            // with a lock too, in case a caller already holds a MySQL snapshot.
            $reference = $reference->newQuery()->whereKey($reference->id)->lockForUpdate()->firstOrFail();
            $actor->refresh();
            Gate::forUser($actor)->authorize('update', $reference);

            if ($reference->equipment()->orderBy('id')->lockForUpdate()->first(['id'])) {
                throw ValidationException::withMessages([
                    'deletion' => 'This entry is assigned to equipment and cannot be deleted. Deactivate it instead.',
                ]);
            }

            Gate::forUser($actor)->authorize('delete', $reference);
            $old = $reference->only(['name', 'description', 'is_active']);
            $reference->delete();
            $this->logger->record($actor, $reference->getMorphClass().'.deleted', $reference, $old);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function save(User $actor, EquipmentCategory|EquipmentLocation $reference, array $data): EquipmentCategory|EquipmentLocation
    {
        try {
            return DB::transaction(function () use ($actor, $reference, $data): EquipmentCategory|EquipmentLocation {
                $actor->refresh();
                Gate::forUser($actor)->authorize($reference->exists ? 'update' : 'create', $reference->exists ? $reference : $reference::class);
                $reference = $reference->exists
                    ? $reference->newQuery()->whereKey($reference->id)->lockForUpdate()->firstOrFail()
                    : $reference;

                foreach (['name', 'description'] as $field) {
                    if (is_string($data[$field] ?? null)) {
                        $data[$field] = trim($data[$field]);
                    }
                }
                if (($data['description'] ?? null) === '') {
                    $data['description'] = null;
                }

                $validated = Validator::make($data, [
                    'name' => [$reference->exists ? 'sometimes' : 'required', 'required', 'string', 'max:255', Rule::unique($reference::class, 'name')->ignore($reference->getKey())],
                    'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
                    'is_active' => ['sometimes', 'required', 'boolean'],
                ])->validate();

                $creating = ! $reference->exists;
                $old = $reference->only(['name', 'description', 'is_active']);
                $reference->fill($validated);
                if ($creating && ! array_key_exists('is_active', $validated)) {
                    $reference->is_active = true;
                }

                if ($creating || $reference->isDirty()) {
                    $changed = array_keys($reference->getDirty());
                    $reference->save();
                    $new = $reference->only(['name', 'description', 'is_active']);
                    $this->logger->record($actor, $reference->getMorphClass().($creating ? '.created' : '.updated'), $reference,
                        $creating ? [] : array_intersect_key($old, array_flip($changed)),
                        $creating ? $new : array_intersect_key($new, array_flip($changed)));
                }

                return $reference;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'This name is already in use.']);
        }
    }
}
