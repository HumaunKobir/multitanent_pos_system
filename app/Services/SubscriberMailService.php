<?php

namespace App\Services;

use App\Mail\SubscriberNewsletterMail;
use App\Models\Subscriber;
use App\Support\DynamicMailConfigurator;
use App\Support\MailSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SubscriberMailService
{
    /**
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendToSubscriber(Subscriber $subscriber, string $subject, string $body): array
    {
        return $this->sendToMany(collect([$subscriber]), $subject, $body);
    }

    /**
     * @param  list<int>|null  $subscriberIds
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function sendBulk(?array $subscriberIds, bool $allActive, string $subject, string $body): array
    {
        $query = Subscriber::query()->where('status', true);

        if (! $allActive) {
            $query->whereIn('id', $subscriberIds ?? []);
        }

        return $this->sendToMany($query->get(), $subject, $body);
    }

    public function ensureMailIsConfigured(): void
    {
        if (! MailSettings::isConfigured()) {
            throw new RuntimeException('SMTP mail is not configured. Please set up mail credentials in Website Settings.');
        }
    }

    /**
     * @return Builder<Subscriber>
     */
    public function activeSubscribersQuery(): Builder
    {
        return Subscriber::query()->active();
    }

    /**
     * @param  Collection<int, Subscriber>  $subscribers
     * @return array{sent: int, failed: int, skipped: int}
     */
    protected function sendToMany(Collection $subscribers, string $subject, string $body): array
    {
        $this->ensureMailIsConfigured();
        DynamicMailConfigurator::apply();

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($subscribers as $subscriber) {
            if (! $subscriber->status) {
                $skipped++;

                continue;
            }

            try {
                Mail::to($subscriber->email)->send(new SubscriberNewsletterMail($subject, $body));
                $sent++;
            } catch (Throwable $exception) {
                $failed++;

                Log::warning('Subscriber newsletter email failed.', [
                    'subscriber_id' => $subscriber->id,
                    'email' => $subscriber->email,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return compact('sent', 'failed', 'skipped');
    }
}
