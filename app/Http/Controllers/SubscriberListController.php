<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriberListController extends Controller
{
    public function index(Request $request): Response
    {
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
        ]);
    }

    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return redirect()->route('subscriber-list.index')
            ->with('success', 'Subscriber removed successfully.');
    }
}
