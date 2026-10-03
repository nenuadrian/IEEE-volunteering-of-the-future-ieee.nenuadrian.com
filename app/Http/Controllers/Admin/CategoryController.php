<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Opportunity;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Opportunity types (Technical, Event-based, Humanitarian…). */
class CategoryController extends Controller
{
    public function index()
    {
        return view('admin.categories.index', [
            'categories' => Category::query()
                ->withCount([
                    'opportunities',
                    'opportunities as open_opportunities_count' => fn ($q) => $q->where('status', Opportunity::OPEN),
                ])
                ->ordered()
                ->get(),
            'nextOrder' => (int) Category::query()->max('display_order') + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $category = Category::create($this->attributes($request, $data) + ['slug' => Str::slug($data['name'])]);

        return back()->with('status', 'Opportunity type “'.$category->name.'” created.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        // The active switch in the table posts on its own.
        if ($request->has('toggle_active')) {
            $category->update(['is_active' => ! $category->is_active]);

            return back()->with('status', '“'.$category->name.'” is now '.($category->is_active ? 'active' : 'hidden from new opportunities').'.');
        }

        $data = $request->validate($this->rules($category));

        // The slug stays put so existing links and saved searches keep working.
        $category->update($this->attributes($request, $data));

        return back()->with('status', 'Opportunity type “'.$category->name.'” updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $used = $category->opportunities()->count();

        if ($used > 0) {
            return back()->withErrors(['category' => '“'.$category->name.'” is used by '.$used.' '.Str::plural('opportunity', $used)
                .'. Deactivate it instead: it stays on those opportunities but can’t be picked for new ones.']);
        }

        $category->delete();

        return back()->with('status', 'Opportunity type “'.$category->name.'” deleted.');
    }

    private function rules(?Category $category = null): array
    {
        return [
            'name' => ['required', 'string', 'max:80', function (string $attribute, mixed $value, Closure $fail) use ($category) {
                $slug = Str::slug((string) $value);
                $taken = Category::query()
                    ->where(fn ($q) => $q->where('slug', $slug)->orWhereRaw('LOWER(name) = ?', [Str::lower(trim((string) $value))]))
                    ->when($category, fn ($q) => $q->whereKeyNot($category->id))
                    ->exists();

                if ($slug === '') {
                    $fail('The name needs at least one letter or number.');
                } elseif ($taken) {
                    $fail('An opportunity type with this name already exists.');
                }
            }],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function attributes(Request $request, array $data): array
    {
        return [
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'color' => isset($data['color']) ? Str::lower($data['color']) : null,
            'display_order' => (int) ($data['display_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
