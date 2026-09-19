<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProviderInterface;
use App\Models\CoinPackage;
use App\Models\PaymentMethod;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentRouter
{
    /**
     * Known provider singleton mapping.
     */
    protected array $providers = [];

    public function __construct(
        public ?KoraPayProvider $koraPay = null,
        public ?FlutterwaveProvider $flutterwave = null,
        public ?MpesaPaymentProvider $mpesa = null,
        public ?GooglePayProvider $googlePay = null
    ) {
        $this->koraPay ??= new KoraPayProvider;
        $this->flutterwave ??= new FlutterwaveProvider;
        $this->mpesa ??= new MpesaPaymentProvider;
        $this->googlePay ??= new GooglePayProvider;

        $this->providers = [
            'korapay' => $this->koraPay,
            'flutterwave' => $this->flutterwave,
            'mpesa' => $this->mpesa,
            'google_pay' => $this->googlePay,
        ];
    }

    /**
     * Get concrete provider service by code.
     */
    public function getProvider(string $code): PaymentProviderInterface
    {
        $code = strtolower($code);
        if (! isset($this->providers[$code])) {
            throw new \InvalidArgumentException("Payment provider '{$code}' is not supported.");
        }

        return $this->providers[$code];
    }

    /**
     * Detect customer country from trusted signals.
     */
    public function detectCountry(User $user, ?string $overrideCountry = null): string
    {
        if (! empty($overrideCountry) && strlen($overrideCountry) === 2) {
            return strtoupper($overrideCountry);
        }

        if (! empty($user->profile->country)) {
            return strtoupper($user->profile->country);
        }

        $phoneNumber = $user->phone ?? $user->phone_number ?? null;
        if (! empty($phoneNumber)) {
            $phone = preg_replace('/[^0-9]/', '', $phoneNumber);
            if (str_starts_with($phone, '254')) {
                return 'KE';
            }
            if (str_starts_with($phone, '234')) {
                return 'NG';
            }
            if (str_starts_with($phone, '233')) {
                return 'GH';
            }
            if (str_starts_with($phone, '1')) {
                return 'US';
            }
        }

        return 'KE';
    }

    /**
     * Resolve currency for country.
     */
    public function resolveCurrency(string $country, ?string $overrideCurrency = null): string
    {
        if (! empty($overrideCurrency) && strlen($overrideCurrency) === 3) {
            return strtoupper($overrideCurrency);
        }

        return match (strtoupper($country)) {
            'KE' => 'KES',
            'NG' => 'NGN',
            'GH' => 'GHS',
            'US' => 'USD',
            'GB' => 'GBP',
            default => 'KES',
        };
    }

    /**
     * Dynamically fetch available payment methods for user context.
     *
     * @return Collection<int, PaymentMethod>
     */
    public function getAvailableMethods(User $user, ?string $countryOverride = null, ?string $currencyOverride = null): Collection
    {
        $country = $this->detectCountry($user, $countryOverride);
        $currency = $this->resolveCurrency($country, $currencyOverride);

        $methods = PaymentMethod::where('enabled', true)
            ->whereHas('provider', function ($query) {
                $query->where('enabled', true)->where('status', 'active');
            })
            ->orderBy('display_order', 'asc')
            ->get();

        return $methods->filter(function (PaymentMethod $method) use ($country, $currency) {
            // Country match
            if (! empty($method->supported_countries) && is_array($method->supported_countries)) {
                if (! in_array($country, $method->supported_countries, true)) {
                    return false;
                }
            }

            // Currency match
            if (! empty($method->supported_currencies) && is_array($method->supported_currencies)) {
                if (! in_array($currency, $method->supported_currencies, true)) {
                    return false;
                }
            }

            // Provider country match
            $provider = $method->provider;
            if ($provider && ! empty($provider->supported_countries) && is_array($provider->supported_countries)) {
                if (! in_array($country, $provider->supported_countries, true)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * Initiate payment transaction.
     */
    public function initiate(User $user, CoinPackage $package, string $paymentMethodCode, array $params = []): PaymentTransaction
    {
        if (! $package->is_active) {
            throw new \InvalidArgumentException('Selected coin package is currently inactive.');
        }

        $country = $this->detectCountry($user, $params['country'] ?? null);
        $currency = $this->resolveCurrency($country, $params['currency'] ?? null);

        /** @var PaymentMethod|null $method */
        $method = PaymentMethod::where('code', $paymentMethodCode)
            ->where('enabled', true)
            ->with('provider')
            ->first();

        if (! $method && $paymentMethodCode === 'mpesa') {
            $provider = PaymentProvider::firstOrCreate(
                ['code' => 'mpesa'],
                ['name' => 'Safaricom M-Pesa (Daraja)', 'enabled' => true, 'test_mode' => true, 'priority' => 1, 'status' => 'active']
            );
            $method = PaymentMethod::firstOrCreate(
                ['code' => 'mpesa'],
                ['name' => 'M-Pesa Express', 'provider_code' => 'mpesa', 'enabled' => true, 'display_order' => 1]
            );
            $method->setRelation('provider', $provider);
        }

        if (! $method || ! $method->provider || ! $method->provider->enabled) {
            throw new \InvalidArgumentException("Payment method '{$paymentMethodCode}' is currently unavailable.");
        }

        $price = (float) $package->price_kes;
        if ($method->minimum_amount !== null && $price < (float) $method->minimum_amount) {
            throw new \InvalidArgumentException("Amount KES {$price} is below the minimum limit for {$method->name}.");
        }
        if ($method->maximum_amount !== null && $price > (float) $method->maximum_amount) {
            throw new \InvalidArgumentException("Amount KES {$price} exceeds the maximum limit for {$method->name}.");
        }

        return DB::transaction(function () use ($user, $package, $method, $country, $currency, $price, $params) {
            $publicRef = 'PAY-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));
            $idempotencyKey = $params['idempotency_key'] ?? 'pay_init_'.md5($user->id.'_'.$package->id.'_'.$publicRef);

            $transaction = PaymentTransaction::create([
                'public_reference' => $publicRef,
                'user_id' => $user->id,
                'package_id' => $package->id,
                'provider_code' => $method->provider_code,
                'payment_method_code' => $method->code,
                'country' => $country,
                'currency' => $currency,
                'amount' => $price,
                'expected_coins' => $package->total_coins,
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'package_name' => $package->name,
                    'phone_number' => $params['phone_number'] ?? null,
                ],
            ]);

            $providerInstance = $this->getProvider($method->provider_code);
            $result = $providerInstance->initiatePayment($transaction, $params);

            if ($result->success) {
                $metadata = array_merge($transaction->metadata ?? [], [
                    'checkout_url' => $result->checkoutUrl,
                    'action_data' => $result->actionData,
                ]);

                $transaction->update([
                    'provider_reference' => $result->providerReference,
                    'metadata' => $metadata,
                ]);
            } else {
                $transaction->update([
                    'status' => 'failed',
                    'error_message' => $result->message,
                ]);
            }

            return $transaction;
        });
    }
}
