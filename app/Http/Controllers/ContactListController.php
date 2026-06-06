<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ContactListController extends Controller
{
    public function index(Request $request): Response
    {
        $contacts = Contact::query()
            ->ownBranch()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/contact-list/index', [
            'contacts' => $contacts,
            'filters' => $request->only('search'),
        ]);
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->authorizeContact($contact);

        $contact->delete();

        return redirect()->route('contact-list.index')
            ->with('success', 'Message deleted successfully.');
    }

    protected function authorizeContact(Contact $contact): void
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId !== null && $contact->branch_id !== $branchId) {
            abort(403);
        }
    }
}
