<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_one_id', 'user_two_id'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_one_id' => 'integer',
            'user_two_id' => 'integer',
        ];
    }

    /**
     * Get the first participant of the conversation.
     *
     * @return BelongsTo<User, $this>
     */
    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /**
     * Get the second participant of the conversation.
     *
     * @return BelongsTo<User, $this>
     */
    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /**
     * Get the messages in the conversation.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the most recent message in the conversation.
     *
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Determine whether the given user participates in the conversation.
     */
    public function hasParticipant(int $userId): bool
    {
        return $this->user_one_id === $userId || $this->user_two_id === $userId;
    }

    /**
     * Get the participant of the conversation other than the given user.
     */
    public function otherParticipant(int $userId): User
    {
        return $this->user_one_id === $userId ? $this->userTwo : $this->userOne;
    }

    /**
     * Scope the query to conversations the given user participates in.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $query) use ($userId) {
            $query->where('user_one_id', $userId)->orWhere('user_two_id', $userId);
        });
    }

    /**
     * Find or create the conversation between the two given users.
     */
    public static function betweenUsers(int $userOneId, int $userTwoId): self
    {
        return static::firstOrCreate([
            'user_one_id' => min($userOneId, $userTwoId),
            'user_two_id' => max($userOneId, $userTwoId),
        ]);
    }
}
