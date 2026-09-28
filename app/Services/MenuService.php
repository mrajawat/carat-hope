<?php

namespace App\Services;

use App\Models\Category;
use App\Models\MenuLink;
use App\Models\MenuSection;

class MenuService
{
    /**
     * Navbar mega menu: active top-level categories, each with its active sections
     * and their active links. Cached; cleared whenever a category, section or link changes.
     */
    public function publicMenu(): array
    {
        return cache()->remember(MenuSection::PUBLIC_MENU_CACHE_KEY, now()->addHours(24), function () {
            $parents = Category::whereNull('parent_id')
                ->where('status', 'active')
                ->with([
                    'menuSections' => fn ($q) => $q->where('status', 'active'),
                    'menuSections.links' => fn ($q) => $q->where('status', 'active'),
                    'menuSections.links.category:id,name,slug,status',
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return $parents->map(function (Category $parent) {
                return [
                    'id' => $parent->id,
                    'name' => $parent->name,
                    'slug' => $parent->slug,
                    'image' => $parent->image,
                    'banner' => $parent->menu_banner_image ? [
                        'image' => $parent->menu_banner_image,
                        'link' => $parent->menu_banner_link,
                    ] : null,
                    'sections' => $parent->menuSections
                        ->map(fn (MenuSection $section) => [
                            'id' => $section->id,
                            'title' => $section->title,
                            'links' => $section->links
                                ->filter(fn (MenuLink $link) => $this->isLinkVisible($link))
                                ->map(fn (MenuLink $link) => $this->formatLink($link))
                                ->values(),
                        ])
                        ->filter(fn ($section) => $section['links']->isNotEmpty())
                        ->values(),
                ];
            })->values()->toArray();
        });
    }

    /**
     * Shape of a link for the storefront. `type` tells the frontend how to route it:
     *  - category: product listing for category_id (+ filters)
     *  - filter:   product listing across all categories with filters only
     *  - url:      plain link (internal page or external URL)
     */
    public function formatLink(MenuLink $link): array
    {
        $type = $link->url ? 'url' : ($link->category_id ? 'category' : 'filter');

        return [
            'id' => $link->id,
            'label' => $link->label,
            'type' => $type,
            'category_id' => $link->category_id,
            'category_slug' => $link->category ? $link->category->slug : null,
            'filters' => $link->filters ?: null,
            'url' => $link->url,
        ];
    }

    /**
     * Hide links whose target category was deleted (FK nulls it) or deactivated.
     */
    private function isLinkVisible(MenuLink $link): bool
    {
        if ($link->url) {
            return true;
        }

        if ($link->category_id) {
            return $link->category && $link->category->status === 'active';
        }

        return !empty($link->filters);
    }
}
