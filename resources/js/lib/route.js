const routes = {
    home: '/',
    search: '/search',
    'search.suggestions': '/search/suggestions',
    dashboard: '/dashboard',
    'admin.dashboard': '/dashboard',
    'branch-panel.dashboard': '/branch-panel',
    login: '/login',
    register: '/register',
    logout: '/logout',
    'password.request': '/forgot-password',
    'password.verify': '/reset-password/verify',
    'password.verify.store': '/reset-password/verify',
    'password.reset': '/reset-password',
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
    'setting.website.edit': '/setting/website',
    'setting.website.update': '/setting/website',
    'setting.website.preview-email.order': '/setting/website/preview/email/order',
    'setting.website.preview-email.payment': '/setting/website/preview/email/payment',
    'setting.website.preview-invoice': '/setting/website/preview/invoice',
    'setting.page-content.edit': '/setting/page-content/:page',
    'setting.page-content.update': '/setting/page-content/:page',
    'setting.faq.index': '/setting/faq',
    'setting.faq.store': '/setting/faq',
    'setting.faq.update': '/setting/faq/:faq',
    'setting.faq.destroy': '/setting/faq/:faq',
    'setting.faq.update-order': '/setting/faq/update-order',
    'setting.special-discount.index': '/setting/special-discount',
    'setting.special-discount.store': '/setting/special-discount',
    'setting.special-discount.update': '/setting/special-discount/:specialDiscount',
    'setting.special-discount.destroy': '/setting/special-discount/:specialDiscount',
    'setting.promotion.index': '/setting/promotion',
    'setting.promotion.store': '/setting/promotion',
    'setting.promotion.update': '/setting/promotion/:promotion',
    'setting.promotion.destroy': '/setting/promotion/:promotion',
    'setting.admin-profile.edit': '/setting/admin-profile',
    'setting.admin-profile.update': '/setting/admin-profile',
    'setting.branch-profile.edit': '/setting/branch-profile',
    'setting.branch-profile.update': '/setting/branch-profile',
    'setting.pos-terms.edit': '/setting/pos-terms',
    'setting.pos-terms.update': '/setting/pos-terms',
    'setting.coin-settings.index': '/setting/coin-settings',
    'setting.coin-settings.create': '/setting/coin-settings/create',
    'setting.coin-settings.store': '/setting/coin-settings',
    'setting.coin-settings.edit': '/setting/coin-settings/edit',
    'setting.coin-settings.update': '/setting/coin-settings',
    'branch.index': '/branch',
    'branch.store': '/branch',
    'branch.update': '/branch/:branch',
    'branch.destroy': '/branch/:branch',
    'user.index': '/user',
    'user.store': '/user',
    'user.update': '/user/:user',
    'user.destroy': '/user/:user',
    'role.index': '/role',
    'role.create': '/role/create',
    'role.store': '/role',
    'role.edit': '/role/:role/edit',
    'role.update': '/role/:role',
    'role.destroy': '/role/:role',
    'role.permissions': '/role/:role/permissions',
    'role.permissions.update': '/role/:role/permissions',
    'contact-list.index': '/contact-list',
    'contact-list.destroy': '/contact-list/:contact',
    'online-order.index': '/online-order',
    'online-order.show': '/online-order/:onlineOrder',
    'online-order.send-steadfast': '/online-order/:onlineOrder/steadfast',
    'online-order.sync-steadfast': '/online-order/:onlineOrder/steadfast/sync',
    'online-order.fulfill': '/online-order/:onlineOrder/fulfill',
    'online-order.update-status': '/online-order/:onlineOrder/status',
    'online-order.invoice': '/online-order/:onlineOrder/invoice',
    'online-customer.index': '/online-customer',
    'online-customer.show': '/online-customer/:customer',
    'subscriber-list.index': '/subscriber-list',
    'subscriber-list.send-bulk-mail': '/subscriber-list/mail/bulk',
    'subscriber-list.send-mail': '/subscriber-list/:subscriber/mail',
    'subscriber-list.destroy': '/subscriber-list/:subscriber',
    'subscribe.store': '/subscribe',
    'barcode.index': '/barcode',
    'barcode.serial-range': '/barcode/serial-range',
    'barcode.print': '/barcode/print',
    'product.index': '/product',
    'product.create': '/product/create',
    'product.store': '/product',
    'product.edit': '/product/:product/edit',
    'product.update': '/product/:product',
    'product.destroy': '/product/:product',
    'product.receive': '/product/:product/receive',
    'variation.store': '/variation',
    'variation.destroy': '/variation/:variation',
    'inventory.purchase.index': '/inventory/purchase',
    'inventory.purchase.create': '/inventory/purchase/create',
    'inventory.purchase.store': '/inventory/purchase',
    'inventory.purchase.edit': '/inventory/purchase/:purchase/edit',
    'inventory.purchase.update': '/inventory/purchase/:purchase',
    'inventory.purchase.show': '/inventory/purchase/:purchase',
    'inventory.purchase.destroy': '/inventory/purchase/:purchase',
    'inventory.sell.index': '/inventory/sell',
    'inventory.sell.create': '/inventory/sell/create',
    'inventory.sell.pause': '/inventory/sell/pause',
    'inventory.sell.store': '/inventory/sell',
    'inventory.sell.edit': '/inventory/sell/:sell/edit',
    'inventory.sell.update': '/inventory/sell/:sell',
    'inventory.sell.show': '/inventory/sell/:sell',
    'inventory.sell.destroy': '/inventory/sell/:sell',
    'inventory.purchase-return.index': '/inventory/purchase-return',
    'inventory.purchase-return.create': '/inventory/purchase-return/create',
    'inventory.purchase-return.store': '/inventory/purchase-return',
    'inventory.purchase-return.show': '/inventory/purchase-return/:purchase_return',
    'inventory.purchase-return.edit': '/inventory/purchase-return/:purchase_return/edit',
    'inventory.purchase-return.update': '/inventory/purchase-return/:purchase_return',
    'inventory.purchase-return.destroy': '/inventory/purchase-return/:purchase_return',
    'inventory.damage.index': '/inventory/damage',
    'inventory.damage.create': '/inventory/damage/create',
    'inventory.damage.store': '/inventory/damage',
    'inventory.damage.show': '/inventory/damage/:damage',
    'inventory.damage.edit': '/inventory/damage/:damage/edit',
    'inventory.damage.update': '/inventory/damage/:damage',
    'inventory.damage.destroy': '/inventory/damage/:damage',
    'inventory.stock-distribution.index': '/inventory/stock-distribution',
    'inventory.stock-distribution.received': '/inventory/stock-distribution/received',
    'inventory.stock-distribution.create': '/inventory/stock-distribution/create',
    'inventory.stock-distribution.store': '/inventory/stock-distribution',
    'inventory.stock-distribution.show': '/inventory/stock-distribution/:stock_distribution',
    'inventory.stock-distribution.receive': '/inventory/stock-distribution/:stock_distribution/receive',
    'inventory.stock-distribution.edit': '/inventory/stock-distribution/:stock_distribution/edit',
    'inventory.stock-distribution.update': '/inventory/stock-distribution/:stock_distribution',
    'inventory.stock-distribution.destroy': '/inventory/stock-distribution/:stock_distribution',
    'inventory.sale-return.index': '/inventory/sale-return',
    'inventory.sale-return.create': '/inventory/sale-return/create',
    'inventory.sale-return.store': '/inventory/sale-return',
    'inventory.sale-return.show': '/inventory/sale-return/:sale_return',
    'inventory.sale-return.edit': '/inventory/sale-return/:sale_return/edit',
    'inventory.sale-return.update': '/inventory/sale-return/:sale_return',
    'inventory.sale-return.destroy': '/inventory/sale-return/:sale_return',
    'inventory.product-exchange.index': '/inventory/product-exchange',
    'inventory.product-exchange.create': '/inventory/product-exchange/create',
    'inventory.product-exchange.store': '/inventory/product-exchange',
    'inventory.product-exchange.show': '/inventory/product-exchange/:product_exchange',
    'inventory.product-exchange.edit': '/inventory/product-exchange/:product_exchange/edit',
    'inventory.product-exchange.update': '/inventory/product-exchange/:product_exchange',
    'inventory.product-exchange.destroy': '/inventory/product-exchange/:product_exchange',
    'api.purchases.lookup': '/api/purchases/lookup',
    'api.sales.lookup': '/api/sales/lookup',
    'api.suppliers.due-purchases': '/api/suppliers/:supplier/due-purchases',
    'api.customers.due-sales': '/api/customers/:customer/due-sales',
    'party.supplier.index': '/party/supplier',
    'party.supplier.store': '/party/supplier',
    'party.supplier.update': '/party/supplier/:supplier',
    'party.supplier.destroy': '/party/supplier/:supplier',
    'party.supplier-payment.index': '/party/supplier-payment',
    'party.supplier-payment.store': '/party/supplier-payment',
    'party.supplier-payment.update': '/party/supplier-payment/:supplier_payment',
    'party.supplier-payment.destroy': '/party/supplier-payment/:supplier_payment',
    'party.customer-due-collection.index': '/party/customer-due-collection',
    'party.customer-due-collection.store': '/party/customer-due-collection',
    'party.customer-due-collection.update': '/party/customer-due-collection/:customer_payment',
    'party.customer-due-collection.destroy': '/party/customer-due-collection/:customer_payment',
    'party.customer-due-alert.index': '/party/customer-due-alert',
    'party.customer-due-alert.store': '/party/customer-due-alert',
    'party.customer-due-alert.update': '/party/customer-due-alert/:customer_due_alert',
    'party.customer-due-alert.destroy': '/party/customer-due-alert/:customer_due_alert',
    'party.customer.index': '/party/customer',
    'party.customer.store': '/party/customer',
    'party.customer.update': '/party/customer/:customer',
    'party.customer.destroy': '/party/customer/:customer',
    'report.customer-ledger': '/report/customer-ledger',
    'report.cash-flow': '/report/cash-flow',
    'report.cash-flow-summary': '/report/cash-flow-summary',
    'report.daily-transactions': '/report/daily-transactions',
    'report.date-wise-stock': '/report/date-wise-stock',
    'report.stock-ledger': '/report/stock-ledger',
    'report.inventory-stock': '/report/inventory-stock',
    'report.daily-summary': '/report/daily-summary',
    'report.account-ledger': '/report/account-ledger',
    'report.account-transactions': '/report/account-transactions',
    'report.balance-sheet': '/report/balance-sheet',
    'api.suppliers': '/api/suppliers',
    'api.suppliers.store': '/api/suppliers',
    'api.products.catalog-options': '/api/products/catalog-options',
    'api.products.purchase': '/api/products/for-purchase',
    'api.products.sell': '/api/products/for-sell',
    'api.products.distribution': '/api/products/for-distribution',
    'api.customers': '/api/customers',
    'api.customers.store': '/api/customers',
    'api.customers.due-alert': '/api/customers/:customer/due-alert',
    'api.customers.coins': '/api/customers/:customer/coins',
    'accounts.index': '/accounts',
    'accounts.store': '/accounts',
    'accounts.next-code': '/accounts/next-code',
    'accounts.update': '/accounts/:chartOfAccount',
    'accounts.destroy': '/accounts/:chartOfAccount',
    'accounts.journal-voucher.index': '/accounts/vouchers?type=journal',
    'accounts.contra-voucher.index': '/accounts/vouchers?type=contra',
    'accounts.income-voucher.index': '/accounts/vouchers?type=income',
    'accounts.expense-voucher.index': '/accounts/vouchers?type=expense',
    'accounts.vouchers.index': '/accounts/vouchers',
    'accounts.vouchers.store': '/accounts/vouchers',
    'accounts.vouchers.show': '/accounts/vouchers/:voucher',
    'accounts.vouchers.update': '/accounts/vouchers/:voucher',
    'accounts.vouchers.destroy': '/accounts/vouchers/:voucher',
    'accounts.vouchers.next-number': '/accounts/vouchers/next-number',
};

