<?php

namespace App\Http\Controllers;

use App\Enums\BorrowerType;
use App\Models\BorrowItem;
use App\Models\BorrowTransaction;
use App\Services\XlsxExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BorrowLogReportController extends Controller
{
    public function print(Request $request): View
    {
        return view('reports.borrow-log', ['transactions' => $this->query($request)->get()]);
    }

    public function export(Request $request, XlsxExporter $exporter): BinaryFileResponse
    {
        $rows = $this->query($request)->cursor()->flatMap(fn (BorrowTransaction $transaction) => $transaction->items->map(
            fn (BorrowItem $item): array => [
                $transaction->borrower_name,
                $transaction->borrower_type->label(),
                $transaction->borrower_identifier,
                $transaction->grade_level,
                $transaction->section,
                $item->equipment->name,
                $item->quantity,
                $transaction->borrowed_at->setTimezone(config('clims.display_timezone'))->format('Y-m-d H:i'),
                $item->returns->max('returned_at')?->setTimezone(config('clims.display_timezone'))->format('Y-m-d H:i'),
                $transaction->statusLabel(),
            ],
        ));

        return $exporter->download('clims-borrow-log-report.xlsx', [
            'Borrower name', 'Borrower type', 'LRN', 'Grade level', 'Section', 'Equipment', 'Quantity', 'Borrow date', 'Return date', 'Status',
        ], $rows);
    }

    /** @return Builder<BorrowTransaction> */
    private function query(Request $request): Builder
    {
        $search = mb_substr(trim($request->string('search')->toString()), 0, 255);
        $borrowerType = BorrowerType::tryFrom($request->string('borrower_type')->toString())?->value;
        $status = in_array($request->string('status')->toString(), ['all', 'borrowed', 'partially_returned', 'overdue', 'returned'], true)
            ? $request->string('status')->toString() : 'all';

        $query = BorrowTransaction::query()->with(['items.equipment', 'items.returns'])
            ->when($search !== '', fn (Builder $builder) => $builder->where(fn (Builder $match) => $match
                ->where('borrower_name', 'like', '%'.$search.'%')
                ->orWhere('borrower_identifier', 'like', '%'.$search.'%')
                ->orWhereHas('items.equipment', fn (Builder $equipment) => $equipment->where('name', 'like', '%'.$search.'%')->orWhere('asset_tag', 'like', '%'.$search.'%'))))
            ->when($borrowerType !== null, fn (Builder $builder) => $builder->where('borrower_type', $borrowerType));

        if (in_array($status, ['borrowed', 'partially_returned', 'overdue'], true)) {
            $query->whereHas('items', fn (Builder $items) => $items->whereColumn('returned_quantity', '<', 'quantity'));
            if ($status === 'overdue') {
                $query->where('expected_return_at', '<', now());
            } else {
                $query->where('expected_return_at', '>=', now());
                $status === 'borrowed'
                    ? $query->whereDoesntHave('items', fn (Builder $items) => $items->where('returned_quantity', '>', 0))
                    : $query->whereHas('items', fn (Builder $items) => $items->where('returned_quantity', '>', 0));
            }
        } elseif ($status === 'returned') {
            $query->has('items')->whereDoesntHave('items', fn (Builder $items) => $items->whereColumn('returned_quantity', '<', 'quantity'));
        }

        return $query->orderByDesc('borrowed_at')->orderByDesc('id');
    }
}
