<?php

namespace App\Services;

use App\Mail\OnlineOrderPaymentConfirmedMail;
use App\Mail\OnlineOrderPlacedMail;
use App\Models\OnlineOrder;
use App\Support\DynamicMailConfigurator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class OnlineOrderNotificationService
{
    public function sendOrderPlacedOnce(OnlineOrder $order): void
    {
        if ($order->order_email_sent_at !== null) {
            return;
        }

        $email = $order->notificationEmail();

        if ($email === null) {
            return;
        }

        $freshOrder = $order->fresh(['products']);

        DynamicMailConfigurator::apply();

        try {
            Mail::to($email)->send(new OnlineOrderPlacedMail($freshOrder));
            $freshOrder->update(['order_email_sent_at' => now()]);
        } catch (Throwable $exception) {
            Log::warning('Online order email failed.', [
                'order_id' => $order->id,
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function sendPaymentConfirmedOnce(OnlineOrder $order): void
    {
        if ($order->payment_email_sent_at !== null) {
            return;
        }

        if (! in_array(strtolower((string) $order->payment_status), ['paid'], true)) {
            return;
        }

        $email = $order->notificationEmail();

        if ($email === null) {
            return;
        }

        $freshOrder = $order->fresh(['products']);
        $isCombined = $freshOrder->order_email_sent_at === null;

        DynamicMailConfigurator::apply();

        try {
            Mail::to($email)->send(new OnlineOrderPaymentConfirmedMail($freshOrder, $isCombined));

            $freshOrder->update([
                'payment_email_sent_at' => now(),
                'order_email_sent_at' => $isCombined ? now() : $freshOrder->order_email_sent_at,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Online order payment email failed.', [
                'order_id' => $order->id,
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
