<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings.edit', [
            'settings' => [
                'site_name' => Setting::get('site_name', 'IEEE Volunteering'),
                'site_tagline' => Setting::get('site_tagline', 'Find your next way to give back to the IEEE community.'),
                'footer_text' => Setting::get('footer_text', ''),
                'contact_email' => Setting::get('contact_email', config('mail.from.address')),
                'seo_default_description' => Setting::get('seo_default_description', ''),
                'social_image' => Setting::get('social_image', ''),
                'twitter_handle' => Setting::get('twitter_handle', ''),
                'search_indexable' => (bool) Setting::get('search_indexable', false),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:150'],
            'site_tagline' => ['nullable', 'string', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['required', 'email', 'max:190'],
            'seo_default_description' => ['nullable', 'string', 'max:255'],
            'social_image' => ['nullable', 'string', 'max:500'],
            'twitter_handle' => ['nullable', 'string', 'max:50'],
        ]);

        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }

        // Checkbox is absent from the request when unchecked, so set it
        // explicitly to allow turning indexing back off.
        Setting::put('search_indexable', $request->boolean('search_indexable') ? '1' : '0');

        return back()->with('status', 'Settings saved.');
    }
}
