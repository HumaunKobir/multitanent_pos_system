<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $subscriber = Subscriber::query()
            ->where('email', $validated['email'])
            ->first();

        if ($subscriber?->status) {
            return back()->with('success', 'You are already subscribed to our newsletter.');
        }

        if ($subscriber) {
            $subscriber->update(['status' => true]);

            return back()->with('success', 'Welcome back! You have been resubscribed.');
        }

        Subscriber::create([
            'email' => $validated['email'],
            'status' => true,
        ]);

        return back()->with('success', 'Thank you for subscribing!');
    }
}
