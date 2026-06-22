<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendBulkSubscriberMailRequest;
use App\Http\Requests\SendSubscriberMailRequest;
use App\Models\Subscriber;
use App\Services\SubscriberMailService;
use App\Support\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SubscriberListController extends Controller
{
    public function index(Request $request, SubscriberMailService $subscriberMailService): Response
    {
        $this->authorize('subscriber-list.view');

        $subscribers = Subscriber::query()
            ->when($request->search, function ($query, $search) {
                $query->where('email', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/subscriber-list/index', [
            'subscribers' => $subscribers,
            'filters' => $request->only('search'),
            'mailConfigured' => MailSettings::isConfigured(),
            'activeSubscriberCount' => $subscriberMailService->activeSubscribersQuery()->count(),
        ]);
    }

    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $this->authorize('subscriber-list.delete');

        $subscriber->delete();

        return redirect()->route('subscriber-list.index')
            ->with('success', 'Subscriber removed successfully.');
    }

    public function sendMail(
        SendSubscriberMailRequest $request,
        Subscriber $subscriber,
        SubscriberMailService $subscriberMailService,
    ): RedirectResponse {
        $this->authorize('subscriber-list.send-mail');

        if (! $subscriber->status) {
            return redirect()->route('subscriber-list.index')
                ->with('error', 'Cannot send mail to an inactive subscriber.');
        }

        try {
            $result = $subscriberMailService->sendToSubscriber(
                $subscriber,
                $request->validated('subject'),
                $request->validated('body'),
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('subscriber-list.index')
                ->with('error', $exception->getMessage());
        }

        if ($result['sent'] === 0) {
            return redirect()->route('subscriber-list.index')
                ->with('error', 'Failed to send email. Please check your SMTP settings.');
        }

        return redirect()->route('subscriber-list.index')
            ->with('success', "Email sent successfully to {$subscriber->email}.");
    }

    public function sendBulkMail(
        SendBulkSubscriberMailRequest $request,
        SubscriberMailService $subscriberMailService,
    ): RedirectResponse {
        $this->authorize('subscriber-list.send-mail');

        $validated = $request->validated();
        $allActive = filter_var($validated['all_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

        try {
            $result = $subscriberMailService->sendBulk(
                $validated['subscriber_ids'] ?? null,
                $allActive,
                $validated['subject'],
                $validated['body'],
            );
        } catch (RuntimeException $exception) {
            return redirect()->route('subscriber-list.index')
                ->with('error', $exception->getMessage());
        }

        if ($result['sent'] === 0) {
            $message = $result['failed'] > 0
                ? 'Failed to send emails. Please check your SMTP settings.'
                : 'No active subscribers were selected to receive this email.';

            return redirect()->route('subscriber-list.index')
                ->with('error', $message);
        }

        $message = "Email sent to {$result['sent']} subscriber(s).";

        if ($result['failed'] > 0) {
            $message .= " {$result['failed']} failed.";
        }

        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} skipped.";
        }

        return redirect()->route('subscriber-list.index')
            ->with('success', $message);
    }
}
