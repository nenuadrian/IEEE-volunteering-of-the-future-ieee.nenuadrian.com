<?php

namespace App\Support;

use App\Models\Opportunity;
use App\Models\User;

/**
 * Explainable match between a volunteer and an opportunity.
 *
 *  - Skills (60%):   share of the required skills the volunteer has.
 *  - Grade (20%):    the volunteer's membership grade is eligible.
 *  - Location (20%): the opportunity is online or in the volunteer's region.
 *
 * Weights live in config('volunteering.match_weights').
 */
class MatchScore
{
    /**
     * @param  array<int,int>|null  $userSkillIds  Pre-loaded skill ids to avoid a query per card.
     * @return array{percent:int, matched:array<int,string>, missing:array<int,string>, grade:bool, location:bool, reasons:array<int,string>}|null
     */
    public static function for(?User $user, Opportunity $opportunity, ?array $userSkillIds = null): ?array
    {
        if (! $user) {
            return null;
        }

        $weights = config('volunteering.match_weights');
        $profile = $user->profile;
        $userSkillIds ??= $user->skills()->pluck('skills.id')->all();

        $required = $opportunity->relationLoaded('skills') ? $opportunity->skills : $opportunity->skills()->get();
        $matched = $required->whereIn('id', $userSkillIds);
        $missing = $required->whereNotIn('id', $userSkillIds);

        $skillShare = $required->isEmpty() ? 1.0 : $matched->count() / $required->count();
        $gradeOk = $opportunity->acceptsGrade($profile?->membership_grade);
        $locationOk = $opportunity->is_online
            || blank($opportunity->region)
            || ($profile?->region && $profile->region === $opportunity->region);

        $percent = (int) round(
            $skillShare * $weights['skills']
            + ($gradeOk ? $weights['grade'] : 0)
            + ($locationOk ? $weights['location'] : 0)
        );

        $reasons = [];
        $reasons[] = $required->isEmpty()
            ? 'No specific skills required'
            : "You have {$matched->count()} of {$required->count()} required skills";
        $reasons[] = $gradeOk ? 'Your membership grade is eligible' : 'Your membership grade is not listed as eligible';
        $reasons[] = $opportunity->is_online ? 'Can be done online' : ($locationOk ? 'In your IEEE region' : 'Outside your IEEE region');

        return [
            'percent' => max(0, min(100, $percent)),
            'matched' => $matched->pluck('name')->values()->all(),
            'missing' => $missing->pluck('name')->values()->all(),
            'grade' => $gradeOk,
            'location' => $locationOk,
            'reasons' => $reasons,
        ];
    }
}
