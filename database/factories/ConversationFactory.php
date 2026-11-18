<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_one_id' => User::factory(),
            'user_two_id' => User::factory(),
        ];
    }

    /**
     * Indicate the conversation between the two given users.
     */
    public function between(User $first, User $second): static
    {
        return $this->state(fn () => [
            'user_one_id' => min($first->id, $second->id),
            'user_two_id' => max($first->id, $second->id),
        ]);
    }
}
