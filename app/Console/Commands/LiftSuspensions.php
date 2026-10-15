<?php

namespace App\Console\Commands;

use App\Services\SuspensionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('suspensions:lift')]
#[Description('Lift every suspension whose end date has passed')]
class LiftSuspensions extends Command
{
    public function handle(SuspensionService $suspensions): int
    {
        $this->info("Lifted {$suspensions->liftExpired()} suspension(s).");

        return self::SUCCESS;
    }
}
