<?php

use App\Livewire\Equipment\ReferenceManager;
use App\Models\EquipmentCategory;
use Livewire\Attributes\Title;

new #[Title('Equipment Categories')] class extends ReferenceManager {
    protected function referenceClass(): string
    {
        return EquipmentCategory::class;
    }
}; ?>

<x-equipment.reference-manager :title="__('Equipment Categories')" :description="__('Organize the types of equipment in your laboratory.')" :singular="__('category')" :records="$this->records" />

