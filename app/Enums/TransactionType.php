<?php

namespace App\Enums;

enum TransactionType: string
{
    case Credit = 'CREDIT';
    case Debit = 'DEBIT';
    case Refund = 'REFUND';
    case Bonus = 'BONUS';
    case Adjustment = 'ADJUSTMENT';
}
