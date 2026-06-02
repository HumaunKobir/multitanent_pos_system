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
    |
    */

    'sections' => [
        [
            'title' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'href' => '/admin',
            'single' => true,
        ],
        [
            'title' => 'Sales',
            'icon' => 'circle-dollar-sign',
            'branch_only' => true,
            'children' => [
                ['title' => 'Sale', 'href' => '/inventory/sell'],
                ['title' => 'Sale Return', 'href' => '/inventory/sale-return'],
                ['title' => 'Product Exchange', 'href' => '/inventory/product-exchange'],
            ],
        ],
        [
            'title' => 'Purchases',
            'icon' => 'hand-coins',
            'children' => [
                ['title' => 'Purchase', 'href' => '/inventory/purchase'],
                ['title' => 'Purchase Return', 'href' => '/inventory/purchase-return'],
                ['title' => 'Damage', 'href' => '/inventory/damage'],
                ['title' => 'Initial Stock', 'href' => '/inventory/initial-stock'],
            ],
        ],
        [
            'title' => 'Suppliers',
            'icon' => 'user',
            'children' => [
                ['title' => 'Supplier', 'href' => '/party/supplier'],
                ['title' => 'Supplier Payment', 'href' => '/party/supplier-payment'],
            ],
        ],
        [
            'title' => 'Customers',
            'icon' => 'users-round',
            'branch_only' => true,
            'children' => [
                ['title' => 'Customer', 'href' => '/party/customer'],
                ['title' => 'Due Collection', 'href' => '/party/customer-due-collection'],
            ],
        ],
        [
            'title' => 'Branch',
            'icon' => 'building-2',
            'href' => '/branch',
            'single' => true,
            'admin_only' => true,
        ],
        [
            'title' => 'User',
            'icon' => 'user-cog',
            'href' => '/user',
            'single' => true,
            'admin_only' => true,
        ],
        [
            'title' => 'Contact List',
            'icon' => 'phone-call',
            'single' => true,
            'admin_only' => true,
        ],
        [
            'title' => 'Settings',
            'icon' => 'settings',
            'children' => [
                ['title' => 'Category', 'href' => '/setting/category'],
                ['title' => 'Tag', 'href' => '/setting/tag'],
                ['title' => 'Brand', 'href' => '/setting/brand'],
                ['title' => 'Unit', 'href' => '/setting/unit'],
                ['title' => 'Warranty', 'href' => '/setting/warranty'],
                ['title' => 'Product', 'href' => '/product'],
                ['title' => 'Barcode'],
                ['title' => 'Product Section', 'href' => '/setting/productsection'],
                ['title' => 'Slider', 'href' => '/setting/slider'],
                ['title' => 'Membership'],
                ['title' => 'Website Setting'],
            ],
        ],
        [
            'title' => 'Accounts',
            'icon' => 'wallet',
            'children' => [
                ['title' => 'Account'],
                ['title' => 'Chart of Account'],
                ['title' => 'Payment Method'],
            ],
        ],
        [
            'title' => 'Reports',
            'icon' => 'bar-chart-2',
            'children' => [
                ['title' => 'Customers Ledger'],
                ['title' => 'Cash Flow'],
                ['title' => 'Cash Flow Summary'],
                ['title' => 'Daily Transactions'],
                ['title' => 'Date Wise Stock'],
                ['title' => 'Due Collection'],
                ['title' => 'Daily Summary'],
            ],
        ],
    ],

];
