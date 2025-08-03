<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Command\Command as SymfonyCommand;


class DeactivateExpiredKifurushi extends Command
{
    protected $signature = 'kifurushi:deactivate-expired';
    protected $description = 'Disable kifurushi purchases whose end_date has passed.';

    public function handle(): int
    {
        $now = Carbon::today();

        $count = DB::table('kifurushi_purchases')
            ->where('is_active', true)
            ->whereDate('end_date', '<', $now)
            ->update(['is_active' => false]);

        $this->info("✅ Deactivated $count expired kifurushi purchases.");

        return SymfonyCommand::SUCCESS;
    }
}
