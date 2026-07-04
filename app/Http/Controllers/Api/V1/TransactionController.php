<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request, Business $business): JsonResponse
    {
        $this->authorizeMember($business);

        $query = $business->transactions()
            ->with('product')
            ->orderByDesc('created_at');

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        return response()->json($query->paginate(50));
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $this->authorizeMember($business);
        return response()->json($this->createTransaction($request->all(), $business), 201);
    }

    public function bulkSync(Request $request, Business $business): JsonResponse
    {
        $this->authorizeMember($business);

        $request->validate([
            'transactions' => 'required|array',
            'transactions.*.local_uuid' => 'required|uuid',
            'transactions.*.type' => 'required|in:jual,beli,kas_masuk,kas_keluar',
            'transactions.*.total' => 'required|integer|min:0',
        ]);

        $results = [];

        foreach ($request->transactions as $item) {
            try {
                $existing = Transaction::where('local_uuid', $item['local_uuid'])->first();

                if ($existing) {
                    $results[] = [
                        'local_uuid' => $item['local_uuid'],
                        'status' => 'duplicate',
                        'server_id' => $existing->id,
                    ];
                    continue;
                }

                $tx = $this->createTransaction($item, $business);
                $results[] = [
                    'local_uuid' => $item['local_uuid'],
                    'status' => 'created',
                    'server_id' => $tx->id,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'local_uuid' => $item['local_uuid'] ?? null,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function destroy(Business $business, Transaction $transaction): JsonResponse
    {
        $this->authorizeOwner($business); // hapus = owner saja
        abort_if($transaction->business_id !== $business->id, 403);

        // Tidak boleh hapus transaksi yang piutangnya sudah ada cicilan
        if ($transaction->receivable && $transaction->receivable->payments()->exists()) {
            return response()->json([
                'message' => 'Tidak bisa menghapus transaksi yang piutangnya sudah dicicil.',
            ], 422);
        }

        DB::transaction(function () use ($transaction) {
            $transaction->receivable?->delete();
            $transaction->delete();
        });

        return response()->json(['message' => 'Transaksi dihapus.']);
    }

    private function createTransaction(array $data, Business $business): Transaction
    {
        return DB::transaction(function () use ($data, $business) {
            // Hitung total di server (§4.1 spec)
            $total = $data['total'];
            if (!empty($data['product_id']) && !empty($data['quantity_kg']) && !empty($data['unit_price'])) {
                $total = (int) round((float) $data['quantity_kg'] * (int) $data['unit_price']);
            }

            $tx = $business->transactions()->create([
                'user_id' => auth()->id(),
                'type' => $data['type'],
                'product_id' => $data['product_id'] ?? null,
                'quantity_kg' => $data['quantity_kg'] ?? null,
                'unit_price' => $data['unit_price'] ?? null,
                'total' => $total,
                'payment_method' => $data['payment_method'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'note' => $data['note'] ?? null,
                'local_uuid' => $data['local_uuid'] ?? \Str::uuid(),
                'synced_at' => now(),
            ]);

            // Update stok produk
            if ($tx->product_id && $tx->quantity_kg) {
                $product = Product::find($tx->product_id);
                if ($product) {
                    if ($tx->type === 'jual') {
                        $product->decrement('stock_kg', $tx->quantity_kg);
                    } elseif ($tx->type === 'beli') {
                        $product->increment('stock_kg', $tx->quantity_kg);
                    }
                }
            }

            // Buat piutang otomatis jika jual-utang (§4.2 spec)
            if ($tx->type === 'jual' && $tx->payment_method === 'utang') {
                Receivable::create([
                    'business_id' => $business->id,
                    'transaction_id' => $tx->id,
                    'customer_name' => $tx->customer_name ?? 'Pelanggan',
                    'total' => $tx->total,
                    'remaining' => $tx->total,
                ]);
            }

            return $tx;
        });
    }
}
