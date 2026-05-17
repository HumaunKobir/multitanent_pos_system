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
            'single' => true,
        ],
        [
            'title' => 'Steadfast Courier Order',
            'icon' => 'send',
            'single' => true,
        ],
        [
            'title' => 'Contact List',
            'icon' => 'phone-call',
            'single' => true,
        ],
        [
            'title' => 'Settings',
            'icon' => 'settings',
            'children' => [
                ['title' => 'Category', 'href' => '/setting/category'],
                ['title' => 'Tag'],
                ['title' => 'Brand', 'href' => '/setting/brand'],
                ['title' => 'Unit', 'href' => '/setting/unit'],
                ['title' => 'Size', 'href' => '/setting/size'],
                ['title' => 'Tailor Measurement', 'href' => '/setting/tailormeasurement'],
                ['title' => 'Color', 'href' => '/setting/color'],
                ['title' => 'Warranty', 'href' => '/setting/warranty'],
                ['title' => 'Product'],
                ['title' => 'Barcode'],
                ['title' => 'Product Section'],
                ['title' => 'Slider'],
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
