<?php

namespace Libinkk\Permission\Delegation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Libinkk\Permission\Permissions\Permission;
use Libinkk\Permission\Scopes\Scope;
use Libinkk\Permission\Support\Tables;
use Libinkk\Permission\Support\UsesConfiguredKeys;

class Delegation extends Model
{
    use UsesConfiguredKeys;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return Tables::permissionDelegations();
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(Scope::class, 'scope_id');
    }

    public function fromUser(): MorphTo
    {
        return $this->morphTo('from_user');
    }

    public function toUser(): MorphTo
    {
        return $this->morphTo('to_user');
    }

    public function isActive(?\DateTimeInterface $at = null): bool
    {
        if ($this->status === self::STATUS_REVOKED || $this->revoked_at !== null) {
            return false;
        }

        $at ??= now();

        if ($this->starts_at && $this->starts_at->gt($at)) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->lte($at)) {
            return false;
        }

        return $this->status !== self::STATUS_EXPIRED;
    }

    public function isExpired(?\DateTimeInterface $at = null): bool
    {
        $at ??= now();

        return $this->revoked_at === null
            && $this->expires_at !== null
            && $this->expires_at->lte($at);
    }

    public function revoke(?string $reason = null, ?object $actor = null): static
    {
        return app(DelegationManager::class)->revoke($this, $reason, $actor);
    }
}
