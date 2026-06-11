<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin sidebar navigation
    |--------------------------------------------------------------------------
    |
    | Each section may define:
    | - children: nested items (dropdown sections)
    | - href: route path
    | - single: true for top-level links without children
    | - icon: icon key used by admin-sidebar.jsx
    | - admin_only: visible only in the admin panel (superadmin)
    | - branch_only: visible only in the branch panel
    | - permission: required permission to see this item (null = always visible)
    |
    */

    'sections' => [
        [
            'title' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'href' => '/dashboard',
            'single' => true,
            'permission' => null,
        ],
        [
            'title' => 'Sales',
            'icon' => 'circle-dollar-sign',
            'branch_only' => true,
            'children' => [
                ['title' => 'Sale', 'href' => '/inventory/sell', 'permission' => 'inventory.sell.view'],
                ['title' => 'Sale Return', 'href' => '/inventory/sale-return', 'permission' => 'inventory.sale-return.view'],
                ['title' => 'Product Exchange', 'href' => '/inventory/product-exchange', 'permission' => 'inventory.product-exchange.view'],
                ['title' => 'Special Discount', 'href' => '/setting/special-discount', 'permission' => 'setting.special-discount.view'],
                ['title' => 'Received Stock', 'href' => '/inventory/stock-distribution', 'permission' => 'inventory.stock-distribution.view', 'branch_received_only' => true],
            ],
        ],
        [
            'title' => 'Purchases',
            'icon' => 'hand-coins',
            'children' => [
                ['title' => 'Purchase', 'href' => '/inventory/purchase', 'permission' => 'inventory.purchase.view'],
                ['title' => 'Purchase Return', 'href' => '/inventory/purchase-return', 'permission' => 'inventory.purchase-return.view'],
                ['title' => 'Damage', 'href' => '/inventory/damage', 'permission' => 'inventory.damage.view'],
                ['title' => 'Distribute Stock', 'href' => '/inventory/stock-distribution', 'permission' => 'inventory.stock-distribution.view', 'main_branch_only' => true],
            ],
        ],
        [
            'title' => 'Suppliers',
            'icon' => 'user',
            'children' => [
                ['title' => 'Supplier', 'href' => '/party/supplier', 'permission' => 'party.supplier.view'],
                ['title' => 'Supplier Payment', 'href' => '/party/supplier-payment', 'permission' => 'party.supplier-payment.view'],
            ],
        ],
        [
            'title' => 'Customers',
            'icon' => 'users-round',
            'branch_only' => true,
            'children' => [
                ['title' => 'Customer', 'href' => '/party/customer', 'permission' => 'party.customer.view'],
                ['title' => 'Due Collection', 'href' => '/party/customer-due-collection', 'permission' => 'party.customer-due-collection.view'],
                ['title' => 'Due Alert', 'href' => '/party/customer-due-alert', 'permission' => 'party.customer-due-alert.view'],
            ],
        ],
        [
            'title' => 'Branch',
            'icon' => 'building-2',
            'href' => '/branch',
            'single' => true,
            'admin_only' => true,
            'permission' => 'branch.view',
        ],
        [
            'title' => 'User',
            'icon' => 'user-cog',
            'href' => '/user',
            'single' => true,
            'admin_only' => true,
            'permission' => 'user.view',
        ],
        [
            'title' => 'Roles',
            'icon' => 'shield',
            'href' => '/role',
            'single' => true,
            'admin_only' => true,
            'permission' => 'role.view',
        ],
        [
            'title' => 'Online Orders',
            'icon' => 'package',
            'href' => '/online-order',
            'single' => true,
            'ecommerce_only' => true,
            'permission' => 'online-order.view',
        ],
        [
            'title' => 'Online Customers',
            'icon' => 'users-round',
            'href' => '/online-customer',
            'single' => true,
            'ecommerce_only' => true,
            'permission' => 'online-customer.view',
        ],
        [
            'title' => 'Contact Messages',
            'icon' => 'phone-call',
            'href' => '/contact-list',
            'single' => true,
            'ecommerce_only' => true,
            'permission' => null,
        ],
        [
            'title' => 'Subscribers',
            'icon' => 'mail',
            'href' => '/subscriber-list',
            'single' => true,
            'ecommerce_only' => true,
            'permission' => null,
        ],
        [
            'title' => 'Website Pages',
            'icon' => 'file-text',
            'ecommerce_only' => true,
            'children' => [
                ['title' => 'About Us', 'href' => '/setting/page-content/about-us', 'permission' => 'setting.page-content.view'],
                ['title' => 'FAQ', 'href' => '/setting/faq', 'permission' => 'setting.faq.view'],
                ['title' => 'Refund Policy', 'href' => '/setting/page-content/refund-policy', 'permission' => 'setting.page-content.view'],
                ['title' => 'Cancellation Policy', 'href' => '/setting/page-content/cancellation-policy', 'permission' => 'setting.page-content.view'],
                ['title' => 'Privacy Policy', 'href' => '/setting/page-content/privacy-policy', 'permission' => 'setting.page-content.view'],
                ['title' => 'Terms of Service', 'href' => '/setting/page-content/terms-policy', 'permission' => 'setting.page-content.view'],
            ],
        ],
        [
            'title' => 'Settings',
            'icon' => 'settings',
            'children' => [
                ['title' => 'Category', 'href' => '/setting/category', 'permission' => 'setting.category.view'],
                ['title' => 'Tag', 'href' => '/setting/tag', 'permission' => 'setting.tag.view'],
                ['title' => 'Brand', 'href' => '/setting/brand', 'permission' => 'setting.brand.view'],
                ['title' => 'Unit', 'href' => '/setting/unit', 'permission' => 'setting.unit.view'],
                ['title' => 'Color', 'href' => '/setting/color', 'permission' => 'setting.color.view'],
                ['title' => 'Size', 'href' => '/setting/size', 'permission' => 'setting.size.view'],
                ['title' => 'Warranty', 'href' => '/setting/warranty', 'permission' => 'setting.warranty.view'],
                ['title' => 'Product', 'href' => '/product', 'permission' => 'product.view'],
                ['title' => 'Barcode', 'href' => '/barcode', 'permission' => 'barcode.view'],
                ['title' => 'Product Section', 'href' => '/setting/productsection', 'permission' => 'setting.productsection.view'],
                ['title' => 'Slider', 'href' => '/setting/slider', 'permission' => 'setting.slider.view'],
                ['title' => 'Website Setting', 'href' => '/setting/website', 'permission' => 'setting.website.view', 'ecommerce_only' => true],
            ],
        ],
        [
            'title' => 'Accounts',
            'icon' => 'wallet',
            'children' => [
                ['title' => 'Accounts', 'href' => '/accounts', 'permission' => 'accounts.view'],
                ['title' => 'Journal Voucher', 'href' => '/accounts/vouchers?type=journal', 'permission' => 'accounts.view'],
                ['title' => 'Contra Voucher', 'href' => '/accounts/vouchers?type=contra', 'permission' => 'accounts.view'],
                ['title' => 'Income Voucher', 'href' => '/accounts/vouchers?type=income', 'permission' => 'accounts.view'],
                ['title' => 'Expense Voucher', 'href' => '/accounts/vouchers?type=expense', 'permission' => 'accounts.view'],
            ],
        ],
        [
            'title' => 'Reports',
            'icon' => 'bar-chart-2',
            'children' => [
                ['title' => 'Account Ledger', 'href' => '/report/account-ledger', 'permission' => 'report.account-ledger.view'],
                ['title' => 'A/C Transactions', 'href' => '/report/account-transactions', 'permission' => 'report.account-transactions.view'],
                ['title' => 'Balance Sheet', 'href' => '/report/balance-sheet', 'permission' => 'report.balance-sheet.view'],
                ['title' => 'Customer Ledger', 'href' => '/report/customer-ledger', 'permission' => 'report.customer-ledger.view'],
                ['title' => 'Cash Flow', 'href' => '/report/cash-flow', 'permission' => 'report.cash-flow.view'],
                ['title' => 'Cash Flow Summary', 'href' => '/report/cash-flow-summary', 'permission' => 'report.cash-flow-summary.view'],
                ['title' => 'Daily Transactions', 'href' => '/report/daily-transactions', 'permission' => 'report.daily-transactions.view'],
                ['title' => 'Date Wise Stock', 'href' => '/report/date-wise-stock', 'permission' => 'report.date-wise-stock.view'],
                ['title' => 'Stock Ledger', 'href' => '/report/stock-ledger', 'permission' => 'report.stock-ledger.view'],
                ['title' => 'Daily Summary', 'href' => '/report/daily-summary', 'permission' => 'report.daily-summary.view'],
            ],
        ],
        [
            'title' => 'Admin Profile',
            'icon' => 'shield',
            'href' => '/setting/admin-profile',
            'single' => true,
            'admin_only' => true,
            'permission' => null,
        ],
        [
            'title' => 'Branch Profile',
            'icon' => 'building-2',
            'href' => '/setting/branch-profile',
            'single' => true,
            'branch_only' => true,
            'permission' => null,
        ],
    ],

];
