<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        $url = filter_var($this->photo_path, FILTER_VALIDATE_URL)
            ? $this->photo_path
            : Storage::disk('public')->url($this->photo_path);

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'url' => $url,
            'is_primary' => (bool) $this->is_primary,
            'display_order' => $this->display_order,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
