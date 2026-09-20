<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <!-- Call Commission & Rates Section -->
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center space-x-3 mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
                <div class="p-2.5 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 rounded-lg shrink-0">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px; max-width: 24px; max-height: 24px; display: block;" class="shrink-0">
                        <path d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">📞 Video & Audio Call Creator Commission</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Set percentage (%) of call token charges awarded as creator credits.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1.5">Female Creator Call Share (%)</label>
                    <div class="relative rounded-lg shadow-sm">
                        <input type="number" step="0.1" min="0" max="100" wire:model="call_female_creator_share_pct" class="fi-input block w-full rounded-lg border-0 py-2.5 pl-3 pr-8 text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-white/5 dark:text-white dark:ring-white/20 text-sm sm:leading-6" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400 text-sm font-bold">%</div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Default: 70.0% of video/audio call token charges awarded to female creators.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1.5">Male Creator Call Share (%)</label>
                    <div class="relative rounded-lg shadow-sm">
                        <input type="number" step="0.1" min="0" max="100" wire:model="call_male_creator_share_pct" class="fi-input block w-full rounded-lg border-0 py-2.5 pl-3 pr-8 text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-white/5 dark:text-white dark:ring-white/20 text-sm sm:leading-6" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400 text-sm font-bold">%</div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Default: 70.0% of video/audio call token charges awarded to male creators.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1.5">Video Call Rate (Tokens / Min)</label>
                    <input type="number" min="1" wire:model="video_call_rate_per_minute" class="fi-input block w-full rounded-lg border-0 py-2.5 px-3 text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-white/5 dark:text-white dark:ring-white/20 text-sm sm:leading-6" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Token cost per minute charged to caller for video calls (Default: 30).</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1.5">Audio Call Rate (Tokens / Min)</label>
                    <input type="number" min="1" wire:model="audio_call_rate_per_minute" class="fi-input block w-full rounded-lg border-0 py-2.5 px-3 text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 dark:bg-white/5 dark:text-white dark:ring-white/20 text-sm sm:leading-6" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Token cost per minute charged to caller for audio calls (Default: 20).</p>
                </div>
            </div>
        </div>

        <!-- Paid Chat & Match Coins Section -->
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center space-x-3 mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
                <div class="p-2.5 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 rounded-lg shrink-0">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px; max-width: 24px; max-height: 24px; display: block;" class="shrink-0">
                        <path d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">💬 Paid Chat & Match Coins</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Manage chat coins, match coins, and creator message commission rates.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1.5">Female Creator Chat Share (%)</label>
                    <div class="relative rounded-lg shadow-sm">
                        <input type="number" step="0.1" min="0" max="100" wire:model="chat_female_creator_share_pct" class="fi-input block w-full rounded-lg border-0 py-2.5 pl-3 pr-8 text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-emerald-600 dark:bg-white/5 dark:text-white dark:ring-white/20 text-sm sm:leading-6" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400 text-sm font-bold">%</div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Default: 60.0% of paid chat message tokens awarded to female creators.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1.5">Male Creator Chat Share (%)</label>
                    <div class="relative rounded-lg shadow-sm">
                        <input type="number" step="0.1" min="0" max="100" wire:model="chat_male_creator_share_pct" class="fi-input block w-full rounded-lg border-0 py-2.5 pl-3 pr-8 text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-emerald-600 dark:bg-white/5 dark:text-white dark:ring-white/20 text-sm sm:leading-6" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400 text-sm font-bold">%</div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Default: 60.0% of paid chat message tokens awarded to male creators.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1.5">💬 Chat Coins (Tokens / Paid Message)</label>
                    <input type="number" min="1" wire:model="message_cost" class="fi-input block w-full rounded-lg border-0 py-2.5 px-3 text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-emerald-600 dark:bg-white/5 dark:text-white dark:ring-white/20 text-sm sm:leading-6" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Chat coins debited from sender per message sent to a creator (Default: 5 coins).</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1.5">🎯 Match Coins (Tokens / Instant Match)</label>
                    <input type="number" min="1" wire:model="matching_token_cost" class="fi-input block w-full rounded-lg border-0 py-2.5 px-3 text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-emerald-600 dark:bg-white/5 dark:text-white dark:ring-white/20 text-sm sm:leading-6" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Match coins debited for instant profile match unlock broadcast (Default: 50 coins).</p>
                </div>
            </div>
        </div>

        <!-- Virtual Gift Commission Section -->
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center space-x-3 mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
                <div class="p-2.5 bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400 rounded-lg shrink-0">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px; max-width: 24px; max-height: 24px; display: block;" class="shrink-0">
                        <path d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V6a2 2 0 10-2 2h2zm0 13C10.832 21 2 20 2 12V8a2 2 0 012-2h16a2 2 0 012 2v4c0 8-8.832 9-10 9z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">🎁 Virtual Gift Creator Commission</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Set default percentage (%) of gift coin value awarded as creator credits.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1.5">Female Creator Gift Share (%)</label>
                    <div class="relative rounded-lg shadow-sm">
                        <input type="number" step="0.1" min="0" max="100" wire:model="gift_female_creator_share_pct" class="fi-input block w-full rounded-lg border-0 py-2.5 pl-3 pr-8 text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-purple-600 dark:bg-white/5 dark:text-white dark:ring-white/20 text-sm sm:leading-6" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400 text-sm font-bold">%</div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Default platform fallback: 60.0% of gift coin price awarded to female creators.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1.5">Male Creator Gift Share (%)</label>
                    <div class="relative rounded-lg shadow-sm">
                        <input type="number" step="0.1" min="0" max="100" wire:model="gift_male_creator_share_pct" class="fi-input block w-full rounded-lg border-0 py-2.5 pl-3 pr-8 text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-purple-600 dark:bg-white/5 dark:text-white dark:ring-white/20 text-sm sm:leading-6" />
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-400 text-sm font-bold">%</div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Default platform fallback: 60.0% of gift coin price awarded to male creators.</p>
                </div>
            </div>
        </div>

        <!-- Payouts & Thresholds Section -->
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center space-x-3 mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
                <div class="p-2.5 bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 rounded-lg shrink-0">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 24px; height: 24px; max-width: 24px; max-height: 24px; display: block;" class="shrink-0">
                        <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">💸 Withdrawal & Payout Rules</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Configure creator credit redemption values and withdrawal security rules.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1.5">Credits per $1.00 USD Payout</label>
                    <input type="number" step="0.1" min="1" wire:model="credits_per_usd" class="fi-input block w-full rounded-lg border-0 py-2.5 px-3 text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-amber-600 dark:bg-white/5 dark:text-white dark:ring-white/20 text-sm sm:leading-6" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Creator credits required per $1 USD (Default: 10.0 credits = $1 USD).</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1.5">Minimum Withdrawal (Credits)</label>
                    <input type="number" min="1" wire:model="minimum_withdrawal_credits" class="fi-input block w-full rounded-lg border-0 py-2.5 px-3 text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-amber-600 dark:bg-white/5 dark:text-white dark:ring-white/20 text-sm sm:leading-6" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Minimum credits required before requesting M-Pesa payout (Default: 100).</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-950 dark:text-white mb-1.5">M-Pesa Change Hold (Hours)</label>
                    <input type="number" min="0" wire:model="payout_number_change_hold_hours" class="fi-input block w-full rounded-lg border-0 py-2.5 px-3 text-gray-950 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-amber-600 dark:bg-white/5 dark:text-white dark:ring-white/20 text-sm sm:leading-6" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">Security hold period after updating payout phone number (Default: 48 hrs).</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" class="fi-btn relative inline-flex items-center justify-center gap-x-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-all cursor-pointer">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 20px; height: 20px; max-width: 20px; max-height: 20px; display: block;" class="shrink-0">
                    <path d="M5 13l4 4L19 7"></path>
                </svg>
                Save Monetization Settings
            </button>
        </div>
    </form>
</x-filament-panels::page>
