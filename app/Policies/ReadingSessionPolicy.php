<?php

namespace App\Policies;

use App\Models\ReadingSession;
use App\Models\User;

class ReadingSessionPolicy
{
    /**
     * Determine whether the user can view the reading session.
     */
    public function view(User $user, ReadingSession $readingSession): bool
    {
        return $readingSession->user_id === $user->id;
    }

    /**
     * Determine whether the user can record or update the reading session.
     */
    public function update(User $user, ReadingSession $readingSession): bool
    {
        return $readingSession->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the reading session.
     */
    public function delete(User $user, ReadingSession $readingSession): bool
    {
        return $readingSession->user_id === $user->id;
    }
}
