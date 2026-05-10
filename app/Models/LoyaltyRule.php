<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class LoyaltyRule extends Model
{
    protected $fillable = [
        'loyalty_program_id',
        'reward_point_amount',
        'reward_point_mode',
        'minimum_qty',
        'minimum_amount',
        'code',
        'conditions',
    ];

    protected function casts(): array
    {
        return [
            'reward_point_amount' => 'decimal:2',
            'minimum_qty' => 'integer',
            'minimum_amount' => 'decimal:2',
            'conditions' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id');
    }

    public function productVariants(): BelongsToMany
    {
        return $this->belongsToMany(ProductProduct::class, 'loyalty_rule_product_product');
    }

    public function productTemplates(): BelongsToMany
    {
        return $this->belongsToMany(ProductTemplate::class, 'loyalty_rule_product_template');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'loyalty_rule_category');
    }

    public function appliesToAllProducts(): bool
    {
        return $this->productVariants()->count() === 0
            && $this->productTemplates()->count() === 0
            && $this->categories()->count() === 0;
    }
}
