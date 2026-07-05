<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Receivable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceivableController extends Controller
{
    public function index(Request $request, Business $business): JsonResponse
    {
        $this->authorizeMember($business);

        $query = $business->receivables()->with('payments')->orderByDesc('created_at');

        if ($request->status === 'unpaid') {
            $query->where('is_paid', false);
        } elseif ($request->status === 'paid') {
            $query->where('is_paid', true);
        }

        return response()->json($query->get());
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $this->authorizeMember($business);

        $data = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'total' => 'required|integer|min:1',
            'note' => 'nullable|string',
        ]);

        $receivable = $business->receivables()->create([
            ...$data,
            'remaining' => $data['total'],
        ]);

        return response()->json($receivable, 201);
    }

    public function pay(Request $request, Business $business, Receivable $receivable): JsonResponse
    {
        $this->authorizeMember($business);
        abort_if($receivable->business_id !== $business->id, 403);

        $data = $request->validate([
            'amount' => 'required|integer|min:1|max:' . $receivable->remaining,
        ]);

        DB::transaction(function () use ($receivable, $data) {
            $receivable->payments()->create([
                'amount' => $data['amount'],
                'paid_at' => now(),
            ]);

            $newRemaining = $receivable->remaining - $data['amount'];
            $receivable->update([
                'remaining' => $newRemaining,
                'is_paid' => $newRemaining <= 0,
            ]);
        });

        return response()->json($receivable->fresh('payments'));
    }

    public function destroy(Business $business, Receivable $receivable): JsonResponse
    {
        $this->authorizeOwner($business); // hapus = owner saja
        abort_if($receivable->business_id !== $business->id, 403);

        if ($receivable->payments()->exists()) {
            return response()->json(['message' => 'Piutang yang sudah dicicil tidak bisa dihapus.'], 422);
        }

        $receivable->delete();
        return response()->json(['message' => 'Piutang dihapus.']);
    }
}
