<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class PaymentProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'enabled',
        'test_mode',
        'priority',
        'supported_countries',
        'supported_currencies',
        'configuration',
        'webhook_secret',
        'status',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'test_mode' => 'boolean',
        'priority' => 'integer',
        'supported_countries' => 'array',
        'supported_currencies' => 'array',
    ];

    protected function configuration(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null) {
                    return [];
                }
                try {
                    $decrypted = Crypt::decrypt($value);
                    if (is_array($decrypted)) {
                        return $decrypted;
                    }

                    return json_decode((string) $decrypted, true) ?? [];
                } catch (DecryptException) {
                    $raw = json_decode((string) $value, true);

                    return is_array($raw) ? $raw : [];
                }
            },
            set: function ($value) {
                if ($value === null) {
                    return null;
                }

                return Crypt::encrypt(is_array($value) ? json_encode($value) : $value);
            }
        );
    }

    protected function webhookSecret(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null) {
                    return null;
                }
                try {
                    return Crypt::decrypt($value);
                } catch (DecryptException) {
                    return $value;
                }
            },
            set: function ($value) {
                return $value !== null && $value !== '' ? Crypt::encrypt($value) : null;
            }
        );
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'provider_code', 'code');
    }
}
