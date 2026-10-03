<?php

namespace App\Http\Requests;

use App\Models\Skill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_online' => $this->boolean('is_online'),
            'membership_grades' => array_values(array_filter((array) $this->input('membership_grades', []))),
            'upskills' => array_values(array_filter((array) $this->input('upskills', []))),
            'skills' => array_values(array_filter((array) $this->input('skills', []))),
            'co_owners' => array_values(array_filter((array) $this->input('co_owners', []), 'is_numeric')),
        ]);
    }

    public function rules(): array
    {
        $rules = $this->fullRules();

        // Drafts only need a title; everything else is checked when publishing.
        if ($this->input('action') === 'draft') {
            foreach ($rules as $field => $fieldRules) {
                if ($field !== 'title') {
                    $rules[$field] = array_map(fn ($r) => $r === 'required' ? 'nullable' : $r, array_filter($fieldRules, fn ($r) => ! ($r instanceof \Illuminate\Validation\Rules\RequiredIf) && ! (is_string($r) && str_starts_with($r, 'min:') && in_array($field, ['skills', 'description'], true))));
                }
            }
        }

        return $rules;
    }

    private function fullRules(): array
    {
        $regions = array_keys(config('volunteering.regions'));
        $grades = array_merge(['_ALL'], array_keys(config('volunteering.membership_grades')));

        return [
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'description' => ['required', 'string', 'min:30', 'max:10000'],
            'details_url' => ['nullable', 'url', 'max:500'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'is_online' => ['boolean'],
            'location' => [Rule::requiredIf(! $this->boolean('is_online')), 'nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:80'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'region' => ['nullable', Rule::in($regions)],
            'section' => ['nullable', 'string', 'max:120'],
            'organizational_unit' => ['nullable', 'string', 'max:160'],
            'society' => ['nullable', 'string', 'max:160'],
            'experience_level' => ['required', Rule::in(array_keys(config('volunteering.experience_levels')))],
            'project_size' => ['required', Rule::in(array_keys(config('volunteering.project_sizes')))],
            'membership_grades' => ['array'],
            'membership_grades.*' => [Rule::in($grades)],
            'upskills' => ['array'],
            'upskills.*' => [Rule::in(array_keys(config('volunteering.upskills')))],
            'ideal_traits' => ['nullable', 'string', 'max:255'],
            'hours_estimate' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'hours_frequency' => ['required', Rule::in(array_keys(config('volunteering.hours_frequencies')))],
            'volunteers_needed' => ['required', 'integer', 'min:1', 'max:1000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'skills' => ['required', 'array', 'min:1', 'max:15'],
            'skills.*' => ['string', 'max:130'],
            'co_owners' => ['array', 'max:'.(config('volunteering.max_owners') - 1)],
            'co_owners.*' => ['integer', Rule::exists('users', 'id')->whereNull('suspended_at')],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'action' => ['nullable', Rule::in(['draft', 'publish', 'save'])],
        ];
    }

    public function messages(): array
    {
        return [
            'skills.required' => 'Pick at least one skill so we can match the right volunteers.',
            'location.required' => 'Add a location, or tick “This can be done online”.',
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }

    /** Validated attributes for the Opportunity model. */
    public function opportunityAttributes(): array
    {
        $data = $this->safe()->except(['skills', 'co_owners', 'thumbnail', 'action']);

        $data['membership_grades'] = $data['membership_grades'] ?: null;
        $data['description'] ??= '';
        $data['hours_frequency'] ??= 'overall';
        $data['volunteers_needed'] ??= 1;
        $data['upskills'] = $data['upskills'] ?: null;

        return $data;
    }

    /**
     * Resolve the skills field into ids. Existing skills arrive as ids; new
     * ones typed in the picker arrive as "new:<name>" and are created.
     *
     * @return array<int,int>
     */
    public function skillIds(): array
    {
        $ids = [];
        foreach ($this->validated('skills', []) as $value) {
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

    /** @return array<int,int> */
    public function coOwnerIds(): array
    {
        return array_map('intval', $this->validated('co_owners', []));
    }
}
