<?php

namespace App\Services;

use App\Models\CommunityGuideline;

class CommunityGuidelinesService
{
    /**
     * Seed initial Community Guidelines v1.0 if empty.
     */
    public function seedDefaultGuidelinesIfEmpty(): void
    {
        if (CommunityGuideline::count() === 0) {
            CommunityGuideline::create([
                'version' => 'v1.0',
                'title' => 'Lindr Community Guidelines & Safety Policy',
                'status' => 'published',
                'published_at' => now(),
                'content' => [
                    'sections' => [
                        [
                            'title' => '1. Respect & Inclusive Behavior',
                            'body' => 'Treat all members with courtesy and kindness. Harassment, intimidation, bullying, or abusive behavior will not be tolerated on Lindr.',
                        ],
                        [
                            'title' => '2. No Hate Speech or Discrimination',
                            'body' => 'Hateful conduct, derogatory remarks, or discrimination based on race, ethnicity, nationality, religion, sexual orientation, gender, or disability is strictly prohibited.',
                        ],
                        [
                            'title' => '3. Sexual Misconduct & Exploitation',
                            'body' => 'Non-consensual sexual content, sexual harassment, coercion, or exploitation is illegal and will result in immediate permanent account termination.',
                        ],
                        [
                            'title' => '4. No Threats or Violence',
                            'body' => 'Physical threats, violent imagery, or encouragement of harm against oneself or others is forbidden.',
                        ],
                        [
                            'title' => '5. Financial Safety & Anti-Scam Policy',
                            'body' => 'Financial scams, impersonation for money, fraudulent token manipulation, or account theft attempts are criminal offenses reported to authorities.',
                        ],
                        [
                            'title' => '6. Privacy & Personal Information (Doxxing)',
                            'body' => 'Do not post or threaten to expose another user\'s private personal details, location, phone numbers, or private communications without consent.',
                        ],
                        [
                            'title' => '7. Adults Only (18+ Mandatory)',
                            'body' => 'Lindr is exclusively for adults aged 18 and older. Minors are strictly prohibited from using the application.',
                        ],
                        [
                            'title' => '8. Creator & Monetization Integrity',
                            'body' => 'Creators and users must follow monetization rules. Artificial call inflation, fake gifts, or payment exploitation will result in account suspension and credit forfeiture.',
                        ],
                    ],
                ],
            ]);
        }
    }

    /**
     * Get the latest published Community Guidelines version.
     */
    public function getLatestPublished(): ?CommunityGuideline
    {
        $this->seedDefaultGuidelinesIfEmpty();

        return CommunityGuideline::where('status', 'published')
            ->orderBy('published_at', 'desc')
            ->first();
    }
}
