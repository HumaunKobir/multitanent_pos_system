<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SliderController extends Controller
{
    public function index(Request $request): Response
    {
        $sliders = Slider::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/setting/slider/index', [
            'sliders' => $sliders,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5048'],
            'status' => ['required', 'in:0,1'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('sliders', 'public');
        }

        $data['status'] = (int) $data['status'];

        Slider::create($data);

        return redirect()->route('setting.slider.index')
            ->with('success', 'Slider created successfully.');
    }

    public function update(Request $request, Slider $slider): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5048'],
            'status' => ['required', 'in:0,1'],
        ]);

        if ($request->hasFile('image')) {
            if ($slider->image) {
                Storage::disk('public')->delete($slider->image);
            }

            $data['image'] = $request->file('image')->store('sliders', 'public');
        } else {
            unset($data['image']);
        }

        $data['status'] = (int) $data['status'];

        $slider->update($data);

        return redirect()->route('setting.slider.index')
            ->with('success', 'Slider updated successfully.');
    }

    public function destroy(Slider $slider): RedirectResponse
    {
        if ($slider->image) {
            Storage::disk('public')->delete($slider->image);
        }

        $slider->delete();

        return redirect()->route('setting.slider.index')
            ->with('success', 'Slider deleted successfully.');
    }
}
