import { inject } from 'vue';
import { createPermissionStore, payloadFromWindow } from '../shared/store.js';

export const permissionKey = 'libinkkPermission';

export function createPermissionPlugin(payload) {
    const store = createPermissionStore(payload ?? payloadFromWindow());

    return {
        install(app) {
            app.config.globalProperties.$can = store.can;
            app.config.globalProperties.$canAny = store.canAny;
            app.config.globalProperties.$canAll = store.canAll;
            app.config.globalProperties.$hasRole = store.hasRole;
            app.provide(permissionKey, store);
        },
    };
}

export function usePermission() {
    return inject(permissionKey) ?? createPermissionStore(payloadFromWindow());
}

export { createPermissionStore, matchesWildcard } from '../shared/store.js';
