<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('setting.faq.view');

        $items = Faq::query()
            ->when($request->search, fn ($q, $search) => $q->where('question', 'like', "%{$search}%"))
            ->ordered()
            ->get();

        return Inertia::render('admin/setting/faq/index', [
            'faqs' => [
                'data' => $items,
                'from' => $items->isEmpty() ? 0 : 1,
                'links' => [],
            ],
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('setting.faq.create');

        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'status' => ['required', 'in:0,1'],
        ]);

        $data['sort_order'] = (int) Faq::query()->max('sort_order') + 1;
        $data['status'] = (int) $data['status'];

        Faq::create($data);

        return redirect()->route('setting.faq.index')
            ->with('success', 'FAQ created successfully.');
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $this->authorize('setting.faq.update');

        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'status' => ['required', 'in:0,1'],
        ]);

        $data['status'] = (int) $data['status'];

        $faq->update($data);

        return redirect()->route('setting.faq.index')
            ->with('success', 'FAQ updated successfully.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $this->authorize('setting.faq.delete');

        $faq->delete();

        return redirect()->route('setting.faq.index')
            ->with('success', 'FAQ deleted successfully.');
    }

    public function updateOrder(Request $request): RedirectResponse
    {
        $this->authorize('setting.faq.update');

        $validated = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*.id' => ['required', 'integer', Rule::exists('faqs', 'id')],
            'orders.*.sort_order' => ['required', 'integer', 'min:1'],
        ]);

        foreach ($validated['orders'] as $order) {
            Faq::query()
                ->whereKey($order['id'])
                ->update(['sort_order' => $order['sort_order']]);
        }

        return redirect()->route('setting.faq.index');
    }
}
