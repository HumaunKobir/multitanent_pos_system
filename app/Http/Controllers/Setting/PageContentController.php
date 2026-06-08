<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdatePageContentRequest;
use App\Support\PageContent;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PageContentController extends Controller
{
    public function edit(string $page): Response
    {
        $this->authorize('setting.page-content.view');

        return Inertia::render('admin/setting/page-content/edit', [
            'page' => PageContent::forAdmin($page),
        ]);
    }

    public function update(UpdatePageContentRequest $request, string $page): RedirectResponse
    {
        PageContent::definition($page);

        PageContent::set($page, $request->validated('content') ?? '');

        return redirect()
            ->route('setting.page-content.edit', ['page' => $page])
            ->with('success', 'Page content updated successfully.');
    }
}
