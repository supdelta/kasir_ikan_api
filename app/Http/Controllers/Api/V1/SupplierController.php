<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Business $business): JsonResponse
    {
        $this->authorizeMember($business);
        $suppliers = $business->suppliers()->orderBy('name')->get();
        return response()->json($suppliers);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $this->authorizeMember($business);
        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string|max:200',
        ]);

        // Jika sudah ada supplier dengan nama sama, kembalikan yang sudah ada
        $existing = $business->suppliers()->where('name', $data['name'])->first();
        if ($existing) {
            return response()->json($existing, 200);
        }

        $supplier = $business->suppliers()->create($data);
        return response()->json($supplier, 201);
    }

    public function update(Request $request, Business $business, Supplier $supplier): JsonResponse
    {
        $this->authorizeMember($business);
        abort_if($supplier->business_id !== $business->id, 403);

        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string|max:200',
        ]);

        $supplier->update($data);
        return response()->json($supplier);
    }

    public function destroy(Business $business, Supplier $supplier): JsonResponse
    {
        $this->authorizeOwner($business);
        abort_if($supplier->business_id !== $business->id, 403);
        $supplier->delete();
        return response()->json(['message' => 'Supplier dihapus.']);
    }
}
