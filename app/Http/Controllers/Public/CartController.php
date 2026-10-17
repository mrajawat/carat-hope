<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\GstService;
use App\Services\RegionDetectionService;
use App\Services\VariantPricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function show(Request $request)
    {
        return $this->cartResponse($request);
    }

    public function addItem(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'quantity' => 'required|integer|min:1|max:100',
            'custom_options' => 'nullable|array',
            'custom_options.*' => 'nullable|string|max:1000',
        ]);

        $product = Product::with('customOptions')->findOrFail($request->product_id);
        $variant = $this->resolveVariant($product, $request->product_variant_id);
        $customOptions = $this->validateCustomOptions($product, $request->input('custom_options', []));

        $cart = Cart::firstOrCreate(['user_id' => $request->user()->id]);

        // The same product, variant and personalisation is one line: adding it again
        // bumps the quantity instead of creating a duplicate row.
        $item = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->get()
            ->first(fn (CartItem $existing) => ($existing->custom_options ?? []) == ($customOptions ?? []));

        $quantity = ($item?->quantity ?? 0) + $request->quantity;
        $this->ensureStock($product, $variant, $quantity);

        if ($item) {
            $item->update(['quantity' => $quantity]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $quantity,
                'custom_options' => $customOptions,
            ]);
        }

        return $this->cartResponse($request, 'Item added to cart.', 201);
    }

    public function updateItem(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $item = $this->findOwnItem($request, $id);
        $this->ensureStock($item->product, $item->variant, $request->quantity);

        $item->update(['quantity' => $request->quantity]);

        return $this->cartResponse($request, 'Cart updated.');
    }

    public function removeItem(Request $request, $id)
    {
        $this->findOwnItem($request, $id)->delete();

        return $this->cartResponse($request, 'Item removed from cart.');
    }

    public function clear(Request $request)
    {
        Cart::where('user_id', $request->user()->id)->first()?->items()->delete();

        return $this->cartResponse($request, 'Cart cleared.');
    }

    protected function findOwnItem(Request $request, $id): CartItem
    {
        $item = CartItem::with(['product', 'variant'])
            ->where('id', $id)
            ->whereHas('cart', fn ($q) => $q->where('user_id', $request->user()->id))
            ->first();

        if (!$item) {
            abort(response()->json([
                'success' => false,
                'message' => 'Cart item not found.'
            ], 404));
        }

        return $item;
    }

    protected function resolveVariant(Product $product, $variantId): ?ProductVariant
    {
        if (!$product->has_variants) {
            if ($variantId) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'This product has no variants.',
                ]);
            }
            return null;
        }

        if (!$variantId) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Please select a variant for this product.',
            ]);
        }

        $variant = $product->variants()->where('id', $variantId)->where('is_active', true)->first();

        if (!$variant) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'The selected variant is not available for this product.',
            ]);
        }

        return $variant;
    }

    /**
     * Check the buyer's answers against the product's custom options and return
     * them keyed by option id, or null when none were given.
     */
    protected function validateCustomOptions(Product $product, array $input): ?array
    {
        $values = [];
        $errors = [];

        foreach ($product->customOptions as $option) {
            $value = trim((string) ($input[$option->id] ?? ''));
            $key = "custom_options.{$option->id}";

            if ($value === '') {
                if ($option->is_required) {
                    $errors[$key] = "{$option->label} is required.";
                }
                continue;
            }

            if ($option->type === 'dropdown' && !in_array($value, $option->choices ?? [], true)) {
                $errors[$key] = "Please choose a valid {$option->label}.";
            } elseif ($option->type === 'text' && $option->max_length && mb_strlen($value) > $option->max_length) {
                $errors[$key] = "{$option->label} may not be longer than {$option->max_length} characters.";
            }

            $values[(string) $option->id] = $value;
        }

        $unknown = array_diff(array_keys($input), $product->customOptions->pluck('id')->map(fn ($id) => (string) $id)->all());
        if (!empty($unknown)) {
            $errors['custom_options'] = 'Unknown custom option for this product.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        ksort($values);

        return empty($values) ? null : $values;
    }

    protected function ensureStock(Product $product, ?ProductVariant $variant, int $quantity): void
    {
        if ($product->status !== 'active') {
            throw ValidationException::withMessages([
                'product_id' => "Product {$product->name} is not available for purchase.",
            ]);
        }

        $available = $this->availableStock($product, $variant);

        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Insufficient stock for product {$product->name}. Only {$available} left.",
            ]);
        }
    }

    protected function availableStock(Product $product, ?ProductVariant $variant): int
    {
        // Variants track their own stock only when quantities vary; otherwise the
        // product-level stock (stock_qty resolves total_stock for variant products) applies.
        if ($variant && $product->quantities_vary) {
            return (int) $variant->stock_quantity;
        }

        return (int) $product->stock_qty;
    }

    protected function cartResponse(Request $request, ?string $message = null, int $status = 200)
    {
        $region = app(RegionDetectionService::class)->detect($request);
        $pricing = app(VariantPricingService::class);

        $cart = Cart::where('user_id', $request->user()->id)->first();

        $items = $cart
            ? $cart->items()
                ->with(['product.product_images', 'product.customOptions', 'product.gstRates', 'variant.attributeValues.attribute'])
                ->orderBy('id')
                ->get()
            : collect();

        $gstService = app(GstService::class);
        $subtotal = 0.00;
        $gstTotal = 0.00;
        $count = 0;

        $data = $items->map(function (CartItem $item) use ($region, $pricing, $gstService, &$subtotal, &$gstTotal, &$count) {
            $product = $item->product;
            $variant = $item->variant;

            if ($variant) {
                $variant->setRelation('product', $product);
                $priceData = $pricing->getPriceForRegion($variant, $region->id);
            } else {
                $priceData = $pricing->getProductPriceForRegion($product, $region->id);
            }

            $lineTotal = round($priceData['price'] * $item->quantity, 2);
            $available = $this->availableStock($product, $variant);
            $isAvailable = $product->status === 'active' && (!$variant || $variant->is_active);

            $gstPercentage = $gstService->rateForRegionId($product, $region->id);
            $gstAmount = $gstService->amountFor($lineTotal, $gstPercentage);

            $subtotal += $lineTotal;
            $gstTotal += $gstAmount;
            $count += $item->quantity;

            $variantImage = $variant?->variant_images[0] ?? null;
            $image = (is_string($variantImage) ? $variantImage : null)
                ??($product->product_images->firstWhere('is_primary', true) ?? $product->product_images->first())?->image_path;

            return [
                'id' => $item->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_image' => $image,
                'product_variant_id' => $variant?->id,
                'variant_sku' => $variant?->sku,
                'variant_attributes' => $variant
                    ? $variant->attributeValues->map(fn ($value) => [
                        'attribute' => $value->attribute?->name,
                        'value' => $value->value,
                    ])->values()
                    : [],
                'custom_options' => collect($item->custom_options ?? [])->map(fn ($value, $optionId) => [
                    'option_id' => (int) $optionId,
                    'label' => $product->customOptions->firstWhere('id', (int) $optionId)?->label,
                    'value' => $value,
                ])->values(),
                'quantity' => $item->quantity,
                'unit_price' => (float) $priceData['price'],
                'compare_at_price' => $priceData['compare_at_price'],
                'line_total' => $lineTotal,
                'gst_percentage' => $gstPercentage,
                'gst_amount' => $gstAmount,
                'line_total_with_gst' => round($lineTotal + $gstAmount, 2),
                'available_stock' => $available,
                'is_available' => $isAvailable && $available >= $item->quantity,
            ];
        });

        $payload = [
            'success' => true,
            'data' => [
                'items' => $data,
                'items_count' => $count,
                'subtotal' => round($subtotal, 2),
                'gst_amount' => round($gstTotal, 2),
                'total' => round($subtotal + $gstTotal, 2),
                'currency_code' => $region->currency_code,
                'currency_symbol' => $region->currency_symbol,
            ],
        ];

        if ($message) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }
}
