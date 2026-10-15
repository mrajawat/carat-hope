<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomOrderController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(CustomOrder::STATUSES)],
            'type_of_jewellery_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CustomOrder::with(CustomOrder::optionRelations())
            ->orderBy('created_at', 'desc');

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type_of_jewellery_id')) {
            $query->where('type_of_jewellery_id', $request->integer('type_of_jewellery_id'));
        }

        // Paginate only when per_page is requested; otherwise return the full list
        if ($request->filled('per_page')) {
            $customOrders = $query->paginate($request->integer('per_page'));

            return response()->json([
                'success' => true,
                'data' => $customOrders->items(),
                'meta' => [
                    'current_page' => $customOrders->currentPage(),
                    'last_page' => $customOrders->lastPage(),
                    'per_page' => $customOrders->perPage(),
                    'total' => $customOrders->total(),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }

    public function show($id)
    {
        $customOrder = CustomOrder::with(array_merge(CustomOrder::optionRelations(), ['user:id,name,email,phone']))
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $customOrder
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', Rule::in(CustomOrder::STATUSES)],
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $customOrder = CustomOrder::findOrFail($id);
        $customOrder->status = $request->status;
        if ($request->has('admin_notes')) {
            $customOrder->admin_notes = $request->admin_notes;
        }
        $customOrder->save();

        return response()->json([
            'success' => true,
            'message' => 'Custom order status updated successfully',
            'data' => $customOrder->load(CustomOrder::optionRelations())
        ]);
    }

    public function destroy($id)
    {
        $customOrder = CustomOrder::findOrFail($id);
        $customOrder->delete();

        return response()->json([
            'success' => true,
            'message' => 'Custom order deleted successfully'
        ]);
    }
}
