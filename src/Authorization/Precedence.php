<?php

namespace Libinkk\Permission\Authorization;

final class Precedence
{
    public const EXPLICIT_DENY = 'explicit_deny';

    public const EXPLICIT_ALLOW = 'explicit_allow';

    public const ROLE_DENY = 'role_deny';

    public const ROLE_ALLOW = 'role_allow';

    public const INHERITED_DENY = 'inherited_deny';

    public const INHERITED_ALLOW = 'inherited_allow';

    /**
     * @return list<string>
     */
    public static function order(): array
    {
        return config('permission.deny.precedence', [
            self::EXPLICIT_DENY,
            self::EXPLICIT_ALLOW,
            self::ROLE_DENY,
            self::ROLE_ALLOW,
            self::INHERITED_DENY,
            self::INHERITED_ALLOW,
        ]);
    }

    public static function rank(string $layer): int
    {
        $order = self::order();
        $index = array_search($layer, $order, true);

        return $index === false ? 999 : $index;
    }

    public static function layerFor(string $effect, string $origin): string
    {
        $denyEnabled = (bool) config('permission.deny.enabled', true);
        $effect = strtolower($effect);

        if ($effect === 'deny' && ! $denyEnabled) {
            $effect = 'allow';
        }

        return match (true) {
            $origin === 'direct' && $effect === 'deny' => self::EXPLICIT_DENY,
            $origin === 'direct' && $effect === 'allow' => self::EXPLICIT_ALLOW,
            $origin === 'role' && $effect === 'deny' => self::ROLE_DENY,
            $origin === 'role' && $effect === 'allow' => self::ROLE_ALLOW,
            $origin === 'inherited' && $effect === 'deny' => self::INHERITED_DENY,
            default => self::INHERITED_ALLOW,
        };
    }

    public static function isDeny(string $layer): bool
    {
        return in_array($layer, [self::EXPLICIT_DENY, self::ROLE_DENY, self::INHERITED_DENY], true);
    }
}
