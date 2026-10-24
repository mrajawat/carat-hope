<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Helpers\ImageHelper;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\CustomOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class CustomOrderController extends Controller
{
    /** Max size of a single reference image, in kilobytes. */
    private const MAX_IMAGE_KB = 5120;

    /**
     * All dropdown values for the custom order form in one call.
     *  - jewellery_types: active categories
     *  - metal_types:     values of the "metal-type" attribute, each with its
     *                     variants (e.g. Gold -> 14k, 18k) under "variants"
     *  - gemstones:       values of the "gemstone" attribute
     */
    public function options()
    {
        $metalTypes = $this->attributeValues(CustomOrder::METAL_TYPE_ATTRIBUTE)
            ->with('variants')
            ->get(['id', 'value'])
            ->map(fn ($metal) => [
                'id' => $metal->id,
                'name' => $metal->value,
                'variants' => $this->present($metal->variants),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'jewellery_types' => Category::where('status', 'active')
                    ->orderBy('name')
                    ->get(['id', 'parent_id', 'name', 'image']),
                'metal_types' => $metalTypes,
                'gemstones' => $this->present(
                    $this->attributeValues(CustomOrder::GEMSTONE_ATTRIBUTE)->get(['id', 'value'])
                ),
            ]
        ]);
    }

    /**
     * Variants (e.g. 14k / 18k / 22k) of one metal type.
     */
    public function metalVariants($metalTypeId)
    {
        $metal = $this->attributeValues(CustomOrder::METAL_TYPE_ATTRIBUTE)->findOrFail($metalTypeId);

        return response()->json([
            'success' => true,
            'data' => $this->present($metal->variants)
        ]);
    }

    /**
     * Submit a personalised jewellery request. Works for guests; when a
     * customer token is sent the request is linked to that account.
     *
     * images[] accepts up to 5 items, each either an uploaded file
     * (multipart/form-data) or a base64 data URI.
     */
    public function store(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:30',
            'country' => 'required|string|max:100',
            'type_of_jewellery_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('status', 'active')],
            'metal_type_id' => [
                'nullable',
                'required_with:metal_sub_type_id',
                'integer',
                $this->attributeValueRule(CustomOrder::METAL_TYPE_ATTRIBUTE),
            ],
            'metal_sub_type_id' => [
                'nullable',
                'integer',
                Rule::exists('attribute_value_variants', 'variant_value_id')
                    ->where('parent_value_id', (int) $request->input('metal_type_id')),
            ],
            'primary_gemstone_id' => ['nullable', 'integer', $this->attributeValueRule(CustomOrder::GEMSTONE_ATTRIBUTE)],
            'occasion_name' => 'nullable|string|max:100',
            'description' => 'required|string|max:5000',
            'target_date' => 'nullable|date|after:today',
            'engraving_text' => 'nullable|string|max:100',
            'images' => 'nullable|array|max:' . CustomOrder::MAX_IMAGES,
            'images.*' => 'required',
            'image_description' => 'nullable|string|max:2000',
        ], [
            'metal_sub_type_id.exists' => 'The selected variant does not belong to the selected metal type.',
            'images.max' => 'You can upload up to ' . CustomOrder::MAX_IMAGES . ' images.',
        ]);

        $uploads = $this->collectImages($request);
        foreach ($uploads as $index => $image) {
            $error = $this->validateImage($image);
            if ($error) {
                return response()->json([
                    'success' => false,
                    'message' => "Image " . ($index + 1) . ": {$error}",
                    'errors' => ["images.{$index}" => [$error]]
                ], 422);
            }
        }

        $images = [];
        try {
            foreach ($uploads as $image) {
                $images[] = ImageHelper::uploadBase64($image, 'custom-orders');
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }

        $user = auth('sanctum')->user();

        $customOrder = CustomOrder::create([
            'user_id' => $user instanceof User ? $user->id : null,
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'country' => $request->country,
            'type_of_jewellery_id' => $request->type_of_jewellery_id,
            'metal_type_id' => $request->metal_type_id,
            'metal_sub_type_id' => $request->metal_sub_type_id,
            'primary_gemstone_id' => $request->primary_gemstone_id,
            'occasion_name' => $request->occasion_name,
            'description' => $request->description,
            'target_date' => $request->target_date,
            'engraving_text' => $request->engraving_text,
            'images' => $images,
            'image_description' => $request->image_description,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your custom jewellery request has been submitted. Our team will get in touch with you shortly.',
            'data' => $customOrder->load(CustomOrder::optionRelations())->makeHidden(['admin_notes', 'user_id'])
        ], 201);
    }

    /**
     * Custom requests of the logged-in customer (linked account or same email).
     */
    public function index(Request $request)
    {
        $customOrders = $this->customerQuery($request)
            ->with(CustomOrder::optionRelations())
            ->orderBy('created_at', 'desc')
            ->get()
            ->makeHidden(['admin_notes', 'user_id']);

        return response()->json([
            'success' => true,
            'data' => $customOrders
        ]);
    }

    public function show(Request $request, $id)
    {
        $customOrder = $this->customerQuery($request)
            ->with(CustomOrder::optionRelations())
            ->findOrFail($id)
            ->makeHidden(['admin_notes', 'user_id']);

        return response()->json([
            'success' => true,
            'data' => $customOrder
        ]);
    }

    private function customerQuery(Request $request)
    {
        $user = $request->user();

        return CustomOrder::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('email', $user->email);
        });
    }

    /**
     * Values of one attribute (by slug), in display order.
     */
    private function attributeValues(string $slug)
    {
        return AttributeValue::whereHas('attribute', fn ($q) => $q->where('slug', $slug))
            ->orderBy('sort_order')
            ->orderBy('value');
    }

    private function attributeValueRule(string $slug)
    {
        return Rule::exists('attribute_values', 'id')->where(
            fn ($q) => $q->whereIn('attribute_id', Attribute::where('slug', $slug)->select('id'))
        );
    }

    /**
     * Uploaded files and base64 strings may both be sent under images[].
     */
    private function collectImages(Request $request): array
    {
        $images = (array) $request->input('images', []) + (array) $request->file('images', []);
        ksort($images);

        return $images;
    }

    /**
     * Returns an error message, or null when the image is acceptable. Plain
     * URLs are rejected so customers cannot point us at arbitrary hosts.
     */
    private function validateImage($image): ?string
    {
        if ($image instanceof UploadedFile) {
            if (!$image->isValid()) {
                return 'Upload failed.';
            }
            if ($image->getSize() > self::MAX_IMAGE_KB * 1024) {
                return 'Image must not be larger than ' . (self::MAX_IMAGE_KB / 1024) . ' MB.';
            }
            if (!in_array(strtolower($image->getClientOriginalExtension()), ImageHelper::ALLOWED_TYPES)) {
                return 'Allowed types: ' . implode(', ', ImageHelper::ALLOWED_TYPES) . '.';
            }
            return null;
        }

        if (!is_string($image) || !preg_match('/^data:image\/(\w+);base64,/', $image)) {
            return 'Must be an image file or a base64 data URI.';
        }

        $bytes = (int) (strlen($image) - strpos($image, ',') - 1) * 3 / 4;
        if ($bytes > self::MAX_IMAGE_KB * 1024) {
            return 'Image must not be larger than ' . (self::MAX_IMAGE_KB / 1024) . ' MB.';
        }

        return null;
    }

    private function present($options)
    {
        return collect($options)->map(fn ($o) => ['id' => $o->id, 'name' => $o->value])->values();
    }
}
