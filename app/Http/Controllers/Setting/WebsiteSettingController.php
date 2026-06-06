<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateWebsiteSettingRequest;
use App\Models\ConfigDictionary;
use App\Support\WebsiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteSettingController extends Controller
{
    public function edit(): Response
    {
        $this->authorize('setting.website.view');

        return Inertia::render('admin/setting/website/index', [
            'settings' => WebsiteSettings::forAdmin(),
        ]);
    }

    public function update(UpdateWebsiteSettingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $textValues = collect($validated)
            ->except(['logo', 'fav_icon'])
            ->map(fn ($value) => is_null($value) ? '' : (string) $value)
            ->all();

        ConfigDictionary::setMany($textValues);

        if ($request->hasFile('logo')) {
            $this->replaceUploadedFile('logo', $request->file('logo')->store('website', 'public'));
        }

        if ($request->hasFile('fav_icon')) {
            $this->replaceUploadedFile('fav_icon', $request->file('fav_icon')->store('website', 'public'));
        }

        return redirect()
            ->route('setting.website.edit')
            ->with('success', 'Website settings updated successfully.');
    }

    protected function replaceUploadedFile(string $key, string $path): void
    {
        $previous = ConfigDictionary::get($key);

        if ($previous && ! str_starts_with($previous, 'http')) {
            Storage::disk('public')->delete($previous);
        }

        ConfigDictionary::set($key, $path);
    }
}
