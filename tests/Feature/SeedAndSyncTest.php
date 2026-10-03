<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\HourLog;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\IeeeOpportunitySync;
use Database\Seeders\CoreSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedAndSyncTest extends TestCase
{
    use RefreshDatabase;

    private function hit(array $overrides = []): array
    {
        return array_merge([
            'opportunityId' => 'abc-123',
            'title' => 'Communications Lead',
            'description' => 'Help Young Professionals see and use the training content.',
            'category' => 'Strategy',
            'expLevel' => 'Basic experience',
            'projectSize' => 'Quick task',
            'isOnline' => 'Y',
            'status' => 'active',
            'displayStatus' => 'Accepting Applicants',
            'skillsRequired' => ['Online content creation', 'Written communication'],
            'skillsOffered' => ['Communication', 'None'],
            'grades' => ['GSM', 'M'],
            'noOfVolNeeded' => 2,
            'timeDurationHours' => 5,
            'startDate' => '01/19/2026',
            'endDate' => '01/19/2027',
            'latlon' => '20.593684,78.96288',
            'createTs' => 1768772094613,
            'createdBy' => '98583749',
            'region' => ['OrganizationName' => 'R10 -Asia and Pacific'],
            'section' => ['OrganizationName' => 'Hyderabad Section'],
            'ou' => ['OrganizationName' => 'Not Applicable'],
            'committee' => ['OrganizationName' => 'IEEE Young Professionals'],
            'thumbNailImgUrl' => 'https://dc773e3yplvbs.cloudfront.net/image/max-dim/130x130/filters:quality(50)/vol-prd-files~files~x',
        ], $overrides);
    }

    public function test_api_hits_are_mapped_upserted_and_linked_to_their_creator(): void
    {
        $creator = User::factory()->create();
        $creator->profile->update(['ieee_member_number' => '98583749']);

        $sync = app(IeeeOpportunitySync::class);
        $run = $sync->run(null, [$this->hit()]);

        $this->assertSame('success', $run->status);
        $this->assertSame(1, $run->created_count);

        $o = Opportunity::where('external_id', 'abc-123')->firstOrFail();
        $this->assertSame(Opportunity::SOURCE_IEEE, $o->source);
        $this->assertSame(Opportunity::OPEN, $o->status);
        $this->assertSame('R10', $o->region);
        $this->assertSame('Hyderabad', $o->section);
        $this->assertNull($o->organizational_unit);
        $this->assertSame('IEEE Young Professionals', $o->society);
        $this->assertSame(['Communication'], $o->upskills);
        $this->assertSame('2026-01-19', $o->start_date->toDateString());
        $this->assertEqualsWithDelta(20.593684, $o->latitude, 0.0001);
        $this->assertSame('Strategy', $o->category->name);
        $this->assertCount(2, $o->skills);
        $this->assertTrue($o->isOwnedBy($creator), 'Creator linked via IEEE member number');
        $this->assertStringContainsString('640x400', $o->thumbnail());

        // Same data again: nothing changes.
        $again = $sync->run(null, [$this->hit()]);
        $this->assertSame(1, $again->unchanged_count);

        // Changed status and a removed opportunity.
        $third = $sync->run(null, [
            $this->hit(['displayStatus' => 'Opportunity on hold', 'status' => 'paused']),
            $this->hit(['opportunityId' => 'new-1', 'title' => 'Video editor']),
        ]);
        $this->assertSame(1, $third->updated_count);
        $this->assertSame(1, $third->created_count);
        $this->assertSame(Opportunity::ON_HOLD, $o->fresh()->status);

        $fourth = $sync->run(null, [$this->hit(['opportunityId' => 'new-1', 'title' => 'Video editor'])]);
        $this->assertSame(1, $fourth->closed_count);
        $this->assertSame(Opportunity::COMPLETED, $o->fresh()->status);
    }

    public function test_full_seed_works_on_the_test_database_and_can_be_purged(): void
    {
        foreach (['DEMO_USERS' => '30', 'SEED_DEMO_DATA' => 'true'] as $k => $v) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $_SERVER[$k] = $v;
        }

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::admins()->count());
        $this->assertGreaterThanOrEqual(30, User::where('email', 'like', '%@'.DemoDataSeeder::DOMAIN)->count());
        $this->assertGreaterThan(70, Opportunity::where('source', 'ieee')->count());
        $this->assertGreaterThan(50, Opportunity::where('source', 'local')->count());
        $this->assertGreaterThan(0, Application::count());
        $this->assertGreaterThan(0, HourLog::where('status', 'approved')->count());

        // Seeding core data twice is safe.
        $this->seed(CoreSeeder::class);
        $this->assertSame(1, User::admins()->count());

        $this->artisan('demo:purge', ['--force' => true])->assertSuccessful();
        $this->assertSame(0, User::where('email', 'like', '%@'.DemoDataSeeder::DOMAIN)->count());
        $this->assertSame(0, Opportunity::withTrashed()->where('source', 'local')->count());
        $this->assertGreaterThan(70, Opportunity::where('source', 'ieee')->count(), 'Imported opportunities are kept');
        $this->assertSame(1, User::count(), 'Only the admin remains');

        foreach (['DEMO_USERS', 'SEED_DEMO_DATA'] as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
    }
}
