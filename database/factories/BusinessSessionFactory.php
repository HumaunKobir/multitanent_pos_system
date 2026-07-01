<?php

namespace Database\Factories;

use App\Enums\BusinessSessionOpeningMethod;
use App\Enums\BusinessSessionStatus;
use App\Models\Branch;
use App\Models\BusinessSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessSession>
 */
class BusinessSessionFactory extends Factory
{
    protected $model = BusinessSession::class;

    public function definition(): array
    {
        $startedAt = now()->subHours(2);

        return [
            'session_number' => 'TEST-'.$this->faker->unique()->numerify('####'),
            'session_date' => $startedAt->toDateString(),
            'branch_id' => null,
            'started_by_user_id' => User::factory(),
            'closed_by_user_id' => null,
            'started_at' => $startedAt,
            'closed_at' => null,
            'opening_method' => BusinessSessionOpeningMethod::ManualFromPanel,
            'status' => BusinessSessionStatus::Open,
            'total_opening_balance' => 0,
            'total_closing_balance' => null,
            'report_snapshot' => null,
        ];
    }

    public function forBranch(?Branch $branch = null): static
    {
        return $this->state(fn () => [
            'branch_id' => $branch?->id ?? Branch::factory(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => BusinessSessionStatus::Closed,
            'closed_at' => now(),
            'total_closing_balance' => 0,
        ]);
    }
}
