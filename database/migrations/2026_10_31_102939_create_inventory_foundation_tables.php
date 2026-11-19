<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['equipment_categories', 'equipment_locations'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('equipment_categories')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('equipment_locations')->restrictOnDelete();
            $table->string('asset_tag')->unique();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->string('serial_number')->nullable()->index();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('available_quantity');
            $table->unsignedInteger('defective_quantity')->default(0);
            $table->string('condition', 50)->default('good');
            $table->date('date_acquired')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('borrow_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('borrower_name')->index();
            $table->enum('borrower_type', ['student', 'instructor', 'staff', 'other']);
            $table->string('borrower_identifier')->nullable();
            $table->dateTime('borrowed_at');
            $table->dateTime('expected_return_at')->index();
            $table->dateTime('returned_at')->nullable();
            $table->enum('status', ['borrowed', 'partially_returned', 'returned'])->default('borrowed');
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'expected_return_at']);
        });

        Schema::create('borrow_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->timestamps();
            $table->unique(['borrow_transaction_id', 'equipment_id']);
        });

        Schema::create('borrow_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrow_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('defective_quantity')->default(0);
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('returned_at')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action', 100)->index();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });

        $this->addCheck('equipment', 'equipment_quantities_valid',
            'quantity >= 0 AND available_quantity >= 0 AND defective_quantity >= 0 AND available_quantity + defective_quantity <= quantity');
        $this->addCheck('equipment', 'equipment_serial_is_individual',
            'serial_number IS NULL OR quantity = 1');
        $this->addCheck('borrow_items', 'borrow_item_quantities_valid',
            'quantity > 0 AND returned_quantity >= 0 AND returned_quantity <= quantity');
        $this->addCheck('borrow_returns', 'borrow_return_quantities_valid',
            'quantity > 0 AND defective_quantity >= 0 AND defective_quantity <= quantity');
        $this->addCheck('borrow_transactions', 'borrow_dates_valid',
            'expected_return_at >= borrowed_at AND (returned_at IS NULL OR returned_at >= borrowed_at)');
    }

    public function down(): void
    {
        foreach (['activity_logs', 'borrow_returns', 'borrow_items', 'borrow_transactions', 'equipment', 'equipment_locations', 'equipment_categories'] as $name) {
            Schema::dropIfExists($name);
        }
    }

    private function addCheck(string $table, string $name, string $expression): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite cannot add CHECK constraints to existing tables; equivalent
            // triggers apply the same quantity rules to the in-memory test schema.
            $columns = Schema::getColumnListing($table);
            $sqliteExpression = preg_replace_callback('/\\b[a-z_]+\\b/',
                fn (array $match): string => in_array($match[0], $columns, true) ? 'NEW.'.$match[0] : $match[0],
                $expression);

            foreach (['INSERT', 'UPDATE'] as $event) {
                DB::statement("CREATE TRIGGER {$name}_{$event} BEFORE {$event} ON {$table}
                    FOR EACH ROW WHEN NOT ({$sqliteExpression})
                    BEGIN SELECT RAISE(ABORT, '{$name}'); END");
            }

            return;
        }

        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
    }
};
