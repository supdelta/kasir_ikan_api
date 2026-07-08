<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Business $business): JsonResponse
    {
        $this->authorizeMember($business);
        $customers = $business->customers()->orderBy('name')->get();
        return response()->json($customers);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $this->authorizeMember($business);
        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string|max:200',
        ]);

        // Jika sudah ada customer dengan nama sama, kembalikan yang sudah ada
        $existing = $business->customers()->where('name', $data['name'])->first();
        if ($existing) {
            return response()->json($existing, 200);
        }

        $customer = $business->customers()->create($data);
        return response()->json($customer, 201);
    }

    public function update(Request $request, Business $business, Customer $customer): JsonResponse
    {
        $this->authorizeMember($business);
        abort_if($customer->business_id !== $business->id, 403);

        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|string|max:200',
        ]);

        $customer->update($data);
        return response()->json($customer);
    }

    public function destroy(Business $business, Customer $customer): JsonResponse
    {
        $this->authorizeOwner($business);
        abort_if($customer->business_id !== $business->id, 403);
        $customer->delete();
        return response()->json(['message' => 'Customer dihapus.']);
    }
}
