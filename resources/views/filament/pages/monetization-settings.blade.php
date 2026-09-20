<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <!-- Call Commission % Section -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-200 dark:border-gray-700">
            <div class="flex items-center space-x-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-3">
                <div class="p-2 bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">📞 Video & Audio Call Creator Commission</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Set percentage (%) of call token charges awarded as creator credits.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Female Creator Call Share (%)</label>
                    <div class="relative rounded-md shadow-sm">
                        <input type="number" step="0.1" min="0" max="100" wire:model="call_female_creator_share_pct" class="w-full pr-8 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-500">%</div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Default: 70.0% of video/audio call token charges awarded to female creators.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Male Creator Call Share (%)</label>
                    <div class="relative rounded-md shadow-sm">
                        <input type="number" step="0.1" min="0" max="100" wire:model="call_male_creator_share_pct" class="w-full pr-8 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-500">%</div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Default: 70.0% of video/audio call token charges awarded to male creators.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Video Call Rate (Tokens / Min)</label>
                    <input type="number" min="1" wire:model="video_call_rate_per_minute" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500" />
                    <p class="text-xs text-gray-400 mt-1">Token cost per minute charged to caller for video calls (Default: 30).</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Audio Call Rate (Tokens / Min)</label>
                    <input type="number" min="1" wire:model="audio_call_rate_per_minute" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-indigo-500 focus:border-indigo-500" />
                    <p class="text-xs text-gray-400 mt-1">Token cost per minute charged to caller for audio calls (Default: 20).</p>
                </div>
            </div>
        </div>

        <!-- Chat Commission % Section -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-200 dark:border-gray-700">
            <div class="flex items-center space-x-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-3">
                <div class="p-2 bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">💬 Paid Chat Creator Commission</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Set percentage (%) of paid chat message fees awarded as creator credits.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Female Creator Chat Share (%)</label>
                    <div class="relative rounded-md shadow-sm">
                        <input type="number" step="0.1" min="0" max="100" wire:model="chat_female_creator_share_pct" class="w-full pr-8 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-emerald-500 focus:border-emerald-500" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-500">%</div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Default: 60.0% of paid chat message tokens awarded to female creators.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Male Creator Chat Share (%)</label>
                    <div class="relative rounded-md shadow-sm">
                        <input type="number" step="0.1" min="0" max="100" wire:model="chat_male_creator_share_pct" class="w-full pr-8 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-emerald-500 focus:border-emerald-500" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-500">%</div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Default: 60.0% of paid chat message tokens awarded to male creators.</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Paid Message Cost (Tokens / Message)</label>
                    <input type="number" min="1" wire:model="message_cost" class="w-full md:w-1/2 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-emerald-500 focus:border-emerald-500" />
                    <p class="text-xs text-gray-400 mt-1">Tokens debited per message sent to a verified creator (Default: 5 tokens).</p>
                </div>
            </div>
        </div>

        <!-- Gift Commission % Section -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-200 dark:border-gray-700">
            <div class="flex items-center space-x-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-3">
                <div class="p-2 bg-purple-100 dark:bg-purple-900/50 text-purple-600 dark:text-purple-400 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V6a2 2 0 10-2 2h2zm0 13C10.832 21 2 20 2 12V8a2 2 0 012-2h16a2 2 0 012 2v4c0 8-8.832 9-10 9z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">🎁 Virtual Gift Creator Commission</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Set default percentage (%) of gift coin value awarded as creator credits.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Female Creator Gift Share (%)</label>
                    <div class="relative rounded-md shadow-sm">
                        <input type="number" step="0.1" min="0" max="100" wire:model="gift_female_creator_share_pct" class="w-full pr-8 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-purple-500 focus:border-purple-500" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-500">%</div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Default platform fallback: 60.0% of gift coin price awarded to female creators.</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Male Creator Gift Share (%)</label>
                    <div class="relative rounded-md shadow-sm">
                        <input type="number" step="0.1" min="0" max="100" wire:model="gift_male_creator_share_pct" class="w-full pr-8 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-purple-500 focus:border-purple-500" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-500">%</div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Default platform fallback: 60.0% of gift coin price awarded to male creators.</p>
                </div>
            </div>
        </div>

        <!-- Payouts & Thresholds Section -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border border-gray-200 dark:border-gray-700">
            <div class="flex items-center space-x-3 mb-4 border-b border-gray-200 dark:border-gray-700 pb-3">
                <div class="p-2 bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400 rounded-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">💸 Withdrawal & Payout Rules</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Configure creator credit redemption values and withdrawal security rules.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Credits per $1.00 USD Payout</label>
                    <input type="number" step="0.1" min="1" wire:model="credits_per_usd" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500" />
                    <p class="text-xs text-gray-400 mt-1">Creator credits required per $1 USD (Default: 10.0 credits = $1 USD).</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Minimum Withdrawal (Credits)</label>
                    <input type="number" min="1" wire:model="minimum_withdrawal_credits" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500" />
                    <p class="text-xs text-gray-400 mt-1">Minimum credits required before requesting M-Pesa payout (Default: 100).</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">M-Pesa Change Hold (Hours)</label>
                    <input type="number" min="0" wire:model="payout_number_change_hold_hours" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-amber-500 focus:border-amber-500" />
                    <p class="text-xs text-gray-400 mt-1">Security hold period after updating payout phone number (Default: 48 hrs).</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="inline-flex items-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm rounded-xl shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all cursor-pointer">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Save Monetization Settings
            </button>
        </div>
    </form>
</x-filament-panels::page>
