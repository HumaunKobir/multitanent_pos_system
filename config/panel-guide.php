<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Panel guide — how each feature works (simple English)
    |--------------------------------------------------------------------------
    |
    | Keys match navigation permissions or special slugs (e.g. admin-profile).
    | Optional "admin" / "branch" keys override summary/steps for that panel.
    |
    */

    'entries' => [

        'dashboard.view' => [
            'title' => 'Dashboard',
            'summary' => 'See today\'s numbers, sales charts, and quick summaries for your work.',
            'steps' => [
                'Open Dashboard from the sidebar.',
                'Check today sales, purchases, expenses, and other cards shown for your role.',
                'Use date filters on the sell report to view a different time range.',
                'Click any sidebar link to open a module you have access to.',
            ],
            'admin' => [
                'summary' => 'See sales and expenses across all branches, branch-wise charts, and collection rates.',
                'steps' => [
                    'Open Dashboard from the sidebar.',
                    'Review today and month totals for sales and expenses.',
                    'Check the branch-wise sales chart and 30-day trend.',
                    'Use the sell report filter to change the date range.',
                    'Open the branch performance table to compare branches.',
                ],
            ],
            'branch' => [
                'summary' => 'See your branch sales, purchases, collections, and other daily numbers.',
                'steps' => [
                    'Open Dashboard from the sidebar.',
                    'Check the stat cards for today\'s sales, purchases, returns, and customers.',
                    'View the 30-day sales chart and collection rate.',
                    'Use quick action buttons to start a sale or other common tasks.',
                ],
            ],
        ],

        'inventory.sell.view' => [
            'title' => 'Sale',
            'summary' => 'Record a sale to a customer. Stock goes down and payment is saved.',
            'steps' => [
                'Go to Sales → Sale.',
                'Search and add products to the cart.',
                'Select a customer (or walk-in) and payment method.',
                'Enter paid amount and any discount.',
                'Save the invoice. Print or share if needed.',
            ],
        ],

        'inventory.sale-return.view' => [
            'title' => 'Sale Return',
            'summary' => 'Take back sold items. Stock goes up and money may be refunded.',
            'steps' => [
                'Go to Sales → Sale Return.',
                'Find the original sale or add return items manually.',
                'Enter return quantity and reason.',
                'Choose refund method if money is given back.',
                'Save the return voucher.',
            ],
        ],

        'inventory.product-exchange.view' => [
            'title' => 'Product Exchange',
            'summary' => 'Swap a sold item for another product. Old stock returns and new stock goes out.',
            'steps' => [
                'Go to Sales → Product Exchange.',
                'Select the original sale and the item to exchange.',
                'Pick the new product and any price difference.',
                'Collect or refund the difference amount.',
                'Save the exchange.',
            ],
        ],

        'inventory.stock-distribution.receive' => [
            'title' => 'Received Stock',
            'summary' => 'Accept stock sent from the main branch to your branch.',
            'steps' => [
                'Go to Sales → Received Stock.',
                'Open a pending transfer from the main branch.',
                'Check quantities and confirm receipt.',
                'Stock is added to your branch after you accept.',
            ],
        ],

        'setting.special-discount.view' => [
            'title' => 'Special Discount',
            'summary' => 'Set extra discounts on products for a limited time.',
            'steps' => [
                'Go to Sales → Special Discount.',
                'Pick products and set discount amount or percent.',
                'Set start and end dates if needed.',
                'Save. The discount applies at the POS during that period.',
            ],
        ],

        'setting.promotion.view' => [
            'title' => 'Promotions',
            'summary' => 'Create offers like buy-one-get-one or bundle deals.',
            'steps' => [
                'Go to Sales → Promotions.',
                'Create a new promotion and choose the rule type.',
                'Select products or categories included.',
                'Set dates and save. The POS applies it automatically.',
            ],
        ],

        'setting.coin-settings.view' => [
            'title' => 'Coin Settings',
            'summary' => 'Set how customers earn and use loyalty coins on purchases.',
            'steps' => [
                'Open Coin Settings from Sales.',
                'Set how many coins customers earn per amount spent.',
                'Set how many coins equal one taka discount.',
                'Save. Coins apply on future sales for registered customers.',
            ],
        ],

        'inventory.purchase.view' => [
            'title' => 'Purchase',
            'summary' => 'Record goods bought from a supplier. Stock goes up and supplier due is tracked.',
            'steps' => [
                'Go to Purchases → Purchase.',
                'Select a supplier and add products with quantity and cost.',
                'Enter paid amount and any discount.',
                'Save the purchase bill. Stock increases right away.',
            ],
        ],

        'inventory.purchase-return.view' => [
            'title' => 'Purchase Return',
            'summary' => 'Send goods back to a supplier. Stock goes down and due is reduced.',
            'steps' => [
                'Go to Purchases → Purchase Return.',
                'Pick the original purchase or add items manually.',
                'Enter return quantity and amount.',
                'Save. Stock and supplier balance update.',
            ],
        ],

        'inventory.damage.view' => [
            'title' => 'Damage',
            'summary' => 'Remove broken or lost stock from inventory.',
            'steps' => [
                'Go to Purchases → Damage.',
                'Add damaged products and quantities.',
                'Add a note explaining the damage.',
                'Save. Stock is reduced without a supplier return.',
            ],
        ],

        'inventory.stock-adjustment.view' => [
            'title' => 'Stock Adjustment',
            'summary' => 'Increase or decrease on-hand stock for corrections.',
            'steps' => [
                'Go to Purchases → Stock Adjustment.',
                'Choose Increase or Decrease.',
                'Add products and quantities, then save.',
            ],
        ],

        'inventory.stock-distribution.view' => [
            'title' => 'Distribute Stock',
            'summary' => 'Send stock from the main branch to other branches.',
            'steps' => [
                'Go to Purchases → Distribute Stock.',
                'Choose the target branch and products.',
                'Enter quantities and create the transfer.',
                'The branch receives it under Received Stock.',
            ],
            'branch' => null,
        ],

        'party.supplier.view' => [
            'title' => 'Supplier',
            'summary' => 'Manage supplier names, contact info, and opening balance.',
            'steps' => [
                'Go to Suppliers → Supplier.',
                'Add a new supplier or edit an existing one.',
                'Set opening due if they had a balance before.',
                'Use this supplier when creating purchases.',
            ],
        ],

        'party.supplier-payment.view' => [
            'title' => 'Supplier Payment',
            'summary' => 'Pay money to a supplier and reduce what you owe them.',
            'steps' => [
                'Go to Suppliers → Supplier Payment.',
                'Select the supplier and payment account.',
                'Enter the amount paid.',
                'Save. The payment is linked to their due balance.',
            ],
        ],

        'party.parties.view' => [
            'title' => 'Parties',
            'summary' => 'Manage third parties used on income and expense vouchers.',
            'steps' => [
                'Go to Parties.',
                'Add a party with name and optional phone or email.',
                'Select them in income Received By or expense Paid To.',
            ],
        ],

        'party.customer.view' => [
            'title' => 'Customer',
            'summary' => 'Manage customer records, phone numbers, and due balances.',
            'steps' => [
                'Go to Customers → Customer.',
                'Add a new customer or search existing ones.',
                'View their purchase history and due amount.',
                'Select them during a sale to track credit.',
            ],
        ],

        'party.customer-due-collection.view' => [
            'title' => 'Due Collection',
            'summary' => 'Collect money a customer owes from past credit sales.',
            'steps' => [
                'Go to Customers → Due Collection.',
                'Select the customer with due balance.',
                'Enter the amount collected and payment method.',
                'Save. Their due goes down.',
            ],
        ],

        'party.customer-due-alert.view' => [
            'title' => 'Due Alert',
            'summary' => 'See customers who have overdue payments.',
            'steps' => [
                'Go to Customers → Due Alert.',
                'Review the list of customers with due amounts.',
                'Contact them or open Due Collection to take payment.',
            ],
        ],

        'branch.view' => [
            'title' => 'Branch',
            'summary' => 'Create and manage business branches.',
            'steps' => [
                'Go to Branch in the sidebar.',
                'Add a branch with name, address, and logo.',
                'Set status to active or inactive.',
                'Assign users to branches from the User page.',
            ],
            'branch' => null,
        ],

        'user.view' => [
            'title' => 'User',
            'summary' => 'Create staff logins and assign each user to a branch and role.',
            'steps' => [
                'Go to User in the sidebar.',
                'Add a user with name, email, and password.',
                'Pick their branch and role.',
                'The role controls what they can see and do.',
            ],
            'branch' => null,
        ],

        'role.view' => [
            'title' => 'Roles',
            'summary' => 'Create roles and choose which pages each role can access.',
            'steps' => [
                'Go to Roles in the sidebar.',
                'Create a role or open an existing one.',
                'Tick the permissions for view, create, update, and delete.',
                'Assign the role to users from the User page.',
            ],
            'branch' => null,
        ],

        'online-order.view' => [
            'title' => 'Online Orders',
            'summary' => 'View and process orders placed on the website.',
            'steps' => [
                'Go to Website Manage → Online Orders.',
                'Open an order to see items and customer details.',
                'Update status as you pack, ship, or deliver.',
                'Mark payment and delivery status when done.',
            ],
        ],

        'online-customer.view' => [
            'title' => 'Online Customers',
            'summary' => 'See customers who registered on the website.',
            'steps' => [
                'Go to Website Manage → Online Customers.',
                'Search by name, phone, or email.',
                'View their order history and account details.',
            ],
        ],

        'contact-list.view' => [
            'title' => 'Contact Messages',
            'summary' => 'Read messages sent through the website contact form.',
            'steps' => [
                'Go to Website Manage → Contact Messages.',
                'Open a message to read the full text.',
                'Reply to the customer by phone or email outside the system.',
            ],
        ],

        'subscriber-list.view' => [
            'title' => 'Subscribers',
            'summary' => 'Manage newsletter email subscribers from the website.',
            'steps' => [
                'Go to Website Manage → Subscribers.',
                'View the list of email addresses.',
                'Send mail to subscribers if you have that permission.',
            ],
        ],

        'setting.slider.view' => [
            'title' => 'Slider',
            'summary' => 'Change banner images on the website home page.',
            'steps' => [
                'Go to Website Manage → Slider.',
                'Add or edit slides with image and link.',
                'Set order and save. Changes show on the storefront.',
            ],
        ],

        'setting.productsection.view' => [
            'title' => 'Product Section',
            'summary' => 'Choose which products appear in home page sections.',
            'steps' => [
                'Go to Website Manage → Product Section.',
                'Pick a section like New Arrivals or Best Sellers.',
                'Add products and set display order.',
                'Save to update the website.',
            ],
        ],

        'setting.website.view' => [
            'title' => 'Website Setting',
            'summary' => 'Set site name, logo, contact info, and delivery charges.',
            'steps' => [
                'Go to Website Manage → Website Setting.',
                'Update name, logo, phone, email, and address.',
                'Set delivery charges and social links.',
                'Save. The storefront uses these values.',
            ],
        ],

        'setting.page-content.view' => [
            'title' => 'Page Content',
            'summary' => 'Edit text on policy and info pages like About Us and Privacy.',
            'steps' => [
                'Go to Website Manage and pick the page (About Us, FAQ, Policies).',
                'Edit the content in the text editor.',
                'Save. Visitors see the updated page on the website.',
            ],
        ],

        'setting.faq.view' => [
            'title' => 'FAQ',
            'summary' => 'Add questions and answers shown on the website FAQ page.',
            'steps' => [
                'Go to Website Manage → FAQ.',
                'Add a question and answer.',
                'Set order and save.',
            ],
        ],

        'setting.category.view' => [
            'title' => 'Category',
            'summary' => 'Group products into categories for easy browsing.',
            'steps' => [
                'Go to Settings → Category.',
                'Add a category name and optional image.',
                'Assign categories when creating products.',
            ],
        ],

        'setting.tag.view' => [
            'title' => 'Tag',
            'summary' => 'Create tags to label products for filters and collections.',
            'steps' => [
                'Go to Settings → Tag.',
                'Add tag names.',
                'Attach tags to products from the Product page.',
            ],
        ],

        'setting.brand.view' => [
            'title' => 'Brand',
            'summary' => 'Manage product brands shown in filters and product details.',
            'steps' => [
                'Go to Settings → Brand.',
                'Add brand name and logo if needed.',
                'Pick the brand when adding a product.',
            ],
        ],

        'setting.unit.view' => [
            'title' => 'Unit',
            'summary' => 'Define units like piece, dozen, or meter for products.',
            'steps' => [
                'Go to Settings → Unit.',
                'Add unit names.',
                'Select the unit on each product.',
            ],
        ],

        'setting.color.view' => [
            'title' => 'Color',
            'summary' => 'Manage color options for product variants.',
            'steps' => [
                'Go to Settings → Color.',
                'Add color names.',
                'Use them when a product has color variants.',
            ],
        ],

        'setting.size.view' => [
            'title' => 'Size',
            'summary' => 'Manage size options like S, M, L, XL for clothing.',
            'steps' => [
                'Go to Settings → Size.',
                'Add size names.',
                'Use them when a product has size variants.',
            ],
        ],

        'setting.warranty.view' => [
            'title' => 'Warranty',
            'summary' => 'Set warranty periods shown on products and invoices.',
            'steps' => [
                'Go to Settings → Warranty.',
                'Add warranty names and duration.',
                'Pick warranty on products that need it.',
            ],
        ],

        'setting.pos-terms.view' => [
            'title' => 'POS Terms & Conditions',
            'summary' => 'Set terms printed on sale invoices from your branch.',
            'steps' => [
                'Go to Sales → POS Terms & Conditions.',
                'Type the terms customers see on receipts.',
                'Save. New sales show these terms on print.',
            ],
        ],

        'product.view' => [
            'title' => 'Product',
            'summary' => 'Add and edit products with price, stock, images, and variants.',
            'steps' => [
                'Go to Settings → Product.',
                'Create a product with name, category, brand, and price.',
                'Add variants for color/size if needed.',
                'Upload images and set stock. Save when done.',
            ],
        ],

        'barcode.view' => [
            'title' => 'Barcode',
            'summary' => 'Print barcode labels for products.',
            'steps' => [
                'Go to Settings → Barcode.',
                'Search products and select items to print.',
                'Choose label size and quantity.',
                'Print and stick labels on products.',
            ],
        ],

        'accounts.view' => [
            'title' => 'Accounts',
            'summary' => 'Manage cash and bank accounts and record money movements.',
            'steps' => [
                'Go to Accounts → Accounts to see all accounts.',
                'Create cash or bank accounts with opening balance.',
                'Use Journal, Contra, Income, or Expense vouchers to move money.',
                'Check balances after each entry.',
            ],
        ],

        'report.account-ledger.view' => [
            'title' => 'Account Ledger',
            'summary' => 'See all debit and credit entries for one account.',
            'steps' => [
                'Go to Reports → Account Ledger.',
                'Pick an account and date range.',
                'Run the report to see opening, entries, and closing balance.',
            ],
        ],

        'report.account-transactions.view' => [
            'title' => 'A/C Transactions',
            'summary' => 'List all account transactions in a date range.',
            'steps' => [
                'Go to Reports → A/C Transactions.',
                'Set from and to dates.',
                'Review debits, credits, and voucher references.',
            ],
        ],

        'report.balance-sheet.view' => [
            'title' => 'Balance Sheet',
            'summary' => 'See total assets, liabilities, and equity on a date.',
            'steps' => [
                'Go to Reports → Balance Sheet.',
                'Pick the report date.',
                'Review assets on one side and liabilities plus equity on the other.',
            ],
        ],

        'report.trial-balance.view' => [
            'title' => 'Trial Balance',
            'summary' => 'Check debit and credit balances account by account.',
            'steps' => [
                'Go to Reports → Trial Balance.',
                'Pick the as-of date and branch if needed.',
                'Review debit and credit totals to confirm the books balance.',
            ],
        ],

        'report.profit-loss.view' => [
            'title' => 'Profit & Loss',
            'summary' => 'Review income, expenses, and net profit for a period.',
            'steps' => [
                'Go to Reports → Profit & Loss.',
                'Pick the date range and branch if needed.',
                'Compare total income with total expenses to see net profit or loss.',
            ],
        ],

        'report.sales-profit-trend.view' => [
            'title' => 'Sales Profit Trend',
            'summary' => 'See sales and profit trends with product, brand, and category charts.',
            'steps' => [
                'Go to Reports → Sales Profit Trend.',
                'Choose product, brand, or category grouping and a date range.',
                'Review the trend chart, top comparison bars, and the breakdown table.',
            ],
        ],

        'report.customer-ledger.view' => [
            'title' => 'Customer Ledger',
            'summary' => 'See sales, payments, and due for one customer.',
            'steps' => [
                'Go to Reports → Customer Ledger.',
                'Select customer and date range.',
                'View each sale, return, and collection with running balance.',
            ],
        ],

        'report.purchase-report.view' => [
            'title' => 'Purchase Report',
            'summary' => 'See purchases by supplier and date with totals and discounts.',
            'steps' => [
                'Go to Reports → Purchase Report.',
                'Filter by supplier (or All) and date range.',
                'Review invoice rows, overall totals, and supplier-wise totals.',
            ],
        ],

        'report.cash-flow.view' => [
            'title' => 'Cash Flow',
            'summary' => 'Track money in and out of accounts over time.',
            'steps' => [
                'Go to Reports → Cash Flow.',
                'Set the date range.',
                'See income, expenses, and net cash movement.',
            ],
        ],

        'report.cash-flow-summary.view' => [
            'title' => 'Cash Flow Summary',
            'summary' => 'A short summary of cash received and paid.',
            'steps' => [
                'Go to Reports → Cash Flow Summary.',
                'Pick dates and run.',
                'Use for a quick cash overview.',
            ],
        ],

        'report.daily-transactions.view' => [
            'title' => 'Daily Transactions',
            'summary' => 'See all sales, purchases, and vouchers for one day.',
            'steps' => [
                'Go to Reports → Daily Transactions.',
                'Pick a date.',
                'Review each transaction type for that day.',
            ],
        ],

        'report.date-wise-stock.view' => [
            'title' => 'Date Wise Stock',
            'summary' => 'See stock levels as they were on a past date.',
            'steps' => [
                'Go to Reports → Date Wise Stock.',
                'Pick a date and optional product filter.',
                'View quantity on hand for that date.',
            ],
        ],

        'report.stock-ledger.view' => [
            'title' => 'Stock Ledger',
            'summary' => 'See every stock in and out for a product.',
            'steps' => [
                'Go to Reports → Stock Ledger.',
                'Select product and date range.',
                'View purchases, sales, returns, damage, and stock adjustment movements.',
            ],
        ],

        'report.inventory-stock.view' => [
            'title' => 'Inventory Stock',
            'summary' => 'Current stock list for all products in your scope.',
            'steps' => [
                'Go to Reports → Inventory Stock.',
                'Filter by category, brand, size, or search by name.',
                'See quantity, cost value, selling value, and expected profit on hand now.',
            ],
        ],

        'report.stock-valuation.view' => [
            'title' => 'Stock Valuation',
            'summary' => 'Value current inventory at cost and selling price.',
            'steps' => [
                'Go to Reports → Stock Valuation.',
                'Filter by product search, brand, category, size, or branch.',
                'Review qty, unit cost, cost value, selling value, and expected profit.',
            ],
        ],

        'report.stock-aging.view' => [
            'title' => 'Stock Aging',
            'summary' => 'See how long current stock has been sitting by age bucket.',
            'steps' => [
                'Go to Reports → Stock Aging.',
                'Filter by product search, brand, category, size, or branch.',
                'Review quantities in 0–30, 31–60, 61–90, and 90+ day buckets.',
            ],
        ],

        'report.opening-stock.view' => [
            'title' => 'Opening Stock',
            'summary' => 'List products that were opened with initial stock.',
            'steps' => [
                'Go to Reports → Opening Stock.',
                'Review opening qty, cost, and supplier paid amount.',
                'To add opening stock, use Settings → Product (Initial Stock field).',
            ],
        ],

        'report.daily-summary.view' => [
            'title' => 'Daily Summary',
            'summary' => 'One-page summary of sales, purchases, and collections for a day.',
            'steps' => [
                'Go to Reports → Daily Summary.',
                'Pick a date.',
                'Review totals for sales, returns, expenses, and collections.',
            ],
        ],

        'setting.branch-profile.view' => [
            'title' => 'Branch Profile',
            'summary' => 'Update your branch name, logo, address, and contact details.',
            'steps' => [
                'Go to Branch Profile in the sidebar.',
                'Edit name, logo, phone, and address.',
                'Save. Your branch details update on invoices and the panel.',
            ],
            'admin' => null,
        ],

        'admin-profile' => [
            'title' => 'Admin Profile',
            'summary' => 'Update main company profile and global settings.',
            'steps' => [
                'Go to Admin Profile in the sidebar.',
                'Edit company name, logo, and contact info.',
                'Save changes for the whole system.',
            ],
            'branch' => null,
        ],

    ],

];
