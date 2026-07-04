<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Business $business): JsonResponse
    {
        $this->authorizeMember($business);
        return response()->json($business->products()->get());
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $this->authorizeMember($business);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'stock_kg' => 'required|numeric|min:0',
            'buy_price' => 'required|integer|min:0',
            'sell_price' => 'required|integer|min:0',
        ]);

        $product = $business->products()->create($data);
        return response()->json($product, 201);
    }

    public function update(Request $request, Business $business, Product $product): JsonResponse
    {
        $this->authorizeMember($business);
        $this->authorizeProduct($business, $product);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'stock_kg' => 'sometimes|numeric|min:0',
            'buy_price' => 'sometimes|integer|min:0',
            'sell_price' => 'sometimes|integer|min:0',
        ]);

        $product->update($data);
        return response()->json($product);
    }

    public function destroy(Business $business, Product $product): JsonResponse
    {
        $this->authorizeOwner($business); // hapus = owner saja
        $this->authorizeProduct($business, $product);

        $product->delete();
        return response()->json(['message' => 'Produk dihapus.']);
    }

    private function authorizeProduct(Business $business, Product $product): void
    {
        abort_if($product->business_id !== $business->id, 403, 'Akses ditolak.');
    }
}
