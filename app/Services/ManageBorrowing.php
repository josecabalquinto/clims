<?php

namespace App\Services;

use App\Enums\BorrowerType;
use App\Enums\BorrowStatus;
use App\Models\BorrowItem;
use App\Models\BorrowReturn;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageBorrowing
{
    public function __construct(private ActivityLogger $logger) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): BorrowTransaction
    {
        $actor->refresh();
        Gate::forUser($actor)->authorize('create', BorrowTransaction::class);
        $data = $this->normalizeText($data, ['borrower_name', 'borrower_identifier', 'grade_level', 'section', 'remarks']);
        $validated = Validator::make($data, [
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrower_type' => ['required', Rule::enum(BorrowerType::class)],
            'borrower_identifier' => ['exclude_unless:borrower_type,student', 'required', 'digits:12'],
            'grade_level' => ['exclude_unless:borrower_type,student', 'required', 'string', 'max:50'],
            'section' => ['exclude_unless:borrower_type,student', 'required', 'string', 'max:100'],
            'borrowed_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'expected_return_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:borrowed_at'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:equipment_id,quantity'],
            'items.*.equipment_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ], [
            'items.*.equipment_id.distinct' => 'Select each equipment record only once.',
            'expected_return_at.after' => 'The return deadline must be after the borrowing time.',
        ])->validate();

        $borrowedAt = $this->utc($validated['borrowed_at']);
        $dueAt = $this->utc($validated['expected_return_at']);
        if ($borrowedAt->isFuture()) {
            throw ValidationException::withMessages(['borrowed_at' => 'Borrowing time cannot be in the future.']);
        }

        return DB::transaction(function () use ($actor, $validated, $borrowedAt, $dueAt): BorrowTransaction {
            $equipmentIds = array_map(fn (array $item): int => (int) $item['equipment_id'], $validated['items']);
            $equipment = Equipment::whereIn('id', $equipmentIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor->refresh();
            Gate::forUser($actor)->authorize('create', BorrowTransaction::class);

            foreach ($validated['items'] as $index => $line) {
                $stock = $equipment->get((int) $line['equipment_id']);
                if (! $stock) {
                    throw ValidationException::withMessages(["items.{$index}.equipment_id" => 'This equipment record is no longer available.']);
                }
                if ((int) $line['quantity'] > $stock->available_quantity) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => "Only {$stock->available_quantity} units of {$stock->name} are available."]);
                }
            }

            $transaction = new BorrowTransaction;
            $transaction->fill(collect($validated)->except(['items', 'borrowed_at', 'expected_return_at'])->all());
            $transaction->borrowed_at = $borrowedAt;
            $transaction->expected_return_at = $dueAt;
            $transaction->recorded_by = $actor->id;
            $transaction->status = BorrowStatus::Borrowed;
            $transaction->save();
            $this->logger->record($actor, 'borrow_transaction.created', $transaction, [], $transaction->attributesToArray());

            foreach ($validated['items'] as $line) {
                $stock = $equipment->get((int) $line['equipment_id']);
                $old = ['available_quantity' => $stock->available_quantity];
                $item = new BorrowItem;
                $item->borrow_transaction_id = $transaction->id;
                $item->equipment_id = $stock->id;
                $item->quantity = (int) $line['quantity'];
                $item->returned_quantity = 0;
                $item->save();
                $stock->available_quantity -= $item->quantity;
                $stock->save();
                $this->logger->record($actor, 'borrow_item.created', $item, [], $item->attributesToArray());
                $this->logger->record($actor, 'equipment.borrowed', $stock, $old, ['available_quantity' => $stock->available_quantity]);
            }

            return $transaction->load('items.equipment');
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function returnEquipment(User $actor, BorrowTransaction $subject, array $data): BorrowTransaction
    {
        $actor->refresh();
        Gate::forUser($actor)->authorize('returnEquipment', $subject);
        $data = $this->normalizeText($data, ['remarks']);
        $validated = Validator::make($data, [
            'returned_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'items.*' => ['required', 'array:borrow_item_id,quantity,defective_quantity'],
            'items.*.borrow_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'items.*.defective_quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ])->validate();
        $returnedAt = $this->utc($validated['returned_at']);
        if ($returnedAt->isFuture()) {
            throw ValidationException::withMessages(['returned_at' => 'Return time cannot be in the future.']);
        }

        // Borrow items are immutable. Read their equipment IDs first so every stock
        // operation locks equipment in ascending order before transaction/items.
        $equipmentIds = $subject->items()->pluck('equipment_id')->all();

        return DB::transaction(function () use ($actor, $subject, $validated, $returnedAt, $equipmentIds): BorrowTransaction {
            $equipment = Equipment::whereIn('id', $equipmentIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $transaction = BorrowTransaction::whereKey($subject->id)->lockForUpdate()->firstOrFail();
            $items = $transaction->items()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor->refresh();
            Gate::forUser($actor)->authorize('returnEquipment', $transaction);

            if ($returnedAt->lessThan($transaction->borrowed_at)) {
                throw ValidationException::withMessages(['returned_at' => 'Return time cannot be before the borrowing time.']);
            }

            $totalReturning = 0;
            foreach ($validated['items'] as $index => $line) {
                $item = $items->get((int) $line['borrow_item_id']);
                if (! $item || ! $equipment->has($item->equipment_id)) {
                    throw ValidationException::withMessages(["items.{$index}.borrow_item_id" => 'Choose an item from this borrowing transaction.']);
                }
                $quantity = (int) $line['quantity'];
                $defective = (int) $line['defective_quantity'];
                if ($quantity > $item->outstandingQuantity()) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => "Only {$item->outstandingQuantity()} units remain outstanding for this item."]);
                }
                if ($defective > $quantity) {
                    throw ValidationException::withMessages(["items.{$index}.defective_quantity" => 'Defective units cannot exceed the quantity being returned.']);
                }
                if ($quantity > $equipment->get($item->equipment_id)->outstandingQuantity()) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => 'Stock is inconsistent. Review the equipment record before processing this return.']);
                }
                $totalReturning += $quantity;
            }
            if ($totalReturning === 0) {
                throw ValidationException::withMessages(['items' => 'Enter a return quantity for at least one item.']);
            }

            $latestReturn = BorrowReturn::whereIn('borrow_item_id', $items->modelKeys())
                ->orderByDesc('returned_at')->orderByDesc('id')->lockForUpdate()->first();
            $completedAt = $latestReturn && $latestReturn->returned_at->greaterThan($returnedAt)
                ? $latestReturn->returned_at : $returnedAt;
            $oldTransaction = ['status' => $transaction->status->value, 'returned_at' => $transaction->returned_at?->toIso8601String()];

            foreach ($validated['items'] as $line) {
                $quantity = (int) $line['quantity'];
                if ($quantity === 0) {
                    continue;
                }
                $item = $items->get((int) $line['borrow_item_id']);
                $stock = $equipment->get($item->equipment_id);
                $oldStock = $stock->only(['available_quantity', 'defective_quantity']);
                $oldItem = ['returned_quantity' => $item->returned_quantity];
                $return = new BorrowReturn;
                $return->borrow_item_id = $item->id;
                $return->quantity = $quantity;
                $return->defective_quantity = (int) $line['defective_quantity'];
                $return->recorded_by = $actor->id;
                $return->returned_at = $returnedAt;
                $return->remarks = $validated['remarks'] ?? null;
                $return->save();
                $item->returned_quantity += $quantity;
                $item->save();
                $stock->available_quantity += $quantity - $return->defective_quantity;
                $stock->defective_quantity += $return->defective_quantity;
                $stock->save();

                $this->logger->record($actor, 'borrow_return.created', $return, [], $return->attributesToArray());
                $this->logger->record($actor, 'borrow_item.updated', $item, $oldItem, ['returned_quantity' => $item->returned_quantity]);
                $this->logger->record($actor, 'equipment.returned', $stock, $oldStock, $stock->only(['available_quantity', 'defective_quantity']));
            }

            $transaction->setRelation('items', $items->values());
            $transaction->status = $transaction->returnStatus();
            $transaction->returned_at = $transaction->status === BorrowStatus::Returned ? $completedAt : null;
            $transaction->save();
            $this->logger->record($actor, 'borrow_transaction.return_recorded', $transaction, $oldTransaction, [
                'status' => $transaction->status->value, 'returned_at' => $transaction->returned_at?->toIso8601String(),
            ]);

            return $transaction->load('items.equipment');
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data
     * @param  list<string>  $fields
     * @return array<string, mixed>
     */
    private function normalizeText(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (is_string($data[$field] ?? null)) {
                $data[$field] = trim($data[$field]);
                if ($data[$field] === '') {
                    $data[$field] = null;
                }
            }
        }

        return $data;
    }

    private function utc(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, config('clims.display_timezone'))->utc();
    }
}
