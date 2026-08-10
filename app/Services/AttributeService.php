<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Support\Collection;

class AttributeService
{
    /**
     * Get attributes applicable to a category: the category's own attributes merged
     * with every ancestor's (a sub-category inherits its parents' attributes and adds
     * its own), plus all attributes flagged is_global which apply to every category.
     *
     * Results are deduplicated by attribute id, with the most specific category's
     * pivot data winning over an ancestor's.
     *
     * @param int $categoryId
     * @param bool|null $canBeVariation Restrict to attributes that may (or may not)
     *                                  be promoted to a variation axis; null returns both.
     * @return Collection
     */
    public function getAttributesForCategory(int $categoryId, ?bool $canBeVariation = null): Collection
    {
        $category = Category::find($categoryId);

        $attributes = collect();

        // Walk from the category up through its ancestors, keeping the first (most
        // specific) occurrence of each attribute.
        while ($category) {
            $query = $category->attributes()->with('values');
            if ($canBeVariation !== null) {
                $query->where('attributes.can_be_variation', $canBeVariation);
            }

            foreach ($query->get() as $attribute) {
                if (!$attributes->has($attribute->id)) {
                    $attributes->put($attribute->id, $attribute);
                }
            }
            $category = $category->parent;
        }

        // Global attributes apply everywhere, including to an unknown/missing category.
        $globalQuery = Attribute::where('is_global', true)->with('values');
        if ($canBeVariation !== null) {
            $globalQuery->where('can_be_variation', $canBeVariation);
        }

        foreach ($globalQuery->get() as $attribute) {
            if (!$attributes->has($attribute->id)) {
                $attributes->put($attribute->id, $attribute);
            }
        }

        return $attributes->values();
    }
}
