<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinsTransaction extends Model
{
    public const STATUS_PENDING = 'PENDING';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'reference_id',
        'amount',
        'currency',
        'status',
        'qr_code_string',
        'raw_response',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'raw_response' => 'array',
        ];
    }
}
