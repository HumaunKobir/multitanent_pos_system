const routes = {
    home: '/',
    dashboard: '/dashboard',
    'admin.dashboard': '/admin',
    'branch-panel.dashboard': '/branch-panel',
    login: '/login',
    register: '/register',
    logout: '/logout',
    'password.request': '/forgot-password',
    'password.reset': '/reset-password/:token',
    'password.update': '/reset-password',
    'password.confirm': '/user/confirm-password',
    'two-factor.login': '/two-factor-challenge',
    'profile.edit': '/settings/profile',
    'security.edit': '/settings/security',
    'appearance.edit': '/settings/appearance',
    'setting.category.index': '/setting/category',
    'setting.category.store': '/setting/category',
    'setting.category.update': '/setting/category/:category',
    'setting.category.destroy': '/setting/category/:category',
    'setting.tag.index': '/setting/tag',
    'setting.tag.store': '/setting/tag',
    'setting.tag.update': '/setting/tag/:tag',
    'setting.tag.destroy': '/setting/tag/:tag',
    'setting.brand.index': '/setting/brand',
    'setting.brand.store': '/setting/brand',
    'setting.brand.update': '/setting/brand/:brand',
    'setting.brand.destroy': '/setting/brand/:brand',
    'setting.unit.index': '/setting/unit',
    'setting.unit.store': '/setting/unit',
    'setting.unit.update': '/setting/unit/:unit',
    'setting.unit.destroy': '/setting/unit/:unit',
    'setting.size.index': '/setting/size',
    'setting.size.store': '/setting/size',
    'setting.size.update': '/setting/size/:size',
    'setting.size.destroy': '/setting/size/:size',
    'setting.color.index': '/setting/color',
    'setting.color.store': '/setting/color',
    'setting.color.update': '/setting/color/:color',
    'setting.color.destroy': '/setting/color/:color',
    'setting.tailormeasurement.index': '/setting/tailormeasurement',
    'setting.tailormeasurement.store': '/setting/tailormeasurement',
    'setting.tailormeasurement.update': '/setting/tailormeasurement/:tailormeasurement',
    'setting.tailormeasurement.destroy': '/setting/tailormeasurement/:tailormeasurement',
    'setting.warranty.index': '/setting/warranty',
    'setting.warranty.store': '/setting/warranty',
    'setting.warranty.update': '/setting/warranty/:warranty',
    'setting.warranty.destroy': '/setting/warranty/:warranty',
    'setting.slider.index': '/setting/slider',
    'setting.slider.store': '/setting/slider',
    'setting.slider.update': '/setting/slider/:slider',
    'setting.slider.destroy': '/setting/slider/:slider',
    'setting.productsection.index': '/setting/productsection',
    'setting.productsection.store': '/setting/productsection',
    'setting.productsection.update': '/setting/productsection/:productsection',
    'setting.productsection.destroy': '/setting/productsection/:productsection',
    'setting.productsection.update-order': '/setting/productsection/update-order',
    'branch.index': '/branch',
    'branch.store': '/branch',
    'branch.update': '/branch/:branch',
    'branch.destroy': '/branch/:branch',
    'user.index': '/user',
    'user.store': '/user',
    'user.update': '/user/:user',
    'user.destroy': '/user/:user',
    'product.index': '/product',
    'product.create': '/product/create',
    'product.store': '/product',
    'product.edit': '/product/:product/edit',
    'product.update': '/product/:product',
    'product.destroy': '/product/:product',
    'variation.store': '/variation',
    'variation.destroy': '/variation/:variation',
    'inventory.purchase.index': '/inventory/purchase',
    'inventory.purchase.create': '/inventory/purchase/create',
    'inventory.purchase.store': '/inventory/purchase',
    'inventory.purchase.edit': '/inventory/purchase/:purchase/edit',
    'inventory.purchase.update': '/inventory/purchase/:purchase',
    'inventory.purchase.show': '/inventory/purchase/:purchase',
    'inventory.purchase.destroy': '/inventory/purchase/:purchase',
    'party.supplier.index': '/party/supplier',
    'party.supplier.store': '/party/supplier',
    'party.supplier.update': '/party/supplier/:supplier',
    'party.supplier.destroy': '/party/supplier/:supplier',
    'api.suppliers': '/api/suppliers',
    'api.products.purchase': '/api/products/for-purchase',
};

