<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('setting.faq.view');

        $faqs = Faq::query()
            ->when($request->search, fn ($q, $search) => $q->where('question', 'like', "%{$search}%"))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/setting/faq/index', [
            'faqs' => $faqs,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('setting.faq.create');

        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:0,1'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
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
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:0,1'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
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
}
