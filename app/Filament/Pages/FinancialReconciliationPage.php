<?php

namespace App\Filament\Pages;

use App\Models\CreatorCreditLedger;
use App\Models\Wallet;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class FinancialReconciliationPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static UnitEnum|string|null $navigationGroup = 'Financials';

    protected string $view = 'filament.pages.financial-reconciliation';

    public float $totalCreditsEarned = 0.0;

    public float $totalCreditsWithdrawn = 0.0;

    public float $totalCreditsReversed = 0.0;

    public float $totalAvailableCredits = 0.0;

    public float $discrepancy = 0.0;

    public array $reconciliationRows = [];

    public function mount(): void
    {
        $this->calculateReconciliation();
    }

    public function calculateReconciliation(): void
    {
        $this->totalCreditsEarned = (float) CreatorCreditLedger::where('amount_credits', '>', 0)
            ->whereNotIn('transaction_type', ['WITHDRAWAL_REVERSAL', 'REFUND'])
            ->sum('amount_credits');

        $this->totalCreditsWithdrawn = (float) abs((float) CreatorCreditLedger::where('amount_credits', '<', 0)
            ->where('transaction_type', 'WITHDRAWAL')
            ->sum('amount_credits'));

        $this->totalCreditsReversed = (float) CreatorCreditLedger::where('amount_credits', '>', 0)
            ->whereIn('transaction_type', ['WITHDRAWAL_REVERSAL', 'REFUND'])
            ->sum('amount_credits');

        $this->totalAvailableCredits = (float) Wallet::sum('credits');

        $expectedAvailable = $this->totalCreditsEarned - $this->totalCreditsWithdrawn + $this->totalCreditsReversed;
        $this->discrepancy = round($expectedAvailable - $this->totalAvailableCredits, 2);

        // Per creator breakdown
        $creators = DB::table('users')
            ->where('is_creator', true)
            ->get(['id', 'name', 'email']);

        $rows = [];
        foreach ($creators as $creator) {
            $earned = (float) CreatorCreditLedger::where('user_id', $creator->id)
                ->where('amount_credits', '>', 0)
                ->whereNotIn('transaction_type', ['WITHDRAWAL_REVERSAL', 'REFUND'])
                ->sum('amount_credits');

            $withdrawn = (float) abs((float) CreatorCreditLedger::where('user_id', $creator->id)
                ->where('amount_credits', '<', 0)
                ->where('transaction_type', 'WITHDRAWAL')
                ->sum('amount_credits'));

            $reversed = (float) CreatorCreditLedger::where('user_id', $creator->id)
                ->where('amount_credits', '>', 0)
                ->whereIn('transaction_type', ['WITHDRAWAL_REVERSAL', 'REFUND'])
                ->sum('amount_credits');

            $walletCredits = (float) (Wallet::where('user_id', $creator->id)->value('credits') ?? 0);
            $expected = $earned - $withdrawn + $reversed;
            $diff = round($expected - $walletCredits, 2);

            $rows[] = [
                'id' => $creator->id,
                'name' => $creator->name,
                'email' => $creator->email,
                'earned' => $earned,
                'withdrawn' => $withdrawn,
                'reversed' => $reversed,
                'available' => $walletCredits,
                'expected' => $expected,
                'diff' => $diff,
                'is_reconciled' => abs($diff) < 0.01,
            ];
        }

        $this->reconciliationRows = $rows;
    }
}
