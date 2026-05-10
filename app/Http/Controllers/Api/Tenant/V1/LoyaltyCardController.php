<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\LoyaltyCardRequest;
use App\Http\Resources\LoyaltyCardResource;
use App\Models\LoyaltyCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoyaltyCardController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $programId = $request->input('loyalty_program_id');
        $partnerId = $request->input('partner_id');
        $status = $request->input('status');

        $query = LoyaltyCard::query()
            ->with(['program', 'partner'])
            ->orderBy('id', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('partner', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($programId) {
            $query->where('loyalty_program_id', $programId);
        }

        if ($partnerId) {
            $query->where('partner_id', $partnerId);
        }

        if ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'all') {
            // Include all
        } else {
            $query->where('is_active', true);
        }

        if ($perPage === '-1' || $perPage === 'total') {
            return $this->success(LoyaltyCardResource::collection($query->get()));
        }

        $cards = $query->paginate((int) $perPage);

        return $this->success(
            LoyaltyCardResource::collection($cards)->response()->getData(true)
        );
    }

    public function store(LoyaltyCardRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = LoyaltyCard::generateCode();

        $card = LoyaltyCard::create($data);
        $card->load(['program', 'partner']);

        return $this->created(new LoyaltyCardResource($card));
    }

    public function show(LoyaltyCard $card): JsonResponse
    {
        $card->load(['program', 'partner', 'transactions']);

        return $this->success(new LoyaltyCardResource($card));
    }

    public function destroy(LoyaltyCard $card): JsonResponse
    {
        $card->delete();

        return $this->noContent();
    }

    public function toggleStatus(LoyaltyCard $card): JsonResponse
    {
        $card->update(['is_active' => ! $card->is_active]);
        $card->load(['program', 'partner']);

        return $this->success(new LoyaltyCardResource($card));
    }

    public function findByCode(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $card = LoyaltyCard::with(['program', 'partner'])
            ->byCode($request->input('code'))
            ->first();

        if (! $card) {
            return $this->notFound('Card not found.');
        }

        return $this->success(new LoyaltyCardResource($card));
    }
}
