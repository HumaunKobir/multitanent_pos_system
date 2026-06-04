<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactListController extends Controller
{
    public function index(Request $request): Response
    {
        $subscribers = Subscriber::query()
            ->when($request->search, fn ($q, $s) => $q->where('email', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/contact-list/index', [
            'subscribers' => $subscribers,
            'filters' => $request->only('search'),
        ]);
    }

    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return redirect()->route('contact-list.index')
            ->with('success', 'Subscriber removed successfully.');
    }
}
