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

Aturan tipe:
- "jual"       = bon tagihan/invoice yang KITA buat untuk pelanggan kita (kita yang jual ikan)
- "beli"       = bon pembelian STOK IKAN dari pemasok/nelayan (kita yang beli ikan untuk dijual kembali)
- "kas_masuk"  = bukti penerimaan uang masuk (transfer masuk, setoran, dll)
- "kas_keluar" = semua pengeluaran operasional: bon BBM/SPBU, listrik, air, gaji, belanja alat, makan, parkir, bensin, dll — APAPUN yang bukan pembelian stok ikan

Catatan penting:
- Bon dari SPBU / pom bensin → "kas_keluar"
- Bon dari minimarket / toko / resto / laundry → "kas_keluar"
- Nota pembelian alat / perlengkapan → "kas_keluar"
- Bon dari pemasok ikan / nelayan → "beli"
- Total harus angka bulat dalam Rupiah (tanpa Rp, titik, koma)
- Jika tidak ada bon yang jelas, kembalikan: {"type":"kas_keluar","total":null,"product_name":null,"note":"Tidak dapat membaca bon"}
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
            $data = ['type' => 'kas_keluar', 'total' => null, 'product_name' => null, 'note' => 'Tidak dapat membaca bon'];
        }

        return response()->json([
            'type' => $data['type'] ?? 'kas_keluar',
            'total' => isset($data['total']) ? (is_numeric($data['total']) ? (int) $data['total'] : null) : null,
            'product_name' => $data['product_name'] ?? null,
            'note' => $data['note'] ?? null,
        ]);
    }
}
