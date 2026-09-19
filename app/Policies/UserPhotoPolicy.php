<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserPhoto;

class UserPhotoPolicy
{
    public function delete(User $user, UserPhoto $photo): bool
    {
        return $user->id === $photo->user_id;
    }

    public function update(User $user, UserPhoto $photo): bool
    {
        return $user->id === $photo->user_id;
    }
}
