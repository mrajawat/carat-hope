# Categories, Subcategories & Mega Menu

Handover notes for the category hierarchy and navbar mega menu (Brilliant Earth style:
nav items → hover panel with column headings → links, plus a featured banner).

## Decisions (agreed with the client — don't undo without asking)

1. **Parent categories come only from the seeder** (`database/seeders/CategorySeeder.php`).
   There is no API to create, delete, or re-parent a top-level category.
2. **Two levels only:** parent → subcategory. A subcategory cannot have children.
   (`SubcategoryController` and `CategoryController` both enforce this; the tree endpoint
   and the product `category_id` filter only look one level down.)
3. **Mega menu is a separate layer on top of categories** (`menu_sections`, `menu_links`),
   because menu links are not always subcategories — they can be a category + attribute
   filters, filters only, or a plain URL (guides, collections).
4. **`GET /api/admin/categories` (master list) returns only subcategories by default**, so
   parents don't appear twice in the admin panel. Parents are fetched with `?roots_only=1`.

## Data model

All categories live in one table, `categories`. `parent_id = NULL` → parent (seeded);
`parent_id = <id>` → subcategory (admin-created).

| Table | Key columns |
|---|---|
| `categories` | `parent_id`, `status`, `sort_order` (nav order), `menu_banner_image`, `menu_banner_link` |
| `menu_sections` | `category_id` (parent only), `title`, `sort_order`, `status` — column headings like "Shop by Style" |
| `menu_links` | `menu_section_id`, `label`, `category_id` (nullable, nullOnDelete), `filters` (JSON), `url`, `sort_order`, `status` |

`menu_links.filters` shape: `{"<attribute-slug>": [<attribute_value_id>, ...]}`,
e.g. `{"materials": [14], "metal-type": [5]}`.

Migrations: `database/migrations/2026_09_28_00000{1,2,3}_*` (all reversible).

## Code map

| File | Role |
|---|---|
| `database/seeders/CategorySeeder.php` | Parent categories. `firstOrCreate` by slug — never overwrites admin edits; array order = `sort_order` |
| `app/Http/Controllers/Admin/CategoryController.php` | Master CRUD; `parent_id` required on create; parents can't be deleted/re-parented |
| `app/Http/Controllers/Admin/SubcategoryController.php` | Older subcategory-specific endpoints (still valid) |
| `app/Http/Controllers/Admin/MenuController.php` | Sections, links, banner, reorder |
| `app/Services/MenuService.php` | Builds the cached public menu; hides links to inactive/deleted categories and empty sections |
| `app/Models/MenuSection.php`, `MenuLink.php` | Clear the `public_menu` cache on save/delete (Category does too) |
| `app/Models/Product.php` → `scopeFilterByAttributes()` | Product attribute filtering |
| `app/Http/Controllers/Public/PublicController.php` | `categories`, `categoryTree`, `menu`, `products` (with `filters`) |

## API reference

### Public

| Endpoint | Returns |
|---|---|
| `GET /api/public/categories` | Flat list of all active categories (parents + subs), sorted by name. Use `parent_id` to tell them apart |
| `GET /api/public/categories/tree` | Active parents with active `children`, sorted by `sort_order` |
| `GET /api/public/menu` | Navbar: parents → `sections` → `links`, plus `banner` (null if none). Cached 24h, auto-cleared |
| `GET /api/public/products?category_id=2&filters[materials][]=14` | Product listing. `category_id` includes that category's subcategories |
| `GET /api/categories/{id}/attributes` | Filter options for a category (value ids go into `filters[slug][]`) |

Menu link `type`: `category` → listing for `category_id` (+ `filters`); `filter` → listing
with `filters` only; `url` → plain link.

**Filter semantics:** values of one attribute are OR'ed; different attributes are AND'ed.
A product matches if an **active variant** has the value (`variant_attribute_values`) **or**
its `products.listing_attributes` JSON has it — stored either as
`{"<slug>": {"attribute_value_ids": [..]}}` or as the plain value string/array, under the
slug or its snake_case form (`gold-purity` / `gold_purity`). Unknown slug/values → no results.
Only select-type attributes (value ids) are filterable; boolean/number are not yet.

### Admin (Sanctum)

| Method | Endpoint | Body / notes |
|---|---|---|
| GET | `/api/admin/categories` | Default: subcategories only. `?roots_only=1` parents · `?leaf_only=1` no-children (incl. childless parents, for product form) · `?include_parents=1` all · `search`, `status` |
| POST | `/api/admin/categories` | `name`*, `parent_id`* (must be a parent), `description`, `image` (base64) |
| PUT | `/api/admin/categories/{id}` | Same. Parents: `parent_id` prohibited. Subs: `parent_id` required |
| DELETE | `/api/admin/categories/{id}` | 422 for parents (deactivate via toggle-status instead) |
| POST | `/api/admin/categories/{parentId}/subcategories` | `name`*, `description`, `image` |
| GET | `/api/admin/menu` | Full menu tree incl. inactive |
| GET/POST | `/api/admin/categories/{parentId}/menu-sections` | `title`*, `sort_order`, `status` |
| PUT/DELETE | `/api/admin/menu-sections/{id}` | Delete cascades to links |
| POST | `/api/admin/menu-sections/{id}/links` | `label`*, `category_id`, `filters`, `url`, `sort_order`, `status` |
| PUT/DELETE | `/api/admin/menu-links/{id}` | Same + `menu_section_id` to move |
| POST | `/api/admin/categories/{parentId}/menu-banner` | `image` (base64), `link`, `remove` (bool) |
| PATCH | `/api/admin/menu/reorder` | `type`: categories\|sections\|links, `ids`: [in order]. Sections/links must share one parent |

Link rules: needs at least one of `category_id` / `filters` / `url`; `url` can't be combined
with the others; `url` and banner `link` must start with `/` or `http(s)://`; every filter
value id must belong to the named attribute.

## Deploy (cPanel)

```
php artisan migrate --force
php artisan db:seed --class=CategorySeeder --force   # safe to re-run
php artisan config:cache
```
Rollback: `php artisan migrate:rollback --step=3` (drops only the menu tables/columns).

## Open items

- `CategorySeeder` still seeds Rings / Necklaces / Earrings / Bracelets. Replace with the
  client's final nav list (e.g. Engagement Rings, Wedding Rings, Diamonds, Gemstones,
  Jewelry). Non-category items like "About"/"Gifts" need a frontend hardcode or a new
  static-links table.
- `GEMSTONE` and `Royal Vintage Collection` exist on live as parents but are not in the
  seeder (they were created through the old API).
- On live, all existing categories are parents, so the default admin master list is empty
  until subcategories are added — admin UI should use `?roots_only=1` / `?include_parents=1`
  where it needs parents.
- Boolean/number attribute filters (`recycled`, `necklace-length`, …) not supported yet.
