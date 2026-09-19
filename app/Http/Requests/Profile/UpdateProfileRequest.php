<?php

namespace App\Http\Requests\Profile;

use App\Models\PlatformSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxInterests = (int) PlatformSetting::get('max_user_interests', 10);

        return [
            'name' => ['nullable', 'string', 'min:3', 'max:50', Rule::unique('users', 'name')->ignore($this->user()?->id)],
            'username' => ['nullable', 'string', 'min:3', 'max:50', Rule::unique('users', 'name')->ignore($this->user()?->id)],
            'display_name' => ['nullable', 'string', 'min:2', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:-18 years'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'interest_ids' => ['nullable', 'array', "max:{$maxInterests}"],
            'interest_ids.*' => ['integer', 'exists:interests,id'],
            'interests' => ['nullable', 'array', "max:{$maxInterests}"],
            'interests.*' => ['string', 'max:50'],
            'profile_visibility' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'You must be at least 18 years old to use Lindr.',
        ];
    }
}
