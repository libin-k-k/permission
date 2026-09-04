/**
 * UI-only authorization store. Never treat this as a security boundary.
 */

export function matchesWildcard(pattern, permission) {
    if (pattern === permission) {
        return true;
    }

    if (!String(pattern).includes('*')) {
        return false;
    }

    if (pattern === '*') {
        return String(permission).length > 0;
    }

    if (pattern.endsWith('.*')) {
        const prefix = pattern.slice(0, -2);
        const escaped = prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

        return new RegExp(`^${escaped}(\\.[^.]+)+$`).test(permission);
    }

    const escaped = String(pattern)
        .replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
        .replace(/\\\*/g, '[^.]+');

    return new RegExp(`^${escaped}$`).test(permission);
}

export function createPermissionStore(payload = {}) {
    const roles = Array.isArray(payload.roles) ? payload.roles : [];
    const permissions = Array.isArray(payload.permissions) ? payload.permissions : [];
    const denials = Array.isArray(payload.denials) ? payload.denials : [];

    const isDenied = (name) =>
        denials.some((pattern) => matchesWildcard(pattern, name));

    const isAllowed = (name) =>
        permissions.some((pattern) => matchesWildcard(pattern, name));

    const can = (name) => {
        if (!name || isDenied(name)) {
            return false;
        }

        return isAllowed(name);
    };

    const wrap = (value) => (Array.isArray(value) ? value : [value]).filter(Boolean);

    const canAny = (names) => wrap(names).some((name) => can(name));
    const canAll = (names) => {
        const list = wrap(names);

        return list.length > 0 && list.every((name) => can(name));
    };

    const hasRole = (name) => {
        const wanted = wrap(name);

        return wanted.some((role) => roles.includes(role));
    };

    return {
        payload,
        roles,
        permissions,
        denials,
        can,
        canAny,
        canAll,
        hasRole,
    };
}

export function payloadFromWindow(key = '__LIBINKK_PERMISSION__') {
    if (typeof window === 'undefined') {
        return {};
    }

    return window[key] || {};
}
