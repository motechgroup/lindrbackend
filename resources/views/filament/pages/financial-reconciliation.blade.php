<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Credits Earned</div>
            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($totalCreditsEarned) }}</div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Credits Withdrawn</div>
            <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($totalCreditsWithdrawn) }}</div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Credits Reversed</div>
            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($totalCreditsReversed) }}</div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow border border-gray-200 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Available Credits</div>
            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($totalAvailableCredits) }}</div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 font-bold text-lg">
            Creator Reconciliation Audit Log
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                        <th class="p-3">Creator</th>
                        <th class="p-3">Total Earned</th>
                        <th class="p-3">Withdrawn</th>
                        <th class="p-3">Reversed</th>
                        <th class="p-3">Available Wallet</th>
                        <th class="p-3">Expected</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reconciliationRows as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="p-3 font-semibold">{{ $row['name'] }} <span class="text-xs text-gray-400">({{ $row['email'] }})</span></td>
                            <td class="p-3 text-emerald-600 font-mono">{{ number_format($row['earned']) }}</td>
                            <td class="p-3 text-amber-600 font-mono">{{ number_format($row['withdrawn']) }}</td>
                            <td class="p-3 text-blue-600 font-mono">{{ number_format($row['reversed']) }}</td>
                            <td class="p-3 text-purple-600 font-mono font-bold">{{ number_format($row['available']) }}</td>
                            <td class="p-3 font-mono">{{ number_format($row['expected']) }}</td>
                            <td class="p-3">
                                @if($row['is_reconciled'])
                                    <span class="px-2 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full">Reconciled</span>
                                @else
                                    <span class="px-2 py-1 bg-rose-100 text-rose-800 text-xs font-bold rounded-full">Discrepancy ({{ $row['diff'] }})</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-4 text-center text-gray-400">No verified creators found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
