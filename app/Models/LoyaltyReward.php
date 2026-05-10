<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class LoyaltyReward extends Model
{
    protected $fillable = [
        'loyalty_program_id',
        'reward_type',
        'required_points',
        'description',
        'discount',
        'discount_mode',
        'discount_applicability',
        'discount_max_amount',
        'reward_product_id',
        'reward_product_qty',
    ];

    protected function casts(): array
    {
        return [
            'required_points' => 'decimal:2',
            'discount' => 'decimal:2',
            'discount_max_amount' => 'decimal:2',
            'reward_product_qty' => 'integer',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }

    public function rewardProduct(): BelongsTo
    {
        return $this->belongsTo(ProductProduct::class, 'reward_product_id');
    }

    public function discountProducts(): BelongsToMany
    {
        return $this->belongsToMany(ProductProduct::class, 'loyalty_reward_product_product');
    }

    public function discountCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'loyalty_reward_category');
    }

    public function isDiscount(): bool
    {
        return $this->reward_type === 'discount';
    }

    public function isFreeProduct(): bool
    {
        return $this->reward_type === 'product';
    }
}
