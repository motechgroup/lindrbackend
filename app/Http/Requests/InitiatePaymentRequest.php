<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'coin_package_id' => $this->input('coin_package_id') ?? $this->input('package_id'),
            'payment_method' => $this->input('payment_method') ?? 'mpesa',
        ]);
    }

    public function rules(): array
    {
        return [
            'coin_package_id' => ['required', 'exists:coin_packages,id'],
            'payment_method' => ['required', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['nullable', 'string', 'size:3'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'return_url' => ['nullable', 'url', 'max:255'],
            'google_pay_token' => ['nullable', 'string'],
        ];
    }
}
