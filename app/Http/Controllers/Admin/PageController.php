<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesEditorBody;
use App\Http\Controllers\Admin\Concerns\HandlesSeoMeta;
use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    use HandlesEditorBody, HandlesSeoMeta;

    /** Slugs that would collide with application routes. */
    private const RESERVED = [
        'opportunities', 'volunteers', 'dashboard', 'my', 'profile', 'admin', 'login', 'register', 'logout',
        'contact', 'accessibility', 'lookup', 'applications', 'hours', 'forgot-password', 'reset-password',
        'verify-email', 'email', 'confirm-password', 'password', 'up', 'storage', 'build', 'vendor',
    ];

    public function index()
    {
        $pages = Page::latest()->paginate(20);

        return view('admin.pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.pages.form', ['page' => new Page(['status' => 'published'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $page = new Page([
            'user_id' => $request->user()->id,
            'status' => $data['status'],
            'title' => $data['title'],
            'slug' => $data['slug'] ?: Page::generateSlug($data['title']),
            'published_at' => $data['status'] === 'published' ? now() : null,
        ]);
        $this->applyEditorBody($page, $request);
        $this->applySeoMeta($page, $request);
        $page->save();

        return redirect()->route('admin.pages.index')->with('status', 'Page created.');
    }

    public function edit(Page $page)
    {
        return view('admin.pages.form', ['page' => $page]);
    }

    public function update(Request $request, Page $page)
    {
        $data = $this->validateData($request, $page);

        $page->fill([
            'title' => $data['title'],
            'slug' => $data['slug'] ?: Page::generateSlug($data['title'], $page->id),
            'status' => $data['status'],
        ]);
        $this->applyEditorBody($page, $request);
        if ($data['status'] === 'published' && ! $page->published_at) {
            $page->published_at = now();
        }
        $this->applySeoMeta($page, $request);
        $page->save();

        return redirect()->route('admin.pages.index')->with('status', 'Page updated.');
    }

    public function destroy(Page $page)
    {
        $page->delete();

        return back()->with('status', 'Page deleted.');
    }

    private function validateData(Request $request, ?Page $page = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'nullable', 'string', 'max:200', 'alpha_dash', Rule::notIn(self::RESERVED),
                Rule::unique('pages', 'slug')->ignore($page?->id),
            ],
            'editor_data' => ['nullable', 'string', 'max:500000'],
            'status' => ['required', 'in:draft,published'],
        ] + $this->seoRules());
    }
}
