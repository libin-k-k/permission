<?php

namespace Libinkk\Permission\Scopes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Libinkk\Permission\Support\Tables;
use Libinkk\Permission\Support\UsesConfiguredKeys;

class Scope extends Model
{
    use UsesConfiguredKeys;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return Tables::get('scopes', 'scopes');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Find or create a scope bound to an application model (organization, workspace, …).
     */
    public static function for(object $scopeable, ?string $type = null, ?self $parent = null): self
    {
        $type ??= class_basename($scopeable);
        $morphType = method_exists($scopeable, 'getMorphClass') ? $scopeable->getMorphClass() : $scopeable::class;
        $id = method_exists($scopeable, 'getKey') ? (string) $scopeable->getKey() : (string) spl_object_id($scopeable);

        $scope = static::query()
            ->where('scopeable_type', $morphType)
            ->where('scopeable_id', $id)
            ->first();

        if ($scope) {
            if ($parent && (string) $scope->parent_id !== (string) $parent->getKey()) {
                $scope->parent_id = $parent->getKey();
                $scope->save();
            }

            return $scope;
        }

        $name = $scopeable->name ?? $scopeable->slug ?? $type.' #'.$id;

        return static::query()->create([
            'type' => strtolower($type),
            'name' => (string) $name,
            'key' => $scopeable->slug ?? $id,
            'parent_id' => $parent?->getKey(),
            'scopeable_type' => $morphType,
            'scopeable_id' => $id,
        ]);
    }

    public function identity(): string
    {
        if ($this->scopeable_type && $this->scopeable_id !== null && $this->scopeable_id !== '') {
            return $this->scopeable_type.':'.$this->scopeable_id;
        }

        return 'scope:'.$this->getKey();
    }
}
