<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Payable;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request, Business $business): JsonResponse
    {
        $member = $this->authorizeMember($business);

        $query = $business->transactions()
            ->with('product')
            ->orderByDesc('created_at');

        // Staff hanya melihat transaksi yang dia input sendiri; owner lihat semua
        if (!$member->isOwner()) {
            $query->where('user_id', auth()->id());
        }

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

        // Pre-assign one transaction_number per kasir_session_id so all items
        // in the same session share the same number without racing each other.
        $sessionNumbers = [];
        foreach ($request->transactions as $item) {
            $sid = $item['kasir_session_id'] ?? null;
            if ($sid && !isset($sessionNumbers[$sid])) {
                $typePrefix = match($item['type']) {
                    'jual'       => 'PJ',
                    'beli'       => 'PB',
                    'kas_masuk'  => 'KM',
                    'kas_keluar' => 'KK',
                    default      => 'TX',
                };
                $prefix = $typePrefix . now()->format('y');
                $lastTx = $business->transactions()
                    ->where('transaction_number', 'like', $prefix . '%')
                    ->max('transaction_number');
                $lastNum = $lastTx ? (int) substr($lastTx, strlen($prefix)) : 0;
                // Account for numbers already reserved in this loop for the same prefix
                $reserved = collect($sessionNumbers)->filter(fn($n) => str_starts_with($n, $prefix))->count();
                $sessionNumbers[$sid] = $prefix . str_pad($lastNum + $reserved + 1, 5, '0', STR_PAD_LEFT);
            }
        }

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

                // Inject pre-assigned number for this session
                $sid = $item['kasir_session_id'] ?? null;
                if ($sid && isset($sessionNumbers[$sid])) {
                    $item['_preset_number'] = $sessionNumbers[$sid];
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

    public function update(Request $request, Business $business, Transaction $transaction): JsonResponse
    {
        $this->authorizeOwner($business);
        abort_if($transaction->business_id !== $business->id, 403);

        $data = $request->validate([
            'quantity_kg'    => 'nullable|numeric|min:0.001',
            'unit_price'     => 'nullable|integer|min:0',
            'payment_method' => 'nullable|string|in:tunai,qris,utang,transfer',
            'note'           => 'nullable|string',
            'transaction_date' => 'nullable|date',
            'customer_id'    => 'nullable|integer',
            'supplier_id'    => 'nullable|integer',
            'customer_name'  => 'nullable|string',
            'customer_phone' => 'nullable|string',
        ]);

        DB::transaction(function () use ($transaction, $data) {
            // Sesuaikan stok jika qty berubah
            $newQty = isset($data['quantity_kg']) ? (float) $data['quantity_kg'] : null;
            if ($newQty !== null && $transaction->product_id && $transaction->quantity_kg) {
                $oldQty = (float) $transaction->quantity_kg;
                $diff = $newQty - $oldQty;
                if (abs($diff) > 0.0001) {
                    $product = Product::find($transaction->product_id);
                    if ($product) {
                        if ($transaction->type === 'jual') {
                            $product->decrement('stock_kg', $diff);
                        } elseif ($transaction->type === 'beli') {
                            $product->increment('stock_kg', $diff);
                        }
                    }
                }
            }

            // Hitung ulang total
            $qty   = $data['quantity_kg'] ?? $transaction->quantity_kg;
            $price = $data['unit_price']  ?? $transaction->unit_price;
            $total = $transaction->total;
            if ($qty && $price) {
                $total = (int) round((float) $qty * (int) $price);
            }

            $transaction->update(array_merge($data, ['total' => $total]));
        });

        return response()->json($transaction->fresh(['product']));
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

            // Ambil buy_price produk sekarang untuk snapshot HPP
            $buyPriceSnapshot = null;
            if (($data['type'] === 'jual') && !empty($data['product_id'])) {
                $p = Product::find($data['product_id']);
                if ($p) {
                    $buyPriceSnapshot = $p->buy_price;
                }
            }

            // Generate nomor transaksi: {PREFIX}{YY}{NNNNN}
            if (!empty($data['_preset_number'])) {
                $transactionNumber = $data['_preset_number'];
            } else {
                $typePrefix = match($data['type']) {
                    'jual'       => 'PJ',
                    'beli'       => 'PB',
                    'kas_masuk'  => 'KM',
                    'kas_keluar' => 'KK',
                    default      => 'TX',
                };
                $year = now()->format('y');
                $prefix = $typePrefix . $year;
                // Pakai MAX number bukan COUNT agar nomor yang di-share tidak bikin gap
                $lastTx = $business->transactions()
                    ->where('transaction_number', 'like', $prefix . '%')
                    ->lockForUpdate()
                    ->max('transaction_number');
                $lastNum = $lastTx ? (int) substr($lastTx, strlen($prefix)) : 0;
                $transactionNumber = $prefix . str_pad($lastNum + 1, 5, '0', STR_PAD_LEFT);
            }

            $tx = $business->transactions()->create([
                'user_id' => auth()->id(),
                'type' => $data['type'],
                'product_id' => $data['product_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'quantity_kg' => $data['quantity_kg'] ?? null,
                'unit_price' => $data['unit_price'] ?? null,
                'buy_price_snapshot' => $buyPriceSnapshot,
                'total' => $total,
                'payment_method' => $data['payment_method'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'note' => $data['note'] ?? null,
                'local_uuid' => $data['local_uuid'] ?? \Str::uuid(),
                'synced_at' => now(),
                'transaction_number' => $transactionNumber,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'kasir_session_id' => $data['kasir_session_id'] ?? null,
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

            // Buat piutang otomatis jika jual-utang
            if ($tx->type === 'jual' && $tx->payment_method === 'utang') {
                Receivable::create([
                    'business_id' => $business->id,
                    'transaction_id' => $tx->id,
                    'customer_name' => $tx->customer_name ?? 'Pelanggan',
                    'customer_phone' => $tx->customer_phone ?? null,
                    'total' => $tx->total,
                    'remaining' => $tx->total,
                ]);
            }

            // Buat hutang otomatis jika beli-utang
            if ($tx->type === 'beli' && $tx->payment_method === 'utang') {
                $supplierName = 'Supplier';
                if ($tx->supplier_id) {
                    $sup = Supplier::find($tx->supplier_id);
                    $supplierName = $sup?->name ?? 'Supplier';
                } elseif (!empty($data['supplier_name'])) {
                    $supplierName = $data['supplier_name'];
                }
                Payable::create([
                    'business_id' => $business->id,
                    'transaction_id' => $tx->id,
                    'supplier_id' => $tx->supplier_id,
                    'supplier_name' => $supplierName,
                    'total' => $tx->total,
                    'remaining' => $tx->total,
                    'note' => $tx->note,
                ]);
            }

            return $tx;
        });
    }
}
