<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\Categories\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\FundResource;
use App\Models\Category;
use App\Models\Fund;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->ordered()
            ->withCount('transactions')
            ->get();

        return Inertia::render('settings/Categories', [
            'categories' => CategoryResource::collection($categories)->resolve(),
            'funds' => FundResource::collection(Fund::query()->ordered()->get())->resolve(),
            'categoryTypes' => CategoryType::options(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::query()->create([
            ...$request->toAttributes(),
            'type' => $request->enum('type', CategoryType::class),
            'is_system' => false,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('category.created'),
        ]);

        return to_route('categories.index');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $attributes = $request->toAttributes();

        // A system category keeps its type, because transactions rely on it.
        if (! $category->is_system) {
            $attributes['type'] = $request->enum('type', CategoryType::class);
        }

        $category->update($attributes);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('category.updated'),
        ]);

        return to_route('categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->is_system) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('category.error.system'),
            ]);

            return to_route('categories.index');
        }

        if ($category->transactions()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('category.error.in_use'),
            ]);

            return to_route('categories.index');
        }

        $category->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('category.deleted'),
        ]);

        return to_route('categories.index');
    }
}
