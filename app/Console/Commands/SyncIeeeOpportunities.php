<?php

namespace App\Console\Commands;

use App\Services\IeeeOpportunitySync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('opportunities:sync {--keep-missing : Do not close imported opportunities that disappeared from the feed}')]
#[Description('Refresh opportunities from the public volunteer.ieee.org API')]
class SyncIeeeOpportunities extends Command
{
    public function handle(IeeeOpportunitySync $sync): int
    {
        $this->info('Fetching opportunities from volunteer.ieee.org…');

        $run = $sync->run(closeMissing: ! $this->option('keep-missing'));

        if ($run->status !== 'success') {
            $this->error('Sync failed: '.$run->error);

            return self::FAILURE;
        }

        $this->table(
            ['Fetched', 'Created', 'Updated', 'Unchanged', 'Closed'],
            [[$run->fetched_count, $run->created_count, $run->updated_count, $run->unchanged_count, $run->closed_count]],
        );

        return self::SUCCESS;
    }
}
