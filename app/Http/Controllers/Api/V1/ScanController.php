<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ScanController extends Controller
{
    public function bon(Request $request): JsonResponse
    {
        if (!$request->user()->isPremium()) {
            return response()->json([
                'message' => 'Scan bon AI khusus Premium. Upgrade untuk memakai fitur ini.',
                'premium_required' => true,
            ], 403);
        }

        $request->validate(['photo' => 'required|image|max:8192']);

        $apiKey = config('services.anthropic.key');
        if (!$apiKey) {
            return response()->json(['message' => 'Fitur scan bon belum dikonfigurasi.'], 503);
        }

        $file     = $request->file('photo');
        $base64   = base64_encode(file_get_contents($file->getRealPath()));
        $mediaType = $file->getMimeType() === 'image/png' ? 'image/png' : 'image/jpeg';

        $prompt = <<<'TXT'
Kamu adalah asisten kasir pasar ikan. Analisis foto bon/struk/nota ini.
Ekstrak SEMUA transaksi yang ada — bisa lebih dari satu jika ada beberapa item/baris.

Kembalikan HANYA JSON (tanpa markdown, tanpa penjelasan):
{
  "transactions": [
    {
      "type": "jual|beli|kas_masuk|kas_keluar",
      "total": angka_integer_rupiah_atau_null,
      "product_name": "nama produk atau null",
      "quantity_kg": angka_desimal_atau_null,
      "unit_price": angka_integer_per_kg_atau_null,
      "customer_name": "nama pembeli atau supplier atau null",
      "note": "catatan singkat atau null"
    }
  ]
}

Aturan tipe:
- "jual"        = tagihan/invoice ke pembeli (kita yang jual ikan)
- "beli"        = bon pembelian stok ikan dari pemasok/nelayan
- "kas_masuk"   = penerimaan uang masuk (transfer, setoran, dll)
- "kas_keluar"  = pengeluaran operasional: SPBU/BBM, listrik, gaji, belanja alat, makan, dll

Aturan tambahan:
- SPBU/pom bensin → "kas_keluar"
- Minimarket/toko/resto/laundry → "kas_keluar"
- Pemasok ikan/nelayan → "beli"
- Nota tangan dengan beberapa baris/pelanggan → satu item per baris
- total tiap item = quantity_kg × unit_price (hitung jika keduanya ada)
- Semua angka rupiah tanpa titik/koma/Rp
- Jika tidak terbaca: {"transactions":[{"type":"kas_keluar","total":null,"product_name":null,"quantity_kg":null,"unit_price":null,"customer_name":null,"note":"Tidak dapat membaca bon"}]}
TXT;

        $resp = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model'      => config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
            'max_tokens' => 1024,
            'messages'   => [[
                'role'    => 'user',
                'content' => [
                    ['type' => 'image', 'source' => [
                        'type'       => 'base64',
                        'media_type' => $mediaType,
                        'data'       => $base64,
                    ]],
                    ['type' => 'text', 'text' => $prompt],
                ],
            ]],
        ]);

        if ($resp->failed()) {
            return response()->json(['message' => 'Gagal memproses bon. Coba lagi.'], 502);
        }

        $text    = $resp->json('content.0.text', '');
        $cleaned = trim(preg_replace('/```json?|```/', '', $text));
        $data    = json_decode($cleaned, true);

        $validTypes  = ['jual', 'beli', 'kas_masuk', 'kas_keluar'];
        $transactions = [];

        if (is_array($data) && isset($data['transactions']) && is_array($data['transactions'])) {
            foreach ($data['transactions'] as $item) {
                $type = $item['type'] ?? 'kas_keluar';
                if (!in_array($type, $validTypes)) $type = 'kas_keluar';

                $transactions[] = [
                    'type'          => $type,
                    'total'         => isset($item['total']) && is_numeric($item['total']) ? (int) $item['total'] : null,
                    'product_name'  => $item['product_name'] ?? null,
                    'quantity_kg'   => isset($item['quantity_kg']) && is_numeric($item['quantity_kg']) ? (float) $item['quantity_kg'] : null,
                    'unit_price'    => isset($item['unit_price']) && is_numeric($item['unit_price']) ? (int) $item['unit_price'] : null,
                    'customer_name' => $item['customer_name'] ?? null,
                    'note'          => $item['note'] ?? null,
                ];
            }
        }

        if (empty($transactions)) {
            $transactions = [[
                'type'          => 'kas_keluar',
                'total'         => null,
                'product_name'  => null,
                'quantity_kg'   => null,
                'unit_price'    => null,
                'customer_name' => null,
                'note'          => 'Tidak dapat membaca bon',
            ]];
        }

        return response()->json(['transactions' => $transactions]);
    }
}
