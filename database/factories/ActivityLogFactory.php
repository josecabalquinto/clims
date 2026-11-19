<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActivityLog> */
class ActivityLogFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => UserFactory::new(),
            'action' => 'user.created',
            'subject_type' => 'user',
            'subject_id' => UserFactory::new(),
            'old_values' => [],
            'new_values' => [],
            'created_at' => now(),
        ];
    }
}
