<?php

namespace App\Console\Commands;

use App\Services\CoinService;
use Illuminate\Console\Command;

class ExpireCoinsCommand extends Command
{
    protected $signature = 'coins:expire';

    protected $description = 'Remove expired customer coin lot balances';

    public function handle(CoinService $coinService): int
    {
        $result = $coinService->expireLots();

        $this->info(sprintf(
            'Expired %s coin(s) across %d lot(s) for %d customer(s).',
            number_format($result['coins'], 2),
            $result['lots'],
            $result['customers'],
        ));

        return self::SUCCESS;
    }
}
