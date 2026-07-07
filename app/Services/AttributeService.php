<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Collection;

class AttributeService
{
    /**
     * Get attributes applicable to a category (walk up to parent if sub-category has none defined, fallback to parent's attributes).
     *
     * @param int $categoryId
     * @return Collection
     */
    public function getAttributesForCategory(int $categoryId): Collection
    {
        $category = Category::find($categoryId);
        if (!$category) {
            return collect();
        }

        while ($category) {
            $attributes = $category->attributes()->with('values')->get();
            if ($attributes->isNotEmpty()) {
                return $attributes;
            }
            $category = $category->parent;
        }

        return collect();
    }
}
