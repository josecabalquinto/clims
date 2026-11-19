<?php

use App\Models\BorrowItem;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('the admin can open the equipment registration and detail pages', function () {
    $equipment = Equipment::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('equipment.index'))->assertOk()->assertSee('QR label');
    $this->get(route('equipment.create'))->assertOk()->assertSee('Register equipment');
    $this->get(route('equipment.show', $equipment))->assertOk()->assertSee($equipment->name)->assertSee('QR label');
    $this->get(route('equipment.edit', $equipment))->assertOk()->assertSee('Edit equipment');
});

test('the admin can register equipment through the Livewire form in Computer Lab 2', function () {
    $category = EquipmentCategory::factory()->create();
    $this->actingAs(User::factory()->create());

    $page = Livewire::test('pages::equipment.form')
        ->set('asset_tag', 'lab-001')
        ->set('name', 'Laboratory mouse')
        ->set('category_id', (string) $category->id)
        ->set('quantity', '20')
        ->set('defective_quantity', '2')
        ->call('save')
        ->assertHasNoErrors();

    $equipment = Equipment::sole();
    $page->assertRedirect(route('equipment.show', $equipment));
    $this->assertDatabaseHas('equipment', ['asset_tag' => 'LAB-001', 'quantity' => 20, 'available_quantity' => 18, 'defective_quantity' => 2]);
    expect($equipment->location->name)->toBe('Computer Lab 2');
    $this->assertDatabaseHas('activity_logs', ['action' => 'equipment.created', 'subject_id' => $equipment->id]);
});

test('a category can be created edited and deactivated through its editor', function () {
    $this->actingAs(User::factory()->create());

    $page = Livewire::test('pages::equipment.categories')
        ->call('create')->set('name', 'Monitors')->set('description', 'Laboratory displays')
        ->call('save')->assertHasNoErrors()->assertSet('showEditor', false);
    $category = EquipmentCategory::sole();
    $page->call('edit', $category->id)->set('name', 'Display monitors')->call('save')->assertHasNoErrors();
    $page->call('setActive', $category->id, false)->assertHasNoErrors();

    $this->assertDatabaseHas('equipment_categories', ['id' => $category->id, 'name' => 'Display monitors', 'is_active' => false]);
    $this->assertDatabaseCount('activity_logs', 3);
});

test('registration errors are shown beside the form fields', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::equipment.form')->call('save')
        ->assertHasErrors(['asset_tag', 'name', 'category_id'])
        ->assertSee('The asset tag field is required.');

    $this->assertDatabaseCount('equipment', 0);
    $this->assertDatabaseCount('activity_logs', 0);
});

test('the admin can edit equipment through the form without changing its identity', function () {
    $equipment = Equipment::factory()->create(['asset_tag' => 'LAB-EDIT']);
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::equipment.form', ['equipment' => $equipment])
        ->assertSet('equipmentId', $equipment->id)
        ->set('name', 'Updated monitor')->set('quantity', '3')
        ->call('save')->assertHasNoErrors()->assertRedirect(route('equipment.show', $equipment));

    $this->assertDatabaseCount('equipment', 1);
    $this->assertDatabaseHas('equipment', ['id' => $equipment->id, 'asset_tag' => 'LAB-EDIT', 'name' => 'Updated monitor', 'quantity' => 3, 'available_quantity' => 3]);
    $this->assertDatabaseHas('activity_logs', ['action' => 'equipment.updated', 'subject_id' => $equipment->id]);
});

test('new forms show only active categories and edits include the current inactive category', function () {
    $activeCategory = EquipmentCategory::factory()->create(['name' => 'Active category']);
    $currentCategory = EquipmentCategory::factory()->inactive()->create(['name' => 'Current inactive category']);
    EquipmentCategory::factory()->inactive()->create(['name' => 'Other inactive category']);
    $equipment = Equipment::factory()->create(['category_id' => $currentCategory->id]);
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::equipment.form')
        ->assertSee($activeCategory->name)->assertDontSee('Current inactive category');
    Livewire::test('pages::equipment.form', ['equipment' => $equipment])
        ->assertSee('Current inactive category')->assertDontSee('Other inactive category');
});

test('equipment search remains grouped with the category filter', function () {
    $match = Equipment::factory()->create(['asset_tag' => 'DISPLAY-MATCH', 'name' => 'Office display']);
    $outside = Equipment::factory()->create(['asset_tag' => 'DISPLAY-OUTSIDE', 'name' => 'Display outside scope']);
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::equipment.index')->set('search', 'DISPLAY')
        ->set('category', (string) $match->category_id)
        ->assertSee($match->name)->assertDontSee($outside->name)
        ->call('clearFilters')->assertSee($outside->name);
});

test('availability filters distinguish borrowed defective and available stock', function (string $filter, string $expected, array $excluded) {
    Equipment::factory()->create(['name' => 'Available mouse']);
    $borrowed = Equipment::factory()->create(['name' => 'Borrowed monitor', 'available_quantity' => 0]);
    BorrowItem::factory()->for($borrowed)->create();
    Equipment::factory()->create(['name' => 'Defective keyboard', 'available_quantity' => 0, 'defective_quantity' => 1]);
    $this->actingAs(User::factory()->create());

    $page = Livewire::test('pages::equipment.index')->set('availability', $filter)->assertSee($expected);
    foreach ($excluded as $name) {
        $page->assertDontSee($name);
    }
})->with([
    'available' => ['available', 'Available mouse', ['Borrowed monitor', 'Defective keyboard']],
    'borrowed' => ['borrowed', 'Borrowed monitor', ['Available mouse', 'Defective keyboard']],
    'defective' => ['defective', 'Defective keyboard', ['Available mouse', 'Borrowed monitor']],
]);

