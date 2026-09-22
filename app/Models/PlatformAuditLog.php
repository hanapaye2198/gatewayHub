<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\PlatformAuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const ACTION_MERCHANT_CREATED = 'merchant.created';

    public const ACTION_MERCHANT_UPDATED = 'merchant.updated';

    public const ACTION_MERCHANT_ACTIVATED = 'merchant.activated';

    public const ACTION_MERCHANT_SUSPENDED = 'merchant.suspended';

    public const ACTION_MERCHANT_USER_CREATED = 'merchant_user.created';

    public const ACTION_MERCHANT_USER_UPDATED = 'merchant_user.updated';

    public const ACTION_MERCHANT_USER_ENABLED = 'merchant_user.enabled';

    public const ACTION_MERCHANT_USER_DISABLED = 'merchant_user.disabled';

    public const ACTION_MERCHANT_CONTEXT_ENTERED = 'merchant.context_entered';

    public const ACTION_MERCHANT_CONTEXT_EXITED = 'merchant.context_exited';

    /**
     * @var list<string>
     */
    public const ACTIONS = [
        self::ACTION_MERCHANT_CREATED,
        self::ACTION_MERCHANT_UPDATED,
        self::ACTION_MERCHANT_ACTIVATED,
        self::ACTION_MERCHANT_SUSPENDED,
        self::ACTION_MERCHANT_USER_CREATED,
        self::ACTION_MERCHANT_USER_UPDATED,
        self::ACTION_MERCHANT_USER_ENABLED,
        self::ACTION_MERCHANT_USER_DISABLED,
        self::ACTION_MERCHANT_CONTEXT_ENTERED,
        self::ACTION_MERCHANT_CONTEXT_EXITED,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_user_id',
        'action',
        'merchant_id',
        'target_type',
        'target_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<static>
     */
    public function scopeFiltered(Builder $query, array $filters): Builder
    {
        return $query
            ->when(
                filled($filters['from_date'] ?? null),
                fn (Builder $inner): Builder => $inner->whereDate('created_at', '>=', $filters['from_date'])
            )
            ->when(
                filled($filters['to_date'] ?? null),
                fn (Builder $inner): Builder => $inner->whereDate('created_at', '<=', $filters['to_date'])
            )
            ->when(
                filled($filters['action'] ?? null),
                fn (Builder $inner): Builder => $inner->where('action', $filters['action'])
            )
            ->when(
                filled($filters['merchant_id'] ?? null),
                fn (Builder $inner): Builder => $inner->where('merchant_id', $filters['merchant_id'])
            )
            ->when(
                filled($filters['actor_user_id'] ?? null),
                fn (Builder $inner): Builder => $inner->where('actor_user_id', $filters['actor_user_id'])
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function actorLabel(): string
    {
        $name = $this->actor?->name;
        if (is_string($name) && $name !== '') {
            return $name;
        }

        if ($this->actor_user_id !== null) {
            return 'User #'.$this->actor_user_id;
        }

        return 'Unknown';
    }

    public function merchantLabel(): string
    {
        $name = $this->merchant?->name;

        return is_string($name) && $name !== '' ? $name : '—';
    }

    public function targetLabel(): string
    {
        if ($this->target_type === null || $this->target_id === null) {
            return '—';
        }

        return $this->target_type.' #'.$this->target_id;
    }
}
