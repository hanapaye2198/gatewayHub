<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configurable platform fee rule with scope and effective period.
 * scope_type: global | merchant | gateway | merchant_gateway
 * fee_type: percentage | flat
 */
class PlatformFeeRule extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'scope_type',
        'scope_id',
        'fee_type',
        'fee_value',
        'is_active',
        'effective_from',
        'effective_to',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fee_value' => 'decimal:4',
            'is_active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }
}
