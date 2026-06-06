<?php

/*
|--------------------------------------------------------------------------
| Application Permission Definitions
|--------------------------------------------------------------------------
|
| Convention: <module>.<action>
|   - view   → list / show
|   - create → store
|   - update → update
|   - delete → destroy
|   - (any custom name) → e.g. approve, export, print
|
| To add permissions for a new route/module:
|   1. Add an entry here with any action names you need.
|   2. Run: php artisan permissions:sync
|
| To remove permissions no longer needed:
|   1. Remove the entry here.
|   2. Run: php artisan permissions:sync --cleanup
|
*/

return [
    'modules' => [

        // ── Inventory ─────────────────────────────────────────────────────────

        'product' => [
            'label' => 'Products',
            'group' => 'Settings',
            'permissions' => [
                'product.view' => 'View Products',
                'product.create' => 'Create Product',
                'product.update' => 'Update Product',
                'product.delete' => 'Delete Product',
            ],
        ],

        'barcode' => [
            'label' => 'Barcode',
            'group' => 'Inventory',
            'permissions' => [
                'barcode.view' => 'View Barcodes',
            ],
        ],

        'inventory.purchase' => [
            'label' => 'Purchases',
            'group' => 'Inventory',
            'permissions' => [
                'inventory.purchase.view' => 'View Purchases',
                'inventory.purchase.create' => 'Create Purchase',
                'inventory.purchase.update' => 'Update Purchase',
                'inventory.purchase.delete' => 'Delete Purchase',
            ],
        ],

        'inventory.purchase-return' => [
            'label' => 'Purchase Returns',
            'group' => 'Inventory',
            'permissions' => [
                'inventory.purchase-return.view' => 'View Purchase Returns',
                'inventory.purchase-return.create' => 'Create Purchase Return',
                'inventory.purchase-return.update' => 'Update Purchase Return',
                'inventory.purchase-return.delete' => 'Delete Purchase Return',
            ],
        ],

        'inventory.damage' => [
            'label' => 'Damages',
            'group' => 'Inventory',
            'permissions' => [
                'inventory.damage.view' => 'View Damages',
                'inventory.damage.create' => 'Create Damage',
                'inventory.damage.update' => 'Update Damage',
                'inventory.damage.delete' => 'Delete Damage',
            ],
        ],

        'inventory.stock-distribution' => [
            'label' => 'Stock Distribution',
            'group' => 'Inventory',
            'permissions' => [
                'inventory.stock-distribution.view' => 'View Stock Distributions',
                'inventory.stock-distribution.create' => 'Create Stock Distribution',
                'inventory.stock-distribution.update' => 'Update Stock Distribution',
                'inventory.stock-distribution.delete' => 'Delete Stock Distribution',
            ],
        ],

        // ── Sales ─────────────────────────────────────────────────────────────

        'inventory.sell' => [
            'label' => 'Sales',
            'group' => 'Sales',
            'permissions' => [
                'inventory.sell.view' => 'View Sales',
                'inventory.sell.create' => 'Create Sale',
                'inventory.sell.update' => 'Update Sale',
                'inventory.sell.delete' => 'Delete Sale',
            ],
        ],

        'inventory.sale-return' => [
            'label' => 'Sale Returns',
            'group' => 'Sales',
            'permissions' => [
                'inventory.sale-return.view' => 'View Sale Returns',
                'inventory.sale-return.create' => 'Create Sale Return',
                'inventory.sale-return.update' => 'Update Sale Return',
                'inventory.sale-return.delete' => 'Delete Sale Return',
            ],
        ],

        'inventory.product-exchange' => [
            'label' => 'Product Exchange',
            'group' => 'Sales',
            'permissions' => [
                'inventory.product-exchange.view' => 'View Product Exchanges',
                'inventory.product-exchange.create' => 'Create Product Exchange',
                'inventory.product-exchange.update' => 'Update Product Exchange',
                'inventory.product-exchange.delete' => 'Delete Product Exchange',
            ],
        ],

        // ── Parties ───────────────────────────────────────────────────────────

        'party.supplier' => [
            'label' => 'Suppliers',
            'group' => 'Parties',
            'permissions' => [
                'party.supplier.view' => 'View Suppliers',
                'party.supplier.create' => 'Create Supplier',
                'party.supplier.update' => 'Update Supplier',
                'party.supplier.delete' => 'Delete Supplier',
            ],
        ],

        'party.supplier-payment' => [
            'label' => 'Supplier Payments',
            'group' => 'Parties',
            'permissions' => [
                'party.supplier-payment.view' => 'View Supplier Payments',
                'party.supplier-payment.create' => 'Create Supplier Payment',
                'party.supplier-payment.delete' => 'Delete Supplier Payment',
            ],
        ],

        'party.customer' => [
            'label' => 'Customers',
            'group' => 'Parties',
            'permissions' => [
                'party.customer.view' => 'View Customers',
                'party.customer.create' => 'Create Customer',
                'party.customer.update' => 'Update Customer',
                'party.customer.delete' => 'Delete Customer',
            ],
        ],

        // ── Accounts ──────────────────────────────────────────────────────────

        'accounts' => [
            'label' => 'Accounts & Vouchers',
            'group' => 'Accounts',
            'permissions' => [
                'accounts.view' => 'View Accounts & Vouchers',
                'accounts.create' => 'Create Account / Voucher',
                'accounts.update' => 'Update Account / Voucher',
                'accounts.delete' => 'Delete Account / Voucher',
            ],
        ],

        // ── Settings ──────────────────────────────────────────────────────────

        'setting.category' => [
            'label' => 'Categories',
            'group' => 'Settings',
            'permissions' => [
                'setting.category.view' => 'View Categories',
                'setting.category.create' => 'Create Category',
                'setting.category.update' => 'Update Category',
                'setting.category.delete' => 'Delete Category',
            ],
        ],

        'setting.brand' => [
            'label' => 'Brands',
            'group' => 'Settings',
            'permissions' => [
                'setting.brand.view' => 'View Brands',
                'setting.brand.create' => 'Create Brand',
                'setting.brand.update' => 'Update Brand',
                'setting.brand.delete' => 'Delete Brand',
            ],
        ],

        'setting.tag' => [
            'label' => 'Tags',
            'group' => 'Settings',
            'permissions' => [
                'setting.tag.view' => 'View Tags',
                'setting.tag.create' => 'Create Tag',
                'setting.tag.update' => 'Update Tag',
                'setting.tag.delete' => 'Delete Tag',
            ],
        ],

        'setting.unit' => [
            'label' => 'Units',
            'group' => 'Settings',
            'permissions' => [
                'setting.unit.view' => 'View Units',
                'setting.unit.create' => 'Create Unit',
                'setting.unit.update' => 'Update Unit',
                'setting.unit.delete' => 'Delete Unit',
            ],
        ],

        'setting.warranty' => [
            'label' => 'Warranties',
            'group' => 'Settings',
            'permissions' => [
                'setting.warranty.view' => 'View Warranties',
                'setting.warranty.create' => 'Create Warranty',
                'setting.warranty.update' => 'Update Warranty',
                'setting.warranty.delete' => 'Delete Warranty',
            ],
        ],

        'setting.slider' => [
            'label' => 'Sliders',
            'group' => 'Settings',
            'permissions' => [
                'setting.slider.view' => 'View Sliders',
                'setting.slider.create' => 'Create Slider',
                'setting.slider.update' => 'Update Slider',
                'setting.slider.delete' => 'Delete Slider',
            ],
        ],

        'setting.productsection' => [
            'label' => 'Product Sections',
            'group' => 'Settings',
            'permissions' => [
                'setting.productsection.view' => 'View Product Sections',
                'setting.productsection.create' => 'Create Product Section',
                'setting.productsection.update' => 'Update Product Section',
                'setting.productsection.delete' => 'Delete Product Section',
            ],
        ],

        // ── Administration ────────────────────────────────────────────────────

        'branch' => [
            'label' => 'Branches',
            'group' => 'Administration',
            'permissions' => [
                'branch.view' => 'View Branches',
                'branch.create' => 'Create Branch',
                'branch.update' => 'Update Branch',
                'branch.delete' => 'Delete Branch',
            ],
        ],

        'user' => [
            'label' => 'Users',
            'group' => 'Administration',
            'permissions' => [
                'user.view' => 'View Users',
                'user.create' => 'Create User',
                'user.update' => 'Update User',
                'user.delete' => 'Delete User',
            ],
        ],

        'role' => [
            'label' => 'Roles & Permissions',
            'group' => 'Administration',
            'permissions' => [
                'role.view' => 'View Roles',
                'role.create' => 'Create Role',
                'role.update' => 'Update Role',
                'role.delete' => 'Delete Role',
            ],
        ],

        // ── Reports ───────────────────────────────────────────────────────────

        'report.customer-ledger' => [
            'label' => 'Customer Ledger',
            'group' => 'Reports',
            'permissions' => [
                'report.customer-ledger.view' => 'View Customer Ledger',
            ],
        ],

        'report.cash-flow' => [
            'label' => 'Cash Flow',
            'group' => 'Reports',
            'permissions' => [
                'report.cash-flow.view' => 'View Cash Flow',
            ],
        ],

        'report.cash-flow-summary' => [
            'label' => 'Cash Flow Summary',
            'group' => 'Reports',
            'permissions' => [
                'report.cash-flow-summary.view' => 'View Cash Flow Summary',
            ],
        ],

        'report.daily-transactions' => [
            'label' => 'Daily Transactions',
            'group' => 'Reports',
            'permissions' => [
                'report.daily-transactions.view' => 'View Daily Transactions',
            ],
        ],

        'report.date-wise-stock' => [
            'label' => 'Date Wise Stock',
            'group' => 'Reports',
            'permissions' => [
                'report.date-wise-stock.view' => 'View Date Wise Stock',
            ],
        ],

        'report.daily-summary' => [
            'label' => 'Daily Summary',
            'group' => 'Reports',
            'permissions' => [
                'report.daily-summary.view' => 'View Daily Summary',
            ],
        ],

        'report.account-ledger' => [
            'label' => 'Account Ledger',
            'group' => 'Reports',
            'permissions' => [
                'report.account-ledger.view' => 'View Account Ledger',
            ],
        ],

        'report.account-transactions' => [
            'label' => 'A/C Transactions',
            'group' => 'Reports',
            'permissions' => [
                'report.account-transactions.view' => 'View A/C Transactions',
            ],
        ],

        'report.balance-sheet' => [
            'label' => 'Balance Sheet',
            'group' => 'Reports',
            'permissions' => [
                'report.balance-sheet.view' => 'View Balance Sheet',
            ],
        ],

    ],
];
