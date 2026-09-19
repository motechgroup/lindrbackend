<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PhotoService
{
    /**
     * Upload and store a new photo for a user.
     */
    public function uploadPhoto(User $user, UploadedFile $file, bool $isPrimary = false): UserPhoto
    {
        $path = $file->store("photos/{$user->id}", 'public');

        $hasPrimary = UserPhoto::where('user_id', $user->id)->where('is_primary', true)->exists();
        if (! $hasPrimary) {
            $isPrimary = true;
        }

        if ($isPrimary) {
            UserPhoto::where('user_id', $user->id)->update(['is_primary' => false]);
        }

        $nextOrder = (UserPhoto::where('user_id', $user->id)->max('display_order') ?? 0) + 1;

        return UserPhoto::create([
            'user_id' => $user->id,
            'photo_path' => $path,
            'is_primary' => $isPrimary,
            'display_order' => $nextOrder,
            'is_active' => true,
        ]);
    }

    /**
     * Delete a photo belonging to the user.
     */
    public function deletePhoto(User $user, UserPhoto $photo): bool
    {
        if ($photo->user_id !== $user->id) {
            throw new \InvalidArgumentException('Unauthorized to delete this photo.');
        }

        if (Storage::disk('public')->exists($photo->photo_path)) {
            Storage::disk('public')->delete($photo->photo_path);
        }

        $wasPrimary = $photo->is_primary;
        $photo->delete();

        if ($wasPrimary) {
            $nextPhoto = UserPhoto::where('user_id', $user->id)->orderBy('display_order', 'asc')->first();
            if ($nextPhoto) {
                $nextPhoto->update(['is_primary' => true]);
            }
        }

        return true;
    }

    /**
     * Set a photo as primary for the user.
     */
    public function setPrimaryPhoto(User $user, UserPhoto $photo): UserPhoto
    {
        if ($photo->user_id !== $user->id) {
            throw new \InvalidArgumentException('Unauthorized to modify this photo.');
        }

        UserPhoto::where('user_id', $user->id)->update(['is_primary' => false]);
        $photo->update(['is_primary' => true]);

        return $photo;
    }
}
