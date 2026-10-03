<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Self-service volunteer profile: skills, IEEE affiliation, bio, links, CV statement. */
class VolunteerProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user()->load('profile', 'skills');

        return view('profile.volunteer', [
            'user' => $user,
            'profile' => $user->profile,
            'skillOptions' => Skill::orderBy('name')->pluck('name', 'id'),
            'completeness' => $user->profile->completeness(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        $data = $request->validate([
            'headline' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'cv_statement' => ['nullable', 'string', 'max:1500'],
            'ieee_member_number' => ['nullable', 'regex:/^\d{6,10}$/', Rule::unique('profiles', 'ieee_member_number')->ignore($profile->id)],
            'membership_grade' => ['nullable', Rule::in(array_keys(config('volunteering.membership_grades')))],
            'member_since' => ['nullable', 'integer', 'min:1963', 'max:'.now()->year],
            'region' => ['nullable', Rule::in(array_keys(config('volunteering.regions')))],
            'section' => ['nullable', 'string', 'max:120'],
            'society' => ['nullable', 'string', 'max:160'],
            'country' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'linkedin_url' => ['nullable', 'url', 'max:255', 'regex:/^https:\/\/([a-z]+\.)?linkedin\.com\//i'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255', 'regex:/^https:\/\/(www\.)?github\.com\//i'],
            'availability' => ['required', Rule::in(array_keys(config('volunteering.availability')))],
            'hours_per_month' => ['nullable', 'integer', 'min:1', 'max:200'],
            'is_public' => ['boolean'],
            'show_email' => ['boolean'],
            'skills' => ['array', 'max:30'],
            'skills.*' => ['string', 'max:130'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_avatar' => ['boolean'],
        ], [
            'ieee_member_number.regex' => 'IEEE member numbers are 6–10 digits.',
            'linkedin_url.regex' => 'Use your full LinkedIn profile link (https://www.linkedin.com/in/…).',
            'github_url.regex' => 'Use your full GitHub link (https://github.com/…).',
        ]);

        if ($request->boolean('remove_avatar') && $profile->avatar_path) {
            Storage::disk('public')->delete($profile->avatar_path);
            $profile->avatar_path = null;
        }
        if ($request->hasFile('avatar')) {
            if ($profile->avatar_path) {
                Storage::disk('public')->delete($profile->avatar_path);
            }
            $profile->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }

        $profile->fill(collect($data)->except(['skills', 'avatar', 'remove_avatar'])->all());
        $profile->is_public = $request->boolean('is_public');
        $profile->show_email = $request->boolean('show_email');
        $profile->save();

        $user->skills()->sync($this->skillIds($data['skills'] ?? []));

        Activity::record('profile.updated', $user);

        return redirect()->route('profile.volunteer.edit')->with('status', 'Your volunteer profile has been saved.');
    }

    /** @return array<int,int> */
    private function skillIds(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (Str::startsWith($value, 'new:')) {
                $name = trim(Str::after($value, 'new:'));
                if ($name !== '') {
                    $ids[] = Skill::findOrCreateByName(Str::limit($name, 120, ''))->id;
                }
            } elseif (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        }

        return Skill::whereIn('id', array_unique($ids))->pluck('id')->all();
    }
}
