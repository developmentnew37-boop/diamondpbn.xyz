<?php

namespace App\Http\Controllers;

use App\Models\WpSiteCategory;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WpSiteCategoryController extends Controller
{
    public function store(Request $request)
    {
        $name = trim((string) $request->input('name', ''));
        $normalized = WpSiteCategory::normalizeName($name);

        $request->merge(['name' => $name, 'name_normalized' => $normalized]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'name_normalized' => ['required', 'string', 'max:80', Rule::unique('wp_site_categories', 'name_normalized')],
        ], [
            'name_normalized.unique' => 'A category with this name already exists.',
        ]);

        WpSiteCategory::create([
            'user_id' => Workspace::ownerId(),
            'name' => $validated['name'],
            'name_normalized' => $validated['name_normalized'],
        ]);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, WpSiteCategory $wpSiteCategory)
    {
        $name = trim((string) $request->input('name', ''));
        $normalized = WpSiteCategory::normalizeName($name);

        $request->merge(['name' => $name, 'name_normalized' => $normalized]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'name_normalized' => [
                'required',
                'string',
                'max:80',
                Rule::unique('wp_site_categories', 'name_normalized')->ignore($wpSiteCategory->id),
            ],
        ], [
            'name_normalized.unique' => 'A category with this name already exists.',
        ]);

        $wpSiteCategory->update([
            'name' => $validated['name'],
            'name_normalized' => $validated['name_normalized'],
        ]);

        return back()->with('success', 'Category renamed.');
    }

    public function destroy(WpSiteCategory $wpSiteCategory)
    {
        $wpSiteCategory->delete();

        return back()->with('success', 'Category deleted. Sites in it are now uncategorized.');
    }
}
