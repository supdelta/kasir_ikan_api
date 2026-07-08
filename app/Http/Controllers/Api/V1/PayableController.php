<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Payable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayableController extends Controller
{
    public function index(Request $request, Business $business): JsonResponse
    {
        $this->authorizeMember($business);

        $query = $business->payables()->with('payments')->orderByDesc('created_at');

        if ($request->status === 'unpaid') {
            $query->where('remaining', '>', 0);
        } elseif ($request->status === 'paid') {
            $query->where('remaining', '<=', 0);
        }

        return response()->json($query->get()->map(fn($p) => array_merge($p->toArray(), [
            'is_paid' => $p->remaining <= 0,
        ])));
    }

    public function pay(Request $request, Business $business, Payable $payable): JsonResponse
    {
        $this->authorizeMember($business);
        abort_if($payable->business_id !== $business->id, 403);

        $data = $request->validate([
            'amount' => 'required|integer|min:1|max:' . $payable->remaining,
            'note' => 'nullable|string',
        ]);

        DB::transaction(function () use ($payable, $data) {
            $payable->payments()->create([
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
            ]);

            $newRemaining = $payable->remaining - $data['amount'];
            $payable->update(['remaining' => $newRemaining]);
        });

        $fresh = $payable->fresh('payments');
        return response()->json(array_merge($fresh->toArray(), [
            'is_paid' => $fresh->remaining <= 0,
        ]));
    }

    public function destroy(Business $business, Payable $payable): JsonResponse
    {
        $this->authorizeOwner($business);
        abort_if($payable->business_id !== $business->id, 403);

        if ($payable->payments()->exists()) {
            return response()->json(['message' => 'Hutang yang sudah dicicil tidak bisa dihapus.'], 422);
        }

        $payable->delete();
        return response()->json(['message' => 'Hutang dihapus.']);
    }
}
