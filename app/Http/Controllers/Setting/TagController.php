<?php

namespace App\Http\Controllers\Setting;

use App\Enums\CommonStatus;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('setting.tag.view');

        $tags = Tag::query()
            ->forPanel()
            ->with('parent:id,name')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Tag $tag): array => [
                'id' => $tag->id,
                'parent_id' => $tag->parent_id,
                'name' => $tag->name,
                'image' => $tag->image,
                'status' => $tag->status->value,
                'status_label' => $tag->status->name,
                'parent' => $tag->parent ? [
                    'id' => $tag->parent->id,
                    'name' => $tag->parent->name,
                ] : null,
            ]);

        return Inertia::render('admin/setting/tag/index', [
            'tags' => $tags,
            'filters' => $request->only('search'),
            'parentOptions' => $this->parentOptions(),
            'statusOptions' => $this->statusOptions(),
            'tagHierarchy' => Tag::query()->forPanel()->get(['id', 'parent_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('setting.tag.create');

        $data = $this->validatedData($request);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('tags', 'public');
        }

        $tag = Tag::create($data);

        if ($request->wantsJson()) {
            return response()->json(['value' => (string) $tag->id, 'label' => $tag->name], 201);
        }

        return redirect()->route('setting.tag.index')
            ->with('success', 'Tag created successfully.');
    }

    public function update(Request $request, Tag $tag): RedirectResponse
    {
        $this->authorize('setting.tag.update');

        $tag = $this->resolveTag($tag);
        $data = $this->validatedData($request, $tag);

        if ($request->hasFile('image')) {
            if ($tag->image) {
                Storage::disk('public')->delete($tag->image);
            }
            $data['image'] = $request->file('image')->store('tags', 'public');
        } else {
            unset($data['image']);
        }

        $tag->update($data);

        return redirect()->route('setting.tag.index')
            ->with('success', 'Tag updated successfully.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorize('setting.tag.delete');

        $tag = $this->resolveTag($tag);

        if ($tag->children()->exists()) {
            return redirect()->route('setting.tag.index')
                ->with('error', 'Cannot delete a tag that has child tags.');
        }

        if ($tag->image) {
            Storage::disk('public')->delete($tag->image);
        }

        $tag->delete();

        return redirect()->route('setting.tag.index')
            ->with('success', 'Tag deleted successfully.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function parentOptions(?Tag $exclude = null): array
    {
        $query = Tag::query()->forPanel()->orderBy('name');

        if ($exclude !== null) {
            $query->whereNotIn('id', $this->descendantIds($exclude));
        }

        $options = [
            ['value' => '__none__', 'label' => '--Select--'],
        ];

        foreach ($query->pluck('name', 'id') as $id => $name) {
            $options[] = ['value' => (string) $id, 'label' => $name];
        }

        return $options;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function statusOptions(): array
    {
        return collect(CommonStatus::asSelectArray())
            ->map(fn (string $label, int $value) => ['value' => (string) $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedData(Request $request, ?Tag $tag = null): array
    {
        if (in_array($request->input('parent_id'), [null, '', '__none__'], true)) {
            $request->merge(['parent_id' => null]);
        }

        $invalidParentIds = $tag ? $this->descendantIds($tag) : [];

        $data = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                Rule::notIn($invalidParentIds),
                Rule::exists('tags', 'id')->where(fn ($query) => $this->applyPanelScope($query)),
            ],
            'name' => ['required', 'string', 'max:191'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', Rule::in(CommonStatus::getValues())],
        ]);

        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['status'] = (int) $data['status'];
        $data['branch_id'] = Auth::user()?->branch_id;

        return $data;
    }

    protected function resolveTag(Tag $tag): Tag
    {
        return Tag::query()->forPanel()->whereKey($tag->id)->firstOrFail();
    }

    protected function applyPanelScope($query): void
    {
        $branchId = Auth::user()?->branch_id;

        if ($branchId === null) {
            $query->whereNull('branch_id');

            return;
        }

        $query->where('branch_id', $branchId);
    }

    /**
     * @return list<int>
     */
    protected function descendantIds(Tag $tag): array
    {
        $ids = collect([$tag->id]);
        $queue = collect([$tag->id]);

        while ($queue->isNotEmpty()) {
            $parentId = $queue->shift();
            $childIds = Tag::query()
                ->forPanel()
                ->where('parent_id', $parentId)
                ->pluck('id');

            foreach ($childIds as $childId) {
                if (! $ids->contains($childId)) {
                    $ids->push($childId);
                    $queue->push($childId);
                }
            }
        }

        return $ids->all();
    }
}
