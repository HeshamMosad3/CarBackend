<?php

namespace App\Console\Commands;

use App\Http\Services\Shared\ShippingWorkflowService;
use Illuminate\Console\Command;

class ExpireUnpaidShippingCommand extends Command
{
    protected $signature = 'shipping:expire-unpaid';

    protected $description = 'Cancel shipping quotes left unpaid past their deadline and remind customers who are close to it';

    public function handle(ShippingWorkflowService $workflow): int
    {
        $result = $workflow->expireUnpaid();

        $this->info("Canceled: {$result['canceled']}, reminded: {$result['reminded']}");

        return self::SUCCESS;
    }
}
