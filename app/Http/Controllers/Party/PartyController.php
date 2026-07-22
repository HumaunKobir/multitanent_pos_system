<?php

namespace App\Http\Controllers\Party;

use App\Http\Controllers\Controller;
use App\Http\Requests\Party\StorePartyRequest;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('party.parties.view');

        $parties = Party::query()
            ->ownBranch()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/party/parties/index', [
            'parties' => $parties,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(StorePartyRequest $request): RedirectResponse
    {
        $this->authorize('party.parties.create');

        Party::query()->create([
            ...$request->validated(),
            'branch_id' => $request->user()?->branch_id,
        ]);

        return back()->with('success', 'Party created successfully.');
    }

    public function update(StorePartyRequest $request, Party $party): RedirectResponse
    {
        $this->authorize('party.parties.update');
        $this->ensureAccessible($party);

        $party->update($request->validated());

        return back()->with('success', 'Party updated successfully.');
    }

    public function destroy(Party $party): RedirectResponse
    {
        $this->authorize('party.parties.delete');
        $this->ensureAccessible($party);

        if ($party->vouchers()->exists()) {
            return back()->with('error', 'Cannot delete party used on vouchers.');
        }

        $party->delete();

        return back()->with('success', 'Party deleted successfully.');
    }

    private function ensureAccessible(Party $party): void
    {
        $branchId = auth()->user()?->branch_id;

        if ($branchId !== null && (int) $party->branch_id !== (int) $branchId) {
            abort(403);
        }
    }
}
