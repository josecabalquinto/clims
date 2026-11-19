<?php

namespace App\Livewire\Equipment;

use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use App\Models\User;
use App\Services\ManageEquipmentReference;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

abstract class ReferenceManager extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $status = 'all';

    #[Locked]
    public ?int $editingId = null;

    public bool $showEditor = false;

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    /** @return class-string<EquipmentCategory>|class-string<EquipmentLocation> */
    abstract protected function referenceClass(): string;

    public function boot(): void
    {
        Gate::authorize('viewAny', $this->referenceClass());
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    /** @return LengthAwarePaginator<int, EquipmentCategory>|LengthAwarePaginator<int, EquipmentLocation> */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        $query = ($this->referenceClass())::query()->withCount('equipment');
        if ($this->search !== '') {
            $query->where('name', 'like', '%'.$this->search.'%');
        }
        if (in_array($this->status, ['active', 'inactive'], true)) {
            $query->where('is_active', $this->status === 'active');
        }

        return $query->orderBy('name')->orderBy('id')->paginate(15);
    }

    public function create(): void
    {
        Gate::authorize('create', $this->referenceClass());
        $this->resetValidation();
        $this->reset('editingId', 'name', 'description', 'is_active');
        $this->showEditor = true;
    }

    public function edit(int $id): void
    {
        $record = ($this->referenceClass())::findOrFail($id);
        Gate::authorize('update', $record);
        $this->resetValidation();
        $this->editingId = $record->id;
        $this->name = $record->name;
        $this->description = $record->description ?? '';
        $this->is_active = $record->is_active;
        $this->showEditor = true;
    }

    public function save(ManageEquipmentReference $service): void
    {
        $class = $this->referenceClass();
        $record = $this->editingId ? $class::findOrFail($this->editingId) : new $class;
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $service->save($actor, $record, $this->only(['name', 'description', 'is_active']));
        $this->showEditor = false;
        $this->resetPage();
        unset($this->records);
        $this->dispatch('notify', type: 'success', message: __('Changes saved.'));
    }

    public function setActive(int $id, bool $active, ManageEquipmentReference $service): void
    {
        $record = ($this->referenceClass())::findOrFail($id);
        Gate::authorize('update', $record);
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $service->save($actor, $record, ['is_active' => $active]);
        unset($this->records);
        $this->dispatch('notify', type: 'success', message: $active ? __('Activated.') : __('Deactivated.'));
    }

    public function delete(int $id, ManageEquipmentReference $service): void
    {
        $this->resetValidation('deletion');
        $record = ($this->referenceClass())::findOrFail($id);
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $service->delete($actor, $record);
        $this->resetPage();
        unset($this->records);
        $this->dispatch('notify', type: 'success', message: __('Entry deleted.'));
    }
}
