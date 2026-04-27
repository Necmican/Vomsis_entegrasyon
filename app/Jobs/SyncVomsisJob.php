<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\VomsisService; 

class SyncVomsisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    
    public function handle(VomsisService $vomsisService): void
    {
        $vomsisService->syncBanks();
        $vomsisService->syncAccounts();
        $vomsisService->syncTransactions();
    }
}