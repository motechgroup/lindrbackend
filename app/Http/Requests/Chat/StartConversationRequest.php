<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $recipientId = $this->input('recipient_id')
            ?? $this->input('receiver_id')
            ?? $this->input('user_id')
            ?? $this->input('target_id');

        if ($recipientId !== null) {
            $this->merge(['recipient_id' => $recipientId]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
