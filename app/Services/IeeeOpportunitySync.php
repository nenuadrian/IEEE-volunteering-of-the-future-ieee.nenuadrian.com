<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Category;
use App\Models\Opportunity;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Pulls opportunities from the public volunteer.ieee.org search API and
 * upserts them locally (matched on the API's opportunityId).
 *
 *  - New opportunities are created with source = "ieee".
 *  - Existing ones are updated only when a mapped field actually changed.
 *  - Imported opportunities that no longer appear in the feed are marked
 *    completed (the public search only lists active and paused ones).
 *  - Owners are linked automatically when a local profile carries the
 *    creator's IEEE member number.
 */
class IeeeOpportunitySync
{
    /** @var array<string,int> */
    private array $skillIds = [];

    /** @var array<string,int> */
    private array $categoryIds = [];

    /**
     * Fetch every page of the public search.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetch(): array
    {
        $config = config('volunteering.api');
        $size = max(10, (int) $config['page_size']);
        $start = 0;
        $hits = [];

        do {
            $response = Http::timeout($config['timeout'])
                ->acceptJson()
                ->withHeaders([
                    'Origin' => $config['origin'],
                    'Referer' => rtrim($config['origin'], '/').'/',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->retry(2, 500, throw: false)
                ->post($config['search_url'], [
                    'start' => $start,
                    'size' => $size,
                    'keyword' => '',
                    'sort' => null,
                    'filters' => [
                        'skillsrequired_la' => [], 'category_la' => [], 'isonline_l' => [],
                        'projectsize_l' => [], 'latlon' => [], 'region_la' => [], 'section_la' => [],
                        'committee_la' => [], 'accepting_applicant_l' => [], 'grades_la' => [],
                        'skillsoffered_la' => [],
                    ],
                ]);

            if (! $response->successful()) {
                throw new RuntimeException('volunteer.ieee.org API returned HTTP '.$response->status());
            }

            $data = $response->json('data');
            if (! is_array($data) || ! isset($data['hits']) || ! is_array($data['hits'])) {
                throw new RuntimeException('Unexpected API response shape (missing data.hits).');
            }

            $page = $data['hits'];
            $total = (int) ($data['totalHits'] ?? count($page));
            $hits = array_merge($hits, $page);
            $start += $size;
        } while (count($page) > 0 && $start < $total && $start < 5000);

        return $hits;
    }

    /**
     * Run a full refresh and record it as a SyncRun.
     *
     * @param  array<int, array<string,mixed>>|null  $hits  Pre-fetched hits (used by the seeder for offline imports).
     */
    public function run(?User $triggeredBy = null, ?array $hits = null, bool $closeMissing = true): SyncRun
    {
        $run = SyncRun::create([
            'source' => 'ieee',
            'triggered_by' => $triggeredBy?->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $log = [];

        try {
            $hits ??= $this->fetch();
            $run->fetched_count = count($hits);

            $seen = [];
            foreach ($hits as $hit) {
                if (empty($hit['opportunityId'])) {
                    continue;
                }

                $seen[] = $hit['opportunityId'];
                $result = $this->upsert($hit);
                $run->{$result.'_count'}++;

                if ($result !== 'unchanged') {
                    $log[] = ['action' => $result, 'title' => Str::limit((string) ($hit['title'] ?? ''), 120), 'id' => $hit['opportunityId']];
                }
            }

            if ($closeMissing && $seen !== []) {
                $missing = Opportunity::query()
                    ->where('source', Opportunity::SOURCE_IEEE)
                    ->whereNotIn('external_id', $seen)
                    ->whereIn('status', [Opportunity::OPEN, Opportunity::IN_PROGRESS, Opportunity::ON_HOLD])
                    ->get();

                foreach ($missing as $opportunity) {
                    $opportunity->update([
                        'status' => Opportunity::COMPLETED,
                        'external_status' => 'No longer listed on volunteer.ieee.org',
                        'closed_at' => now(),
                        'external_synced_at' => now(),
                    ]);
                    $run->closed_count++;
                    $log[] = ['action' => 'closed', 'title' => Str::limit($opportunity->title, 120), 'id' => $opportunity->external_id];
                }
            }

            $run->fill([
                'status' => 'success',
                'log' => array_slice($log, 0, 500),
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            report($e);
            $run->fill([
                'status' => 'failed',
                'error' => Str::limit($e->getMessage(), 2000),
                'log' => array_slice($log, 0, 500),
                'finished_at' => now(),
            ])->save();
        }

        if ($triggeredBy) {
            Activity::record('admin.sync_run', $run, [
                'status' => $run->status,
                'created' => $run->created_count,
                'updated' => $run->updated_count,
                'closed' => $run->closed_count,
            ], $triggeredBy);
        }

        return $run;
    }

    /** @return 'created'|'updated'|'unchanged' */
    public function upsert(array $hit): string
    {
        return DB::transaction(function () use ($hit) {
            $attributes = $this->map($hit);
            $opportunity = Opportunity::withTrashed()->where('external_id', $hit['opportunityId'])->first();
            $created = false;

            if (! $opportunity) {
                $opportunity = new Opportunity([
                    'source' => Opportunity::SOURCE_IEEE,
                    'external_id' => $hit['opportunityId'],
                    'slug' => Opportunity::generateSlug($attributes['title']),
                ]);
                $createdAt = $this->timestamp($hit['createTs'] ?? null) ?? now();
                $opportunity->created_at = $createdAt;
                $opportunity->published_at = $createdAt;
                $created = true;
            }

            $opportunity->fill($attributes);
            $dirty = $opportunity->isDirty();

            $skillIds = $this->skillIdsFor($hit['skillsRequired'] ?? []);
            $skillsChanged = $created
                || collect($skillIds)->sort()->values()->all() !== $opportunity->skills()->pluck('skills.id')->sort()->values()->all();

            if (! $created && ! $dirty && ! $skillsChanged) {
                $opportunity->forceFill(['external_synced_at' => now()])->saveQuietly();
                $this->linkOwner($opportunity, $hit['createdBy'] ?? null);

                return 'unchanged';
            }

            $opportunity->external_synced_at = now();
            $opportunity->save();

            if ($skillsChanged) {
                $opportunity->skills()->sync($skillIds);
            }

            $this->linkOwner($opportunity, $hit['createdBy'] ?? null);

            return $created ? 'created' : 'updated';
        });
    }

    /** Translate an API hit into Opportunity attributes. */
    public function map(array $hit): array
    {
        [$lat, $lng] = $this->coordinates($hit);
        $display = (string) ($hit['displayStatus'] ?? '');

        $status = match (true) {
            ($hit['status'] ?? null) === 'paused', str_contains(strtolower($display), 'hold') => Opportunity::ON_HOLD,
            str_contains(strtolower($display), 'no longer') => Opportunity::IN_PROGRESS,
            default => Opportunity::OPEN,
        };

        $upskills = array_values(array_filter(
            (array) ($hit['skillsOffered'] ?? []),
            fn ($s) => $s && $s !== 'None',
        ));

        return [
            'title' => Str::limit(trim((string) ($hit['title'] ?? 'Untitled opportunity')), 200, ''),
            'description' => trim((string) ($hit['description'] ?? '')) ?: 'No description provided.',
            'details_url' => $this->url($hit['descDetailsUrl'] ?? $hit['detailUrl'] ?? null),
            'category_id' => $this->categoryIdFor($hit['category'] ?? null),
            'status' => $status,
            'is_online' => ($hit['isOnline'] ?? 'N') === 'Y',
            'location' => $this->clean($hit['location'] ?? null),
            'city' => $this->clean($hit['city'] ?? null),
            'state' => $this->clean($hit['state'] ?? null),
            'country' => $this->clean($hit['country'] ?? null),
            'latitude' => $lat,
            'longitude' => $lng,
            'region' => $this->regionCode(Arr::get($hit, 'region.OrganizationName')),
            'section' => $this->section(Arr::get($hit, 'section.OrganizationName')),
            'organizational_unit' => $this->org(Arr::get($hit, 'ou.OrganizationName')),
            'society' => $this->org(Arr::get($hit, 'committee.OrganizationName')),
            'experience_level' => $this->clean($hit['expLevel'] ?? null),
            'project_size' => $this->clean($hit['projectSize'] ?? null),
            'membership_grades' => array_values((array) ($hit['grades'] ?? [])) ?: null,
            'upskills' => $upskills ?: null,
            'hours_estimate' => isset($hit['timeDurationHours']) ? (int) $hit['timeDurationHours'] : null,
            'hours_frequency' => $this->frequency($hit['timeDurationFreq'] ?? null),
            'volunteers_needed' => max(1, (int) ($hit['noOfVolNeeded'] ?? 1)),
            'start_date' => $this->date($hit['startDate'] ?? null),
            'end_date' => $this->date($hit['endDate'] ?? null),
            'thumbnail_url' => $this->url($hit['thumbNailImgUrl'] ?? null),
            'external_creator_id' => $this->clean((string) ($hit['createdBy'] ?? '')),
            'external_status' => $display ?: null,
        ];
    }

    private function linkOwner(Opportunity $opportunity, ?string $memberNumber): void
    {
        if (blank($memberNumber)) {
            return;
        }

        $userId = Profile::query()->where('ieee_member_number', $memberNumber)->value('user_id');
        if (! $userId) {
            return;
        }

        if (! $opportunity->owners()->where('users.id', $userId)->exists()) {
            $hasOwner = $opportunity->owners()->wherePivot('role', 'owner')->exists();
            $opportunity->owners()->attach($userId, ['role' => $hasOwner ? 'co_owner' : 'owner']);
        }

        if (! $opportunity->created_by) {
            $opportunity->forceFill(['created_by' => $userId])->saveQuietly();
        }
    }

    /** @return array<int,int> */
    private function skillIdsFor(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $key = Str::slug($name);
            $ids[] = $this->skillIds[$key] ??= Skill::findOrCreateByName($name)->id;
        }

        return array_values(array_unique($ids));
    }

    private function categoryIdFor(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return $this->categoryIds[Str::slug($name)] ??= Category::findOrCreateByName($name)->id;
    }

    private function regionCode(?string $name): ?string
    {
        return $name && preg_match('/^R(\d{1,2})\b/i', trim($name), $m) ? 'R'.(int) $m[1] : null;
    }

    private function section(?string $name): ?string
    {
        $name = $this->org($name);

        return $name ? (preg_replace('/\s+Section$/i', '', $name) ?: $name) : null;
    }

    private function org(?string $name): ?string
    {
        $name = trim((string) $name);

        return $name === '' || strcasecmp($name, 'Not Applicable') === 0 ? null : $name;
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function url(?string $value): ?string
    {
        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_URL) && Str::startsWith($value, ['http://', 'https://']) ? Str::limit($value, 500, '') : null;
    }

    private function frequency(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return match (true) {
            str_contains($value, 'week') => 'week',
            str_contains($value, 'month') => 'month',
            default => 'overall',
        };
    }

    private function date(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('m/d/Y', $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function timestamp($ms): ?Carbon
    {
        return is_numeric($ms) && $ms > 0 ? Carbon::createFromTimestampMs((int) $ms) : null;
    }

    /** @return array{0: float|null, 1: float|null} */
    private function coordinates(array $hit): array
    {
        if (! empty($hit['latlon']) && str_contains($hit['latlon'], ',')) {
            [$lat, $lng] = array_map('floatval', explode(',', $hit['latlon'], 2));
            if ($lat != 0.0 || $lng != 0.0) {
                return [round($lat, 7), round($lng, 7)];
            }
        }

        $lat = (float) ($hit['latitude'] ?? 0);
        $lng = (float) ($hit['longitude'] ?? 0);

        return ($lat != 0.0 || $lng != 0.0) ? [round($lat, 7), round($lng, 7)] : [null, null];
    }
}
