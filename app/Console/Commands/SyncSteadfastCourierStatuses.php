<?php

namespace App\Console\Commands;

use App\Actions\Steadfast\SyncSteadfastOrderStatus;
use App\Exceptions\SteadfastCourierException;
use App\Models\OnlineOrder;
use App\Services\SteadfastCourierGateway;
use Illuminate\Console\Command;

class SyncSteadfastCourierStatuses extends Command
{
    protected $signature = 'steadfast:sync-statuses';

    protected $description = 'Sync Steadfast delivery statuses for shipped online orders';

    public function handle(SyncSteadfastOrderStatus $syncSteadfastOrderStatus, SteadfastCourierGateway $gateway): int
    {
        if (! $gateway->isConfigured()) {
            $this->warn('Steadfast API credentials are not configured.');

            return self::SUCCESS;
        }

        $orders = OnlineOrder::query()
            ->awaitingSteadfastStatusSync()
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No Steadfast orders require status sync.');

            return self::SUCCESS;
        }

        $synced = 0;
        $failed = 0;

        foreach ($orders as $order) {
            try {
                $syncSteadfastOrderStatus->execute($order);
                $synced++;
                $this->line("Synced order #{$order->id} ({$order->courier_status})");
            } catch (SteadfastCourierException $exception) {
                $failed++;
                $this->error("Order #{$order->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Steadfast sync complete. Synced: {$synced}, Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
