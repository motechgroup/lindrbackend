# Lindr Product & Architecture PRD — Version 2.0 (Final Production Model)

## 1. Primary Authentication
- **Google Sign-In (`POST /api/v1/auth/google`)**: Primary authentication mechanism for all mobile users.
- Legacy password endpoints remain for admin access and testing.
- Secure Sanctum bearer tokens issued upon successful Google ID token verification.

## 2. Gender-Neutral Creator Economy
- **Verified Creator Status**: Both male and female users can apply to become verified Creators (`is_creator = true`, `creator_status = 'approved'`).
- **Selfie Liveness Verification**: Required pose challenge sequence (`turn_head_left`, `smile`, `blink`, etc.) submitted to `POST /api/v1/liveness/verify`. Reviewed privately in Filament Admin.
- Verified creators earn **Credits** from paid chat messages, virtual gifts, and audio/video calls.

## 3. Financial Separation: Tokens vs. Credits
- **Tokens (Spending Currency)**: Purchased by users via unified payment gateways (KoraPay, Flutterwave, M-Pesa, Google Pay). Used for swiping, instant matching, sending paid messages, sending gifts, and initiating calls.
- **Credits (Creator Earnings)**: Earned exclusively by verified Creators. Saved in `wallet.credits` and tracked in `creator_credit_ledgers`.
- **Instant Match Cost**: e.g., 50 tokens deducted from user wallet. **100% platform revenue to Lindr (0 credits awarded)**.
- **Revenue Splits**: Managed via `PlatformSetting` per gender (`chat_female_creator_share_pct`, `chat_male_creator_share_pct`, `call_female_creator_share_pct`, etc.).

## 4. M-Pesa Phone Verification & Creator Withdrawals
- **Phone Verification OTP (`POST /api/v1/payouts/mpesa/send-otp`, `verify-otp`)**: Mandatory 6-digit OTP verification for M-Pesa payout phone numbers.
- **Payout Hold Window**: Updating or changing an M-Pesa phone number triggers a configurable hold period (default 48 hours, `payout_number_change_hold_hours`) during which withdrawals are locked (`payout_hold_until`).
- **Withdrawal Conversions**: Credits are converted to payout value using `credits_per_usd` (default 10 credits = $1.00 USD).

## 5. Filament Admin Panel (`/access`)
- Manage Liveness Verification Applications (review selfie proofs, approve/reject creators).
- Manage Platform Pricing & Monetization Splits.
- Review and process Creator Payout Requests.
