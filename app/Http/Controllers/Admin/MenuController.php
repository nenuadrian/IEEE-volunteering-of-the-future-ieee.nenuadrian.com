<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index()
    {
        $header = MenuItem::where('location', 'header')->orderBy('display_order')->get();
        $footer = MenuItem::where('location', 'footer')->orderBy('display_order')->get();

        return view('admin.menus.index', compact('header', 'footer'));
    }

    public function create()
    {
        return view('admin.menus.form', $this->formData(new MenuItem(['location' => 'header', 'link_type' => 'route'])));
    }

    public function store(Request $request)
    {
        MenuItem::create($this->validateData($request));

        return redirect()->route('admin.menus.index')->with('status', 'Menu item added.');
    }

    public function edit(MenuItem $menu)
    {
        return view('admin.menus.form', $this->formData($menu));
    }

    public function update(Request $request, MenuItem $menu)
    {
        $menu->update($this->validateData($request));

        return redirect()->route('admin.menus.index')->with('status', 'Menu item updated.');
    }

    public function destroy(MenuItem $menu)
    {
        $menu->delete();

        return back()->with('status', 'Menu item removed.');
    }

    private function formData(MenuItem $menu): array
    {
        return [
            'menu' => $menu,
            'routes' => $this->linkableRoutes(),
            'pages' => Page::orderBy('title')->pluck('title', 'slug'),
        ];
    }

    private function linkableRoutes(): array
    {
        return [
            'home' => 'Home',
            'opportunities.index' => 'Opportunities',
            'opportunities.create' => 'Create opportunity',
            'volunteers.index' => 'Volunteer directory',
            'dashboard' => 'Dashboard',
            'contact.show' => 'Contact',
            'accessibility' => 'Accessibility'
        ];
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'location' => ['required', 'in:header,footer'],
            'label' => ['required', 'string', 'max:60'],
            'link_type' => ['required', 'in:url,route,page'],
            'value' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'new_tab' => ['nullable', 'boolean'],
        ]);
    }
}
