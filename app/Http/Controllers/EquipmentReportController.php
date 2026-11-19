<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentCondition;
use App\Models\Equipment;
use App\Services\XlsxExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EquipmentReportController extends Controller
{
    public function print(Request $request): View
    {
        return view('reports.equipment', ['equipment' => $this->query($request)->get()]);
    }

    public function export(Request $request, XlsxExporter $exporter): BinaryFileResponse
    {
        $rows = $this->query($request)->cursor()->map(fn (Equipment $item): array => [
            $item->name,
            $item->category->name,
            $item->quantity,
            $item->available_quantity,
            $item->outstandingQuantity(),
            ucfirst($item->condition),
        ]);

        return $exporter->download('clims-equipment-report.xlsx', [
            'Equipment name', 'Category', 'Total quantity', 'Available quantity', 'Borrowed quantity', 'Condition',
        ], $rows);
    }

    /** @return Builder<Equipment> */
    private function query(Request $request): Builder
    {
        $search = mb_substr(trim($request->string('search')->toString()), 0, 255);
        $category = $request->integer('category');
        $availability = in_array($request->string('availability')->toString(), ['all', 'available', 'borrowed', 'defective'], true)
            ? $request->string('availability')->toString() : 'all';
        $condition = EquipmentCondition::tryFrom($request->string('condition')->toString())?->value;

        return Equipment::query()->with('category')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('name', 'like', '%'.$search.'%')
                ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', '%'.$search.'%'))))
            ->when($category > 0, fn (Builder $query) => $query->where('category_id', $category))
            ->when($condition !== null, fn (Builder $query) => $query->where('condition', $condition))
            ->when($availability === 'available', fn (Builder $query) => $query->where('available_quantity', '>', 0))
            ->when($availability === 'defective', fn (Builder $query) => $query->where('defective_quantity', '>', 0))
            ->when($availability === 'borrowed', fn (Builder $query) => $query->whereHas('borrowItems', fn (Builder $items) => $items->whereColumn('returned_quantity', '<', 'quantity')))
            ->orderBy('name')->orderBy('id');
    }
}
