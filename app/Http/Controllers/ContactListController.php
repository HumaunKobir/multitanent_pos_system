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
        $this->authorize('contact-list.view');

        $contacts = Contact::query()
            ->accessibleAtBranch($this->ecommerceBranchId())
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
        $this->authorize('contact-list.delete');
        $this->authorizeContact($contact);

        $contact->delete();

        return redirect()->route('contact-list.index')
            ->with('success', 'Message deleted successfully.');
    }

    protected function authorizeContact(Contact $contact): void
    {
        if ($contact->branch_id !== $this->ecommerceBranchId()) {
            abort(403);
        }
    }

    protected function ecommerceBranchId(): int
    {
        return Auth::user()?->ecommercePanelBranchId()
            ?? abort(403);
    }
}