const methods = {
    'login.store': 'post',
    'register.store': 'post',
    logout: 'post',
    'password.email': 'post',
    'password.update': 'post',
    'password.confirm.store': 'post',
    'profile.update': 'patch',
    'profile.destroy': 'delete',
    'user-password.update': 'put',
    'verification.send': 'post',
    'two-factor.login.store': 'post',
    'two-factor.enable': 'post',
    'two-factor.disable': 'delete',
    'two-factor.confirm': 'post',
    'two-factor.qr-code': 'get',
    'two-factor.recovery-codes': 'get',
    'two-factor.secret-key': 'get',
    'two-factor.regenerate-recovery-codes': 'post',
    'setting.category.store': 'post',
    'setting.category.update': 'patch',
    'setting.category.destroy': 'delete',
    'setting.tag.store': 'post',
    'setting.tag.update': 'patch',
    'setting.tag.destroy': 'delete',
    'setting.brand.store': 'post',
    'setting.brand.update': 'patch',
    'setting.brand.destroy': 'delete',
    'setting.unit.store': 'post',
    'setting.unit.update': 'patch',
    'setting.unit.destroy': 'delete',
    'setting.size.store': 'post',
    'setting.size.update': 'patch',
    'setting.size.destroy': 'delete',
    'setting.color.store': 'post',
    'setting.color.update': 'patch',
    'setting.color.destroy': 'delete',
    'setting.tailormeasurement.store': 'post',
    'setting.tailormeasurement.update': 'patch',
    'setting.tailormeasurement.destroy': 'delete',
    'setting.warranty.store': 'post',
    'setting.warranty.update': 'patch',
    'setting.warranty.destroy': 'delete',
    'setting.slider.store': 'post',
    'setting.slider.update': 'patch',
    'setting.slider.destroy': 'delete',
    'setting.productsection.store': 'post',
    'setting.productsection.update': 'patch',
    'setting.productsection.destroy': 'delete',
    'setting.productsection.update-order': 'post',
    'branch.store': 'post',
    'branch.update': 'patch',
    'branch.destroy': 'delete',
    'user.store': 'post',
    'user.update': 'patch',
    'user.destroy': 'delete',
    'product.store': 'post',
    'product.update': 'patch',
    'product.destroy': 'delete',
    'variation.store': 'post',
    'variation.destroy': 'delete',
    'inventory.purchase.store': 'post',
    'inventory.purchase.update': 'put',
    'inventory.purchase.destroy': 'delete',
    'party.supplier.store': 'post',
    'party.supplier.update': 'patch',
    'party.supplier.destroy': 'delete',
};

const aliases = {
    'login.store': 'login',
    'register.store': 'register',
    'password.email': 'password.request',
    'password.confirm.store': 'password.confirm',
    'two-factor.login.store': 'two-factor.login',
};

function buildQuery(query) {
    if (!query || Object.keys(query).length === 0) {
        return '';
    }

    const params = new URLSearchParams();

    for (const [key, value] of Object.entries(query)) {
        if (value !== undefined && value !== null && value !== '') {
            params.set(key, String(value));
        }
    }

    const queryString = params.toString();

    return queryString ? `?${queryString}` : '';
}

function resolveParams(template, params) {
    let path = template;

    if (params === undefined || params === null) {
        return path;
    }

    if (typeof params !== 'object') {
        const placeholder = path.match(/:([A-Za-z0-9_]+)/);

        if (placeholder) {
            path = path.replace(`:${placeholder[1]}`, String(params));
        }

        return path;
    }

    if (params.query) {
        return path + buildQuery(params.query);
    }

    for (const [key, value] of Object.entries(params)) {
        if (key === 'query') {
            continue;
        }

        path = path.replace(`:${key}`, String(value));
    }

    return path;
}

export function route(name, params) {
    const template = routes[aliases[name] ?? name];

    if (!template) {
        throw new Error(`Unknown route: ${name}`);
    }

    return resolveParams(template, params);
}

export function routeForm(name, params) {
    return {
        action: route(name, params),
        method: methods[name] ?? 'get',
    };
}

export function routeRequest(name, params) {
    return {
        url: route(name, params),
        method: methods[name] ?? 'get',
    };
}

export function settingRoutes(resource) {
    const prefix = `setting.${resource}`;

    const routes = {
        index: (query) => route(`${prefix}.index`, query ? { query } : undefined),
        store: route(`${prefix}.store`),
        update: (id) => route(`${prefix}.update`, { [resource]: id }),
        destroy: (id) => route(`${prefix}.destroy`, { [resource]: id }),
    };

    if (resource === 'productsection') {
        routes.updateOrder = () => route(`${prefix}.update-order`);
    }

    return routes;
}

export function resourceRoutes(resource) {
    const prefix = resource;

    return {
        index: (query) => route(`${prefix}.index`, query ? { query } : undefined),
        store: route(`${prefix}.store`),
        update: (id) => route(`${prefix}.update`, { [resource]: id }),
        destroy: (id) => route(`${prefix}.destroy`, { [resource]: id }),
    };
}