const methods = {
    'login.store': 'post',
    'register.store': 'post',
    logout: 'post',
    'password.email': 'post',
    'password.verify.store': 'post',
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
    'setting.website.update': 'put',
    'setting.admin-profile.update': 'put',
    'setting.branch-profile.update': 'put',
    'setting.pos-terms.update': 'put',
    'setting.coin-settings.store': 'post',
    'setting.coin-settings.update': 'put',
    'setting.page-content.update': 'put',
    'setting.faq.store': 'post',
    'setting.faq.update': 'patch',
    'setting.faq.destroy': 'delete',
    'setting.faq.update-order': 'post',
    'branch.store': 'post',
    'branch.update': 'patch',
    'branch.destroy': 'delete',
    'user.store': 'post',
    'user.update': 'patch',
    'user.destroy': 'delete',
    'role.store': 'post',
    'role.update': 'patch',
    'role.destroy': 'delete',
    'role.permissions.update': 'put',
    'product.store': 'post',
    'product.receive': 'post',
    'product.update': 'patch',
    'product.destroy': 'delete',
    'variation.store': 'post',
    'variation.destroy': 'delete',
    'inventory.purchase.store': 'post',
    'inventory.purchase.update': 'put',
    'inventory.purchase.destroy': 'delete',
    'inventory.sell.pause': 'post',
    'inventory.sell.store': 'post',
    'inventory.sell.update': 'put',
    'inventory.sell.destroy': 'delete',
    'inventory.purchase-return.store': 'post',
    'inventory.purchase-return.update': 'put',
    'inventory.purchase-return.destroy': 'delete',
    'inventory.damage.store': 'post',
    'inventory.damage.update': 'put',
    'inventory.damage.destroy': 'delete',
    'inventory.stock-distribution.store': 'post',
    'inventory.stock-distribution.receive': 'post',
    'inventory.stock-distribution.update': 'put',
    'inventory.stock-distribution.destroy': 'delete',
    'inventory.sale-return.store': 'post',
    'inventory.sale-return.update': 'put',
    'inventory.sale-return.destroy': 'delete',
    'inventory.product-exchange.store': 'post',
    'inventory.product-exchange.update': 'put',
    'inventory.product-exchange.destroy': 'delete',
    'party.supplier.store': 'post',
    'party.supplier.update': 'patch',
    'party.supplier.destroy': 'delete',
    'party.supplier-payment.store': 'post',
    'party.supplier-payment.destroy': 'delete',
    'party.customer-due-collection.store': 'post',
    'party.customer-due-collection.destroy': 'delete',
    'party.customer-due-alert.store': 'post',
    'party.customer-due-alert.update': 'patch',
    'party.customer-due-alert.destroy': 'delete',
    'party.customer.store': 'post',
    'party.customer.update': 'patch',
    'party.customer.destroy': 'delete',
    'accounts.store': 'post',
    'accounts.update': 'patch',
    'accounts.destroy': 'delete',
    'accounts.vouchers.store': 'post',
    'accounts.vouchers.update': 'put',
    'accounts.vouchers.destroy': 'delete',
    'contact-list.destroy': 'delete',
    'online-order.send-steadfast': 'post',
    'online-order.sync-steadfast': 'patch',
    'online-order.fulfill': 'patch',
    'online-order.update-status': 'patch',
    'subscriber-list.send-bulk-mail': 'post',
    'subscriber-list.send-mail': 'post',
    'subscriber-list.destroy': 'delete',
    'subscribe.store': 'post',
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

    if (resource === 'productsection' || resource === 'faq') {
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
