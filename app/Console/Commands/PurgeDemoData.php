<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Opportunity;
use App\Models\SearchLog;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('demo:purge {--force : Skip the confirmation prompt}')]
#[Description('Remove the seeded demo volunteers and everything they created, keeping real accounts and imported opportunities')]
class PurgeDemoData extends Command
{
    public function handle(): int
    {
        $domain = '@'.DemoDataSeeder::DOMAIN;
        $demoIds = User::where('email', 'like', '%'.$domain)->pluck('id');

        if ($demoIds->isEmpty()) {
            $this->info('No demo accounts found — nothing to purge.');

            return self::SUCCESS;
        }

        // Local opportunities whose every owner is a demo account.
        $opportunityIds = Opportunity::withTrashed()
            ->where('source', Opportunity::SOURCE_LOCAL)
            ->whereHas('owners', fn ($q) => $q->whereIn('users.id', $demoIds))
            ->whereDoesntHave('owners', fn ($q) => $q->whereNotIn('users.id', $demoIds))
            ->pluck('id');

        $this->table(['What', 'Count'], [
            ['Demo accounts', $demoIds->count()],
            ['Opportunities owned only by demo accounts', $opportunityIds->count()],
        ]);

        if (! $this->option('force') && ! $this->confirm('Permanently delete this demo data?')) {
            return self::FAILURE;
        }

        // Anonymous searches can't be attributed, so only those logged up to
        // the moment the demo dataset was generated are removed.
        $seededAt = Setting::get('demo_seeded_at');

        DB::transaction(function () use ($demoIds, $opportunityIds, $seededAt) {
            Activity::whereIn('user_id', $demoIds)->delete();
            Activity::where('subject_type', (new Opportunity)->getMorphClass())->whereIn('subject_id', $opportunityIds)->delete();
            SearchLog::whereIn('user_id', $demoIds)->delete();
            if ($seededAt) {
                SearchLog::whereNull('user_id')->where('created_at', '<=', $seededAt)->delete();
            }

            Opportunity::withTrashed()->whereIn('id', $opportunityIds)->forceDelete();
            // Applications, hours, endorsements, saves and skill links cascade with the users.
            User::whereIn('id', $demoIds)->delete();

            // Imported opportunities stay, but their seeded counters reset.
            Opportunity::where('source', Opportunity::SOURCE_IEEE)->update(['filled_at' => null, 'views_count' => 0]);
        });

        Setting::put('demo_seeded_at', null);

        $this->info('Demo data removed. Real accounts, imported opportunities and settings were kept.');

        return self::SUCCESS;
    }
}
