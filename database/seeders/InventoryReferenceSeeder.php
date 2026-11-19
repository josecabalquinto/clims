<?php

namespace Database\Seeders;

use App\Models\EquipmentCategory;
use App\Models\EquipmentLocation;
use Illuminate\Database\Seeder;

class InventoryReferenceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) && ! config('nativephp-internal.running')) {
            return;
        }

        foreach (['System Unit', 'Monitor', 'Keyboard', 'Mouse', 'Printer', 'UPS', 'Projector', 'Network Equipment', 'Others'] as $name) {
            EquipmentCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        EquipmentLocation::updateOrCreate(
            ['name' => EquipmentLocation::COMPUTER_LAB_TWO],
            ['description' => 'The laboratory managed by CLIMS.', 'is_active' => true],
        );
    }
}
