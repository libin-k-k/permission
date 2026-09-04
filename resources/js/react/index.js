import { createContext, createElement, useContext } from 'react';
import { createPermissionStore, payloadFromWindow } from '../shared/store.js';

const PermissionContext = createContext(null);

export function PermissionProvider({ value, children }) {
    const store = value && typeof value.can === 'function'
        ? value
        : createPermissionStore(value ?? payloadFromWindow());

    return createElement(PermissionContext.Provider, { value: store }, children);
}

export function usePermission() {
    return useContext(PermissionContext) ?? createPermissionStore(payloadFromWindow());
}

export function Can({ permission, children }) {
    const { can } = usePermission();

    return can(permission) ? children : null;
}

export function CanAny({ permissions, children }) {
    const { canAny } = usePermission();

    return canAny(permissions) ? children : null;
}

export function CanAll({ permissions, children }) {
    const { canAll } = usePermission();

    return canAll(permissions) ? children : null;
}

export { createPermissionStore, matchesWildcard } from '../shared/store.js';
