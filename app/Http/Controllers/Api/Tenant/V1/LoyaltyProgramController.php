<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\LoyaltyProgramRequest;
use App\Http\Resources\LoyaltyProgramResource;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyReward;
use App\Models\Partner;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LoyaltyProgramController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $status = $request->input('status');
        $module = $request->input('module');
        $programType = $request->input('program_type');

        $query = LoyaltyProgram::query()
            ->with(['rules.productVariants', 'rules.productTemplates', 'rules.categories', 'rewards.rewardProduct', 'rewards.discountProducts', 'rewards.discountCategories'])
            ->withCount('cards')
            ->orderBy('id', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($module) {
            $query->forModule($module);
        }

        if ($programType) {
            $query->ofType($programType);
        }

        if ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'all') {
            // Include all
        } else {
            $query->where('is_active', true);
        }

        if ($perPage === '-1' || $perPage === 'total') {
            return $this->success(LoyaltyProgramResource::collection($query->get()));
        }

        $programs = $query->paginate((int) $perPage);

        return $this->success(
            LoyaltyProgramResource::collection($programs)->response()->getData(true)
        );
    }

    public function store(LoyaltyProgramRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $rulesData = $data['rules'] ?? [];
            $rewardsData = $data['rewards'] ?? [];
            unset($data['rules'], $data['rewards']);

            $program = LoyaltyProgram::create($data);

            $this->syncRules($program, $rulesData);
            $this->syncRewards($program, $rewardsData);

            $program->load(['rules.productVariants', 'rules.productTemplates', 'rules.categories', 'rewards.rewardProduct', 'rewards.discountProducts', 'rewards.discountCategories']);

            return $this->created(new LoyaltyProgramResource($program));
        });
    }

    public function show(LoyaltyProgram $program): JsonResponse
    {
        $program->load(['rules.productVariants', 'rules.productTemplates', 'rules.categories', 'rewards.rewardProduct', 'rewards.discountProducts', 'rewards.discountCategories']);
        $program->loadCount('cards');

        return $this->success(new LoyaltyProgramResource($program));
    }

    public function update(LoyaltyProgramRequest $request, LoyaltyProgram $program): JsonResponse
    {
        return DB::transaction(function () use ($request, $program) {
            $data = $request->validated();
            $rulesData = $data['rules'] ?? null;
            $rewardsData = $data['rewards'] ?? null;
            unset($data['rules'], $data['rewards']);

            $program->update($data);

            if ($rulesData !== null) {
                $this->syncRules($program, $rulesData);
            }

            if ($rewardsData !== null) {
                $this->syncRewards($program, $rewardsData);
            }

            $program->load(['rules.productVariants', 'rules.productTemplates', 'rules.categories', 'rewards.rewardProduct', 'rewards.discountProducts', 'rewards.discountCategories']);
            $program->loadCount('cards');

            return $this->success(new LoyaltyProgramResource($program));
        });
    }

    public function destroy(LoyaltyProgram $program): JsonResponse
    {
        $program->delete();

        return $this->noContent();
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        LoyaltyProgram::whereIn('id', $request->ids)->delete();

        return $this->noContent();
    }

    public function simulate(Request $request, LoyaltyService $loyaltyService): JsonResponse
    {
        $request->validate([
            'partner_id' => 'required|integer|exists:partners,id',
            'total' => 'required|numeric|min:0',
            'total_qty' => 'sometimes|integer|min:0',
            'module' => 'sometimes|string|in:sales,pos,web',
        ]);

        $customer = Partner::findOrFail($request->input('partner_id'));
        $result = $loyaltyService->simulate(
            $customer,
            (float) $request->input('total'),
            (int) $request->input('total_qty', 0),
            $request->input('module', 'sales'),
        );

        return $this->success($result);
    }

    public function validateCode(Request $request, LoyaltyService $loyaltyService): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'partner_id' => 'required|integer|exists:partners,id',
            'total' => 'required|numeric|min:0',
        ]);

        $customer = Partner::findOrFail($request->input('partner_id'));
        $result = $loyaltyService->validateCode(
            $request->input('code'),
            $customer,
            (float) $request->input('total'),
        );

        if (! $result['valid']) {
            return $this->error($result['error'], 422);
        }

        return $this->success($result);
    }

    public function toggleStatus(LoyaltyProgram $program): JsonResponse
    {
        $program->update(['is_active' => ! $program->is_active]);
        $program->load(['rules.productVariants', 'rules.productTemplates', 'rules.categories', 'rewards']);

        return $this->success(new LoyaltyProgramResource($program));
    }

    private function syncRules(LoyaltyProgram $program, array $rulesData): void
    {
        $program->rules()->delete();

        foreach ($rulesData as $ruleData) {
            $variantIds = $ruleData['product_variant_ids'] ?? [];
            $templateIds = $ruleData['product_template_ids'] ?? [];
            $categoryIds = $ruleData['category_ids'] ?? [];
            unset($ruleData['product_variant_ids'], $ruleData['product_template_ids'], $ruleData['category_ids']);

            $rule = $program->rules()->create($ruleData);

            if ($variantIds) {
                $rule->productVariants()->sync($variantIds);
            }
            if ($templateIds) {
                $rule->productTemplates()->sync($templateIds);
            }
            if ($categoryIds) {
                $rule->categories()->sync($categoryIds);
            }
        }
    }

    private function syncRewards(LoyaltyProgram $program, array $rewardsData): void
    {
        $program->rewards()->delete();

        foreach ($rewardsData as $rewardData) {
            $discountProductIds = $rewardData['discount_product_ids'] ?? [];
            $discountCategoryIds = $rewardData['discount_category_ids'] ?? [];
            unset($rewardData['discount_product_ids'], $rewardData['discount_category_ids']);

            $reward = $program->rewards()->create($rewardData);

            if ($discountProductIds) {
                $reward->discountProducts()->sync($discountProductIds);
            }
            if ($discountCategoryIds) {
                $reward->discountCategories()->sync($discountCategoryIds);
            }
        }
    }
}
