<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'school_university' => fake()->company(),
            'course_year_level' => fake()->randomElement(['BS IT', 'BS Education', 'BS Nursing']),
            'year_level' => fake()->randomElement(['1st Year', '2nd Year', '3rd Year', '4th Year']),
            'cellphone_number' => fake()->phoneNumber(),
            'status' => 'approved',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            if (! $user->email) {
                $user->email = fake()->unique()->safeEmail();
            }
            if (! $user->scholar_id) {
                $user->scholar_id = 'BS-'.fake()->unique()->numerify('####');
            }
            if (! $user->password) {
                $user->password = 'password';
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
