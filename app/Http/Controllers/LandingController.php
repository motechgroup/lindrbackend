<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\LandingMedia;
use App\Models\LandingSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingController extends Controller
{
    /**
     * Display the public marketing landing page.
     */
    public function __invoke(Request $request): View
    {
        $settings = LandingSetting::getAllSettings();
        $media = LandingMedia::getAllMedia();
        $faqs = Faq::getActiveFaqs();

        return view('landing', compact('settings', 'media', 'faqs'));
    }
}
