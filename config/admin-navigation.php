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
    |
    */

    'sections' => [
        [
            'title' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'href' => '/dashboard',
            'single' => true,
        ],
        [
            'title' => 'Branch',
            'icon' => 'building-2',
            'href' => '/branch',
            'single' => true,
        ],
        [
            'title' => 'User',
            'icon' => 'user-cog',
            'href' => '/user',
            'single' => true,
        ],
        [
            'title' => 'Online Order',
            'icon' => 'globe',
            'href' => '/order/online-order',
            'single' => true,
        ],
        [
            'title' => 'Steadfast Courier Order',
            'icon' => 'send',
            'href' => '/steadfast/index',
            'single' => true,
        ],
        [
            'title' => 'Contact List',
            'icon' => 'phone-call',
            'href' => '/contactlist',
            'single' => true,
        ],
        [
            'title' => 'Settings',
            'icon' => 'settings',
            'children' => [
                ['title' => 'Category', 'href' => '/setting/category'],
                ['title' => 'Tag', 'href' => '/setting/collectioncategory'],
                ['title' => 'Brand', 'href' => '/setting/brand'],
                ['title' => 'Unit', 'href' => '/setting/unit'],
                ['title' => 'Size', 'href' => '/setting/size'],
                ['title' => 'Tailor Measurement', 'href' => '/setting/tailormeasurement'],
                ['title' => 'Color', 'href' => '/setting/color'],
                ['title' => 'Warranty', 'href' => '/setting/warranty'],
                ['title' => 'Product', 'href' => '/setting/product'],
                ['title' => 'Barcode', 'href' => '/setting/barcode'],
                ['title' => 'Product Section', 'href' => '/setting/productsection'],
                ['title' => 'Slider', 'href' => '/setting/slider'],
                ['title' => 'Membership', 'href' => '/setting/member-ship-card'],
                ['title' => 'Website Setting', 'href' => '/setting/sitesetting'],
            ],
        ],
        [
            'title' => 'Accounts',
            'icon' => 'wallet',
            'children' => [
                ['title' => 'Account', 'href' => '/accounts/account'],
                ['title' => 'Chart of Account', 'href' => '/accounts/chart-of-account'],
                ['title' => 'Payment Method', 'href' => '/accounts/payment-method'],
            ],
        ],
        [
            'title' => 'Reports',
            'icon' => 'bar-chart-2',
            'children' => [
                ['title' => 'Customers Ledger', 'href' => '/report/customer-ledger'],
                ['title' => 'Cash Flow', 'href' => '/report/cash-flow'],
                ['title' => 'Cash Flow Summary', 'href' => '/report/daily-cash-flow-summery'],
                ['title' => 'Daily Transactions', 'href' => '/report/daily-transactions'],
                ['title' => 'Date Wise Stock', 'href' => '/report/date-wise-stock'],
                ['title' => 'Due Collection', 'href' => '/report/customer-due-collection'],
                ['title' => 'Daily Summary', 'href' => '/report/daily-summery'],
            ],
        ],
    ],

];
