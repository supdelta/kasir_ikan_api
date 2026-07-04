<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ScanController extends Controller
{
    /**
     * Scan bon/struk pakai AI (Claude) — key disimpan di server, tidak di app.
     */
    public function bon(Request $request): JsonResponse
    {
        $request->validate(['photo' => 'required|image|max:8192']);

        $apiKey = config('services.anthropic.key');
        if (!$apiKey) {
            return response()->json(['message' => 'Fitur scan bon belum dikonfigurasi.'], 503);
        }

        $file = $request->file('photo');
        $base64 = base64_encode(file_get_contents($file->getRealPath()));
        $mediaType = $file->getMimeType() === 'image/png' ? 'image/png' : 'image/jpeg';

        $prompt = <<<'TXT'
Kamu adalah asisten kasir pasar ikan. Analisis foto bon/struk/nota ini dan ekstrak informasi transaksi.

Kembalikan HANYA JSON (tanpa markdown, tanpa penjelasan) dalam format ini:
{
  "type": "jual" | "beli" | "kas_masuk" | "kas_keluar",
  "total": angka_integer_rupiah,
  "product_name": "nama produk jika ada, null jika tidak ada",
  "note": "catatan singkat, null jika tidak ada"
}

Aturan:
- Jika bon adalah penjualan/tagihan ke pembeli -> type = "jual"
- Jika bon adalah pembelian barang/stok -> type = "beli"
- Jika bon adalah penerimaan uang tanpa produk -> type = "kas_masuk"
- Jika bon adalah pengeluaran tanpa produk -> type = "kas_keluar"
- Total harus angka bulat dalam Rupiah (tanpa Rp, titik, koma)
- Jika tidak ada bon yang jelas, kembalikan: {"type":"jual","total":null,"product_name":null,"note":"Tidak dapat membaca bon"}
TXT;

        $resp = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(45)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
            'max_tokens' => 256,
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'image', 'source' => [
                        'type' => 'base64',
                        'media_type' => $mediaType,
                        'data' => $base64,
                    ]],
                    ['type' => 'text', 'text' => $prompt],
                ],
            ]],
        ]);

        if ($resp->failed()) {
            return response()->json(['message' => 'Gagal memproses bon. Coba lagi.'], 502);
        }

        $text = $resp->json('content.0.text', '');
        $cleaned = trim(preg_replace('/```json?|```/', '', $text));
        $data = json_decode($cleaned, true);

        if (!is_array($data)) {
            $data = ['type' => 'jual', 'total' => null, 'product_name' => null, 'note' => 'Tidak dapat membaca bon'];
        }

        return response()->json([
            'type' => $data['type'] ?? 'jual',
            'total' => isset($data['total']) ? (is_numeric($data['total']) ? (int) $data['total'] : null) : null,
            'product_name' => $data['product_name'] ?? null,
            'note' => $data['note'] ?? null,
        ]);
    }
}
