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
            'name'      => 'required|string|max:255',
            'category'  => 'nullable|string|max:100',
            'stock_kg'  => 'nullable|numeric|min:0',
            'buy_price' => 'nullable|integer|min:0',
            'sell_price'=> 'nullable|integer|min:0',
        ]);
        $data['stock_kg']   = $data['stock_kg']   ?? 0;
        $data['buy_price']  = $data['buy_price']  ?? 0;
        $data['sell_price'] = $data['sell_price'] ?? 0;

        $product = $business->products()->create($data);
        return response()->json($product, 201);
    }

    public function update(Request $request, Business $business, Product $product): JsonResponse
    {
        $this->authorizeMember($business);
        $this->authorizeProduct($business, $product);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'nullable|string|max:100',
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

    public function uploadPhoto(Request $request, Business $business, Product $product): JsonResponse
    {
        $this->authorizeMember($business);
        $this->authorizeProduct($business, $product);

        $request->validate(['photo' => 'required|image|max:4096']);

        if ($product->photo) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($product->photo);
        }

        $path = $request->file('photo')->store('products', 'public');
        $product->update(['photo' => $path]);

        return response()->json(['photo_url' => asset('storage/' . $path)]);
    }

    public function import(Request $request, Business $business): JsonResponse
    {
        $this->authorizeMember($business);

        $request->validate([
            'products' => 'required|array|min:1|max:500',
            'products.*.name' => 'required|string|max:255',
            'products.*.stock_kg' => 'required|numeric|min:0',
            'products.*.buy_price' => 'nullable|integer|min:0',
            'products.*.sell_price' => 'nullable|integer|min:0',
        ]);

        $created = 0;
        $updated = 0;

        foreach ($request->products as $row) {
            $existing = $business->products()->where('name', $row['name'])->first();
            if ($existing) {
                $existing->update([
                    'stock_kg' => $row['stock_kg'],
                    'buy_price' => $row['buy_price'] ?? $existing->buy_price,
                    'sell_price' => $row['sell_price'] ?? $existing->sell_price,
                ]);
                $updated++;
            } else {
                $business->products()->create([
                    'name' => $row['name'],
                    'stock_kg' => $row['stock_kg'],
                    'buy_price' => $row['buy_price'] ?? 0,
                    'sell_price' => $row['sell_price'] ?? 0,
                ]);
                $created++;
            }
        }

        return response()->json([
            'message' => "Import selesai: $created produk baru, $updated diperbarui.",
            'created' => $created,
            'updated' => $updated,
        ]);
    }

    private function authorizeProduct(Business $business, Product $product): void
    {
        abort_if($product->business_id !== $business->id, 403, 'Akses ditolak.');
    }
}