test('condition filtering and serial number searches work', function () {
    Equipment::factory()->create(['name' => 'Fair display', 'condition' => 'fair', 'serial_number' => 'SERIAL-FIND']);
    Equipment::factory()->create(['name' => 'Good display']);
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::equipment.index')->set('condition', 'fair')->assertSee('Fair display')->assertDontSee('Good display')
        ->call('clearFilters')->set('search', 'SERIAL-FIND')->assertSee('Fair display')->assertDontSee('Good display');
});

test('search updates reset the equipment paginator', function () {
    Equipment::factory()->count(16)->create(['name' => 'Ordinary keyboard']);
    Equipment::factory()->create(['name' => 'Unique projector']);
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::equipment.index')->call('setPage', 2)->assertSet('paginators.page', 2)
        ->set('search', 'Unique projector')->assertSet('paginators.page', 1)
        ->assertSee('Unique projector')->assertDontSee('Ordinary keyboard');
});

test('equipment searches bind SQL-like input as data', function () {
    Equipment::factory()->create(['name' => 'Protected inventory']);
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::equipment.index')->set('search', "' OR 1=1 --")
        ->assertSee('No equipment found')->assertDontSee('Protected inventory');
});

test('equipment and catalog text is escaped in rendered pages', function () {
    $payload = '<script>alert("inventory")</script>';
    $equipment = Equipment::factory()->create(['name' => $payload, 'description' => $payload, 'remarks' => $payload]);
    EquipmentCategory::factory()->create(['name' => $payload]);
    $this->actingAs(User::factory()->create());

    $this->get(route('equipment.show', $equipment))->assertOk()->assertSee($payload)->assertDontSee($payload, false);
    Livewire::test('pages::equipment.categories')->assertSeeHtml('&lt;script&gt;')->assertDontSeeHtml($payload);
});

test('guest and inactive access is refused on each new equipment route', function (string $name) {
    $equipment = Equipment::factory()->create();
    $parameters = $name === 'equipment.create' ? [] : ['equipment' => $equipment];
    $this->get(route($name, $parameters))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->inactive()->create())
        ->get(route($name, $parameters))->assertForbidden();
})->with(['equipment.create', 'equipment.show', 'equipment.edit']);

test('a forged equipment identity cannot change the form target', function () {
    $equipment = Equipment::factory()->create();
    $this->actingAs(User::factory()->create());

    expect(fn () => Livewire::test('pages::equipment.form')->set('equipmentId', $equipment->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('deactivation blocks a submitted equipment registration from an already open page', function () {
    $user = User::factory()->create();
    $category = EquipmentCategory::factory()->create();
    $page = $this->actingAs($user)->get(route('equipment.create'))->assertOk();
    preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
    $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    User::whereKey($user->id)->update(['is_active' => false]);

    $this->withHeader('X-Livewire', 'true')->postJson(Livewire::getUpdateUri(), [
        'components' => [[
            'snapshot' => $snapshot,
            'updates' => ['asset_tag' => 'BLOCKED', 'name' => 'Blocked item', 'category_id' => (string) $category->id],
            'calls' => [['path' => '', 'method' => 'save', 'params' => []]],
        ]],
    ])->assertForbidden();

    $this->assertDatabaseCount('equipment', 0);
    $this->assertDatabaseCount('activity_logs', 0);
});

test('catalog editors show duplicate name errors without closing the dialog', function () {
    EquipmentCategory::factory()->create(['name' => 'Existing category']);
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::equipment.categories')->call('create')->set('name', 'Existing category')
        ->call('save')->assertHasErrors('name')->assertSet('showEditor', true);

    $this->assertDatabaseCount('equipment_categories', 1);
    $this->assertDatabaseCount('activity_logs', 0);
});

test('catalog delete actions remove unused entries from the listing', function (string $page, string $model) {
    $actor = User::factory()->create();
    $record = $model::factory()->create(['name' => 'Delete this entry']);
    $this->actingAs($actor);

    Livewire::test($page)->assertSee('Delete this entry')->call('delete', $record->id)
        ->assertHasNoErrors()->assertDontSee('Delete this entry');

    $this->assertModelMissing($record);
    $this->assertDatabaseHas('activity_logs', ['action' => $record->getMorphClass().'.deleted', 'subject_id' => $record->id, 'user_id' => $actor->id]);
})->with(['category' => ['pages::equipment.categories', EquipmentCategory::class]]);

test('a new equipment assignment blocks deletion from an already opened catalog page', function (string $page, string $model, string $relationship) {
    $record = $model::factory()->create();
    $this->actingAs(User::factory()->create());
    $component = Livewire::test($page);
    $equipment = Equipment::factory()->for($record, $relationship)->create();

    $component->call('delete', $record->id)->assertHasErrors('deletion')
        ->assertSee('This entry is assigned to equipment and cannot be deleted. Deactivate it instead.');

    $this->assertModelExists($record);
    $this->assertModelExists($equipment);
    $this->assertDatabaseCount('activity_logs', 0);
})->with(['category' => ['pages::equipment.categories', EquipmentCategory::class, 'category']]);

test('catalog deletion resets pagination when the last entry on the page is removed', function () {
    $this->actingAs(User::factory()->create());
    EquipmentCategory::factory()->count(15)
        ->sequence(fn (Sequence $sequence) => ['name' => 'A-'.$sequence->index])
        ->create();
    $record = EquipmentCategory::factory()->create(['name' => 'Z last entry']);

    Livewire::test('pages::equipment.categories')->call('setPage', 2)->assertSet('paginators.page', 2)
        ->call('delete', $record->id)->assertHasNoErrors()->assertSet('paginators.page', 1);

    $this->assertModelMissing($record);
});
