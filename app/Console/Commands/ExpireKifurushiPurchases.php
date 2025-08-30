<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KifurushiPurchase;
use Carbon\Carbon;

class ExpireKifurushiPurchases extends Command
{
    protected $signature = 'kifurushi:expire';
    protected $description = 'Mark kifurushi purchases as expired if end_date is reached or passed';

    public function handle()
    {
        $today = Carbon::now('Africa/Nairobi')->setTime(3, 0, 0);

        $expiredCount = KifurushiPurchase::where('is_active', true)
            ->where('status', 'approved')
            ->whereDate('end_date', '<=', $today)
            ->update([
                'status' => 'expired',
                'is_active' => false,
                'updated_at' => now(),
            ]);

        $this->info("Vifurushi $expiredCount vimebadilishwa baada ya kuexpire.");
    }
}
