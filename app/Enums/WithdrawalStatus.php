<?php

namespace App\Enums;

enum WithdrawalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Processing = 'processing';
    case Paid = 'paid';
    case Success = 'success';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';

    public function isFinal(): bool
    {
        return in_array($this, [self::Paid, self::Success, self::Failed, self::Rejected, self::Cancelled, self::Reversed]);
    }

    public function canTransitionTo(WithdrawalStatus $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return match ($this) {
            self::Pending => in_array($next, [self::Approved, self::Processing, self::Paid, self::Success, self::Rejected, self::Failed, self::Cancelled]),
            self::Approved, self::Processing => in_array($next, [self::Paid, self::Success, self::Failed, self::Cancelled, self::Reversed]),
            self::Paid, self::Success => $next === self::Reversed,
            self::Failed, self::Rejected, self::Cancelled, self::Reversed => false,
        };
    }
}
