<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProcessingProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcessingProfileController extends Controller
{
    /**
     * List processing profiles, with how many products use each.
     */
    public function index(Request $request)
    {
        $query = ProcessingProfile::withCount('products');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'status' => true,
            'message' => 'Processing profiles retrieved successfully',
            'data' => $query->orderByDesc('is_default')->orderBy('min_days')->get()
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'status' => true,
            'message' => 'Processing profile retrieved successfully',
            'data' => ProcessingProfile::withCount('products')->findOrFail($id)
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $profile = DB::transaction(function () use ($validated) {
            $profile = ProcessingProfile::create($validated);
            $this->syncDefault($profile);

            return $profile;
        });

        return response()->json([
            'status' => true,
            'message' => 'Processing profile created successfully',
            'data' => $profile
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $profile = ProcessingProfile::findOrFail($id);
        $validated = $request->validate($this->rules());

        DB::transaction(function () use ($profile, $validated) {
            $profile->update($validated);
            $this->syncDefault($profile);
        });

        return response()->json([
            'status' => true,
            'message' => 'Processing profile updated successfully',
            'data' => $profile->fresh()
        ]);
    }

    public function destroy($id)
    {
        $profile = ProcessingProfile::withCount('products')->findOrFail($id);

        if ($profile->products_count > 0) {
            return response()->json([
                'status' => false,
                'message' => "This profile is used by {$profile->products_count} product(s). Reassign them before deleting.",
                'data' => null
            ], 422);
        }

        $profile->delete();

        return response()->json([
            'status' => true,
            'message' => 'Processing profile deleted successfully',
            'data' => null
        ]);
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'min_days' => 'required|integer|min:0',
            'max_days' => 'required|integer|min:0|gte:min_days',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Only one profile may be the default at a time.
     */
    private function syncDefault(ProcessingProfile $profile): void
    {
        if ($profile->is_default) {
            ProcessingProfile::where('id', '!=', $profile->id)->update(['is_default' => false]);
        }
    }
}
