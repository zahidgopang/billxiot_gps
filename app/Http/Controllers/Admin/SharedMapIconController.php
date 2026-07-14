<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SharedMapIcon;
use App\Services\Authorization\RbacService;
use App\Services\Tracking\SharedMapIconService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SharedMapIconController extends Controller
{
    public function __construct(
        private SharedMapIconService $sharedIcons,
        private RbacService $rbac,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->rbac->isSuperAdmin($request->user()), 403);

        $categories = $this->sharedIcons->uploadCategories();
        $category = $this->sharedIcons->normalizeCategory($request->query('category'));

        return view('admin.shared-map-icons.index', [
            'icons' => $this->sharedIcons->all($category),
            'allIcons' => $this->sharedIcons->all(),
            'categories' => $categories,
            'activeCategory' => $category,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->rbac->isSuperAdmin($request->user()), 403);

        $allowed = $this->sharedIcons->allowedCategoryIds();
        $maxKb = (int) config('vehicle_icons.upload.max_kb', 512);
        $validated = $request->validate([
            'icon' => ['required'],
            'icon.*' => ['file', 'max:'.$maxKb],
            'label' => ['nullable', 'string', 'max:120'],
            'category' => ['required', 'string', Rule::in($allowed)],
            'rotation_offset' => ['nullable', 'integer', Rule::in([0, 90, -90, 180, 270])],
        ]);

        $files = $request->file('icon');
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }
        $files = array_values(array_filter($files));

        if ($files === []) {
            return back()->withErrors(['icon' => __('app.map.custom_icon_invalid_image')])->withInput();
        }

        try {
            foreach ($files as $index => $file) {
                $label = $validated['label'] ?? null;
                if (count($files) > 1) {
                    $label = null; // use each file name when uploading many
                }
                $this->sharedIcons->store(
                    $request->user(),
                    $file,
                    $label,
                    $validated['category'],
                    // SVG Repo–style suggestion art is usually side-view (nose right / East).
                    $validated['rotation_offset'] ?? -90,
                );
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('admin.shared-map-icons.index', ['category' => $validated['category']])
            ->with('success', __('app.map.shared_icon_uploaded'));
    }

    public function update(Request $request, SharedMapIcon $sharedMapIcon): RedirectResponse
    {
        abort_unless($this->rbac->isSuperAdmin($request->user()), 403);

        $validated = $request->validate([
            'rotation_offset' => ['required', 'integer', Rule::in([0, 90, -90, 180, 270])],
        ]);

        $this->sharedIcons->updateRotationOffset($sharedMapIcon, $validated['rotation_offset']);

        return redirect()
            ->route('admin.shared-map-icons.index', ['category' => $sharedMapIcon->category ?: 'vehicles'])
            ->with('success', __('app.map.shared_icon_orientation_saved'));
    }

    public function destroy(Request $request, SharedMapIcon $sharedMapIcon): RedirectResponse
    {
        abort_unless($this->rbac->isSuperAdmin($request->user()), 403);

        $category = $sharedMapIcon->category ?: 'vehicles';
        $this->sharedIcons->delete($sharedMapIcon);

        return redirect()
            ->route('admin.shared-map-icons.index', ['category' => $category])
            ->with('success', __('app.map.shared_icon_deleted'));
    }
}
