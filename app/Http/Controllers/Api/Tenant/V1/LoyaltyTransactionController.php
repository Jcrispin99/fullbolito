<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\LoyaltyTransactionResource;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoyaltyTransactionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $programId = $request->input('loyalty_program_id');
        $cardId = $request->input('loyalty_card_id');
        $partnerId = $request->input('partner_id');
        $type = $request->input('type');

        $query = LoyaltyTransaction::query()
            ->with(['partner', 'card'])
            ->orderBy('created_at', 'desc');

        if ($programId) {
            $query->where('loyalty_program_id', $programId);
        }

        if ($cardId) {
            $query->where('loyalty_card_id', $cardId);
        }

        if ($partnerId) {
            $query->where('partner_id', $partnerId);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($perPage === '-1' || $perPage === 'total') {
            return $this->success(LoyaltyTransactionResource::collection($query->get()));
        }

        $transactions = $query->paginate((int) $perPage);

        return $this->success(
            LoyaltyTransactionResource::collection($transactions)->response()->getData(true)
        );
    }

    public function adjust(Request $request): JsonResponse
    {
        $request->validate([
            'loyalty_card_id' => 'required|integer|exists:loyalty_cards,id',
            'points' => 'required|numeric|not_in:0',
            'description' => 'nullable|string|max:500',
        ]);

        $card = LoyaltyCard::findOrFail($request->input('loyalty_card_id'));
        $points = round((float) $request->input('points'), 2);
        $newBalance = round((float) $card->points + $points, 2);

        if ($newBalance < 0) {
            return $this->error('El ajuste resultaría en un saldo negativo.', 422);
        }

        $transaction = LoyaltyTransaction::create([
            'loyalty_program_id' => $card->loyalty_program_id,
            'loyalty_card_id' => $card->id,
            'partner_id' => $card->partner_id,
            'type' => 'adjust',
            'points' => $points,
            'balance' => $newBalance,
            'description' => $request->input('description') ?? 'Ajuste manual',
        ]);

        $card->update(['points' => $newBalance]);
        $transaction->load(['partner', 'card']);

        return $this->created(new LoyaltyTransactionResource($transaction));
    }
}
