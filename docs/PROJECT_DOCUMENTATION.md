# Aaprajita Coolness — সম্পূর্ণ Project Documentation (A-Z)

> এই ফাইলটি অন্য AI (Cursor, ChatGPT, ইত্যাদি) তে ব্যবহারের জন্য তৈরি। সমস্ত business logic, architecture, এবং flow বাংলায় এবং কোড references সহ ব্যাখ্যা করা হয়েছে।

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Tech Stack](#2-tech-stack)
3. [Directory Structure](#3-directory-structure)
4. [Database Tables (Migrations)](#4-database-tables-migrations)
5. [Models ও তাদের Relations](#5-models-ও-তাদের-relations)
6. [Routes (সব Route)](#6-routes-সব-route)
7. [Admin Menu Structure (Navigation)](#7-admin-menu-structure-navigation)
8. [Controllers — সব Controller ও তাদের Logic](#8-controllers--সব-controller-ও-তাদের-logic)
9. [Product Variant System](#9-product-variant-system)
10. [Stock / Inventory System](#10-stock--inventory-system)
11. [Accounting System](#11-accounting-system)
12. [Transaction Flow — কখন কোন Transaction হয়](#12-transaction-flow--কখন-কোন-transaction-হয়)
13. [Frontend — কোন Page এ কী Data](#13-frontend--কোন-page-এ-কী-data)
14. [Customer Authentication](#14-customer-authentication)
15. [Admin Authentication ও Permission (Laratrust)](#15-admin-authentication-ও-permission-laratrust)
16. [Branch System & Permission Matrix (Superadmin vs Branch Panel)](#16-branch-system--permission-matrix-superadmin-vs-branch-panel)
17. [GCP (Google Cloud Storage) Setup](#17-gcp-google-cloud-storage-setup)
18. [Pathao Courier Integration](#18-pathao-courier-integration)
19. [SSLCommerz Payment Gateway](#19-sslcommerz-payment-gateway)
20. [Online Order Flow (E-commerce)](#20-online-order-flow-e-commerce)
21. [Site Settings (ConfigDictionary)](#21-site-settings-configdictionary)
22. [Import System (Excel)](#22-import-system-excel)
23. [Key Enums](#23-key-enums)
24. [Traits](#24-traits)
25. [Services](#25-services)

---

## 1. Project Overview

**Aaprajita Coolness** একটি **Fashion/Clothing E-commerce + POS/ERP System**।

দুটো অংশ আছে:
1. **Frontend (Customer-facing)** — পণ্য দেখা, cart, checkout, payment, customer login/register
2. **Backend (Admin Panel)** — Purchase, Sale, Inventory, Accounting, Reports, Online Order Management

**Business Type:** পোশাক ব্যবসা (Stitch/Tailor service সহ)

---

## 2. Tech Stack

| Category | Technology |
|---|---|
| Framework | Laravel 11 (PHP 8.2) |
| Frontend UI | Blade Templates + Alpine.js + TailwindCSS |
| Admin UI | Mary UI (robsontenorio/mary v1.34) |
| Real-time | Livewire v3 |
| Authentication (Admin) | Laravel Breeze |
| Authentication (Customer) | Custom (auth:customer guard) |
| Permission/Roles | Laratrust v8.3 (santigarcor/laratrust) |
| DataTables | Yajra Laravel DataTables v11 |
| Excel Import | Maatwebsite Excel v3.1 |
| Image Storage | Google Cloud Storage (GCS) |
| Courier | Pathao Courier (codeboxr/pathao-courier) |
| Payment | SSLCommerz (dgvai/laravel-sslcommerz) |
| Toast Notification | yoeunes/toastr |
| HTTP Client | Guzzle v7 |
| API Auth | Laravel Sanctum |

**package.json dependencies:**
- AlpineJS
- TailwindCSS
- Axios
- Concurrently (dev)
- Vite (build tool)

---

## 3. Directory Structure

```
aaprajita-coolness/
├── app/
│   ├── Casts/               # Custom casts (ImageField)
│   ├── Console/             # Artisan commands
│   ├── Enum/                # Old-style enums (OrderStatus, PaymentStatus, Wallet)
│   ├── Enums/               # New PHP 8.1+ backed enums
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Account/     # AccountController, ExpenseController, IncomeController, etc.
│   │   │   ├── Admin/       # MemberShipCardController, SitesettingController
│   │   │   ├── Api/         # ProductController, CustomerController (internal API)
│   │   │   ├── Auth/        # Laravel Breeze auth
│   │   │   ├── Customer/    # CustomerController, GroupController, DueCollectionController
│   │   │   │   └── Auth/    # CustomerLoginController, CustomerRegisterController
│   │   │   ├── Inventory/   # ProductController, PurchaseController, SellController, etc.
│   │   │   ├── Report/      # (unused/old)
│   │   │   └── Reports/     # ReportController (active)
│   │   └── Requests/        # SellStoreRequest (main sell logic)
│   ├── Imports/             # Excel import classes
│   ├── Lib/
│   │   └── Resource/        # Image, Field, Column, Card (helper classes)
│   ├── Livewire/            # Livewire components
│   ├── Models/              # All Eloquent models
│   ├── Providers/           # AppServiceProvider
│   ├── Services/            # UploadService, GoogleCloudStorageService
│   └── Traits/              # HasAccount, HasBranch, ChecksPermission, etc.
├── config/
│   ├── pathao.php           # Pathao courier credentials
│   ├── sslcommerz.php       # SSLCommerz credentials
│   ├── filesystems.php      # GCS disk config
│   ├── laratrust.php        # Role/Permission config
│   └── site.php             # Site-level config (vat, inventory settings)
├── database/
│   ├── migrations/          # All DB migrations
│   └── seeders/             # Default seeders
├── resources/
│   └── views/
│       ├── frontend/        # Frontend Blade templates
│       ├── layouts/         # Admin + guest layouts
│       ├── inventory/       # Admin inventory views
│       ├── account/         # Admin account views
│       ├── report/          # Admin report views
│       ├── resource/        # Generic CRUD views (index, create, edit, show)
│       └── ...
└── routes/
    ├── web.php              # Main routes
    └── auth.php             # Breeze auth routes
```

---

## 4. Database Tables (Migrations)

### Core System Tables

| Table | Purpose |
|---|---|
| `users` | Admin/Staff users |
| `branches` | Business branches |
| `accounts` | Cash/Bank accounts |
| `roles`, `permissions`, `role_user`, `permission_role`, `permission_user` | Laratrust permission tables |
| `personal_access_tokens` | Sanctum API tokens |

### Product Tables

| Table | Purpose |
|---|---|
| `categories` | Product categories |
| `brands` | Product brands |
| `units` | Units (piece, dozen, etc.) |
| `warranties` | Warranty options |
| `colors` | Color options |
| `sizes` | Size options |
| `tailormeasurements` | Tailor measurement templates |
| `products` | Main product table |
| `product_photos` | Multiple photos per product (GCS) |
| `batches` | Stock batches (branch_id, product_id, purchase_price, available) |
| `product_in_out_logs` | Stock movement audit trail |
| `variations` | Variation types (e.g., Color, Size) |
| `variation_values` | Variation values (e.g., Red, XL) |
| `product_variations` | Product × Variation combination (with own price/stock/sku) |
| `collectioncategories` | Tags for products (collection/tag system) |

### Sales Tables

| Table | Purpose |
|---|---|
| `customers` | Customers (can also login as frontend user) |
| `groups` | Customer groups (for wholesale pricing) |
| `member_ship_cards` | Membership/loyalty cards |
| `sells` | All sales (type: sale, sale_return, exchange) |
| `sell_products` | Line items of each sale |

### Purchase Tables

| Table | Purpose |
|---|---|
| `suppliers` | Suppliers |
| `purchases` | All purchases (type: purchase, damage, purchase_return, initial_stock) |
| `purchase_products` | Line items of each purchase |

### Online Order Tables

| Table | Purpose |
|---|---|
| `online_orders` | Frontend checkout orders |
| `online_order_products` | Line items of online orders |
| `pathao_orders` | Pathao courier tracking data |

### Accounting Tables

| Table | Purpose |
|---|---|
| `transactions` | Every financial movement (polymorphic) |
| `payment_methods` | Payment methods (Cash, bKash, etc.) |
| `parties` | Third parties for income/expense |
| `chart_of_accounts` | GL account heads |
| `expenses` | Expense records |
| `head_expenses` | Expense line items |
| `incomes` | Income records |
| `head_incomes` | Income line items |
| `payment_configs` | Payment gateway configs |

### Other Tables

| Table | Purpose |
|---|---|
| `sliders` | Homepage slider images |
| `product_sections` | Homepage product sections |
| `contacts` | Website contact form submissions |
| `config_dictionaries` | Site settings (key-value) |
| `cache`, `jobs` | Laravel cache/queue |

---

## 5. Models ও তাদের Relations

### User
```
File: app/Models/User.php
Traits: HasRolesAndPermissions (Laratrust), HasApiTokens (Sanctum), DeletesImage
Fields: branch_id, name, email, phone, password, address, avatar, status
Relations:
  - belongsTo Branch (via branch_id)
Note: Admin user. branch_id = null মানে সে admin (সব branch দেখতে পারে)
```

### Branch
```
File: app/Models/Branch.php
Fields: name, address, phone, status
Relations:
  - hasMany Account
  - hasMany User
  - hasMany Customer
  - hasMany Product
```

### Account
```
File: app/Models/Account.php
Traits: HasAccount (transactions), Status
Fields: branch_id, number, name, account_info, balance, balance_in, balance_out, opening_balance, status
Relations:
  - belongsTo Branch
  - morphMany Transaction (via transactionable)
Scope: scopeMyAccounts() — branch_id filter
```

### Supplier
```
File: app/Models/Supplier.php
Traits: HasAccount, HasBranch, Status
Fields: name, phone, company_name, address, balance, balance_in, balance_out, status, branch_id
Relations:
  - morphMany Transaction (via transactionable)
Note: balance = ধার আছে কতটুকু supplier এর কাছে (negative = supplier আমাদের পাচ্ছে)
```

### Customer
```
File: app/Models/Customer.php
Traits: HasAccount, HasBranch, Status, Notifiable (Authenticatable)
Fields: group_id, name, email, phone, address, password, balance, balance_in, balance_out, status, branch_id, is_default, member_ship_id
Relations:
  - belongsTo Group
  - belongsTo Branch
  - belongsTo MemberShipCard
  - hasMany Sell
  - hasMany OnlineOrder
  - morphMany Transaction
Note: Customer হলো frontend login user। is_default = 1 মানে walk-in customer (ধার নাই)।
      password field = frontend login এর জন্য (auth:customer guard)
```

### Product
```
File: app/Models/Product.php
Traits: HasFactory, DeletesImage, Status, HasBranch
Fields:
  - vendor_id, category_id, brand_id, unit_id
  - name, slug (auto-generated), bn_name, code
  - purchase_price, sale_price, discount_price
  - wholesale_title, wholesale_price
  - type (stitch/notstitch), tailor_option (yes/no), tailor_price
  - colors (JSON array), sizes (JSON array), tailormeasurement (JSON array), tags (JSON array)
  - warranty_id, visible, availabe_area, description, bn_description
  - delivery_info, bn_delivery_info, image (GCS path), chest_size_image (GCS path)
  - status, youtube_link, branch_id
Relations:
  - hasMany ProductPhoto
  - hasMany ProductVariation
  - hasMany Batch
  - hasMany PurchaseProducts
  - hasMany SellProduct
  - hasOne Category
  - hasOne Brand
  - hasOne Unit
  - hasOne Warranty
  - belongsTo Branch
Methods:
  - productStock() — batch থেকে total available stock
  - generateUniqueSlug() — unique slug auto-generate
  - getRouteKeyName() — returns 'slug' (route model binding by slug)
Scopes:
  - scopeBranchWise() — admin দেখে সব, branch user দেখে তাদের branch এর
```

### ProductVariation
```
File: app/Models/ProductVariation.php
Traits: HasBranch
Fields: product_id, sku, sku_code, price, purchase_price, stock, variation_data (JSON), branch_id, created_by, status
Relations:
  - belongsTo Product
Note: variation_data = JSON object, e.g. {"Color": "Red", "Size": "XL"}
      stock = variant এর নিজস্ব stock (batch system ব্যবহার করে না)
      price = ওই variant এর বিক্রয় মূল্য
```

### Variation
```
File: app/Models/Variation.php
Fields: name, created_by, branch_id, status
Relations:
  - hasMany VariationValue
Note: এটা variation TYPE — যেমন "Color", "Size", "Material"
```

### VariationValue
```
File: app/Models/VariationValue.php
Fields: variation_id, value, branch_id, created_by, status
Relations:
  - belongsTo Variation
Note: এটা variation VALUE — যেমন "Red", "Blue", "XL", "Cotton"
```

### Batch
```
File: app/Models/Batch.php
Fields: branch_id, supplier_id, product_id, serial, purchase_price, expiry_date, available
Relations:
  - hasOne Product
  - hasOne Branch
Methods:
  - inStock(qty) — stock বাড়ায় + log করে
  - outStock(qty) — stock কমায় + log করে
  - saleReturnStock(qty) — sale return stock বাড়ায় + log করে
  - initialStock(qty) — initial stock + log করে
Note: Regular product stock tracking এখানে হয়।
      একই product এর একাধিক batch থাকতে পারে (ভিন্ন purchase_price বা expiry_date)
```

### ProductInOutLog
```
File: app/Models/ProductInOutLog.php
Fields: branch_id, product_id, type (enum: ProductLogType), quantity, note
Note: Batch এর প্রতিটি stock movement এখানে log হয়
```

### Purchase
```
File: app/Models/Purchase.php
Traits: HasBranch
Fields: branch_id, supplier_id, date, gross_amount, discount, vat, paid_amount, account_id, purchase_type, comment, parent_purchase_id, payment_type
Relations:
  - hasOne Branch
  - hasOne Supplier
  - belongsTo Account
  - hasMany PurchaseProducts
Scopes: scopePurchase(), scopeDamage(), scopePurchaseReturn(), scopeInitialStock()
```

### PurchaseProducts
```
File: app/Models/PurchaseProducts.php
Fields: branch_id, purchase_id, product_id, variation_id, quantity, unit_price, serial, batches (JSON — {batch_id: qty})
```

### Sell
```
File: app/Models/Sell.php
Traits: HasBranch
Fields: branch_id, customer_id, account_id, date, purchase_price, gross_amount, payable, discount, previous_due, paid_amount, vat, created_by, comment, parent_sale_id, child_id, type (SaleType), demarace, payment_type, membership_discount, change_amount
Relations:
  - hasOne Branch, Customer
  - belongsTo Account
  - hasMany SellProduct
  - belongsTo Sell as parentSale (for returns/exchange)
Scopes: scopeSale(), scopeSaleReturn(), scopeExchange()
Appends: invoice_no (e.g., "00123")
```

### SellProduct
```
File: app/Models/SellProduct.php
Fields: sell_id, branch_id, product_id, variation_id, quantity, free_quantity, unit_price, purchase_price, serial, sku, batches (JSON)
```

### OnlineOrder
```
File: app/Models/OnlineOrder.php
Fields: customer_id, name, email, phone, address, payment_method, delivery_charge, subtotal, total, payment_status, city_id, zone_id, area_id, courier, status (OrderStatus enum), tailor_price
Relations:
  - hasMany OnlineOrderProduct (as 'products')
  - belongsTo Customer
```

### OnlineOrderProduct
```
File: app/Models/OnlineOrderProduct.php
Fields: online_order_id, name, price, product_id, quantity, total_price, variation_id, sku, tailor_service, tailor_price, tailormeasurement (JSON)
```

### Transaction
```
File: app/Models/Transaction.php
Traits: HasBranch
Fields: branch_id, type (TransactionType enum), transactionable_type, transactionable_id, amount, note, reference_type, reference_id, created_at, balance
Relations:
  - morphTo transactionable (Account | Supplier | Customer)
  - morphTo reference (Purchase | Sell | Expense | Income | etc.)
Note: amount > 0 = credit (ঢুকেছে), amount < 0 = debit (বেরিয়েছে)
      balance = running balance snapshot
```

### Expense
```
File: app/Models/Expense.php
Fields: branch_id, parti_id, reference, date, account_id, note, payment_method_id, amount
Relations:
  - belongsTo Account
  - belongsTo Parties (parti)
  - hasMany HeadExpense
```

### Income
```
File: app/Models/Income.php
Fields: branch_id, parti_id, reference, date, account_id, note, payment_method_id, amount
Relations:
  - belongsTo Account
  - belongsTo Parties (parti)
  - hasMany HeadIncome
```

### ProductSection
```
File: app/Models/ProductSection.php
Fields: branch_id, name, description, button_text, images (JSON), items (JSON — product IDs), block_per_line, layout_type, block_type, status, serial
Appends: product_items (products from items JSON), product_images (image URLs from GCS)
Note: Homepage section। block_type = Item হলে product দেখায়, Image হলে banner দেখায়।
      serial দিয়ে order নির্ধারণ হয়।
```

### Slider
```
Fields: name, image (GCS path), status
Note: Homepage slider image
```

### Collectioncategory (Tag)
```
Fields: name, status, image
Note: Product এর `tags` JSON array এ এদের name store হয়।
      Frontend এ /collection/{name} route এ এই tag দিয়ে filter হয়।
```

### ConfigDictionary
```
Fields: key, value
Note: Key-value store for site settings।
Static methods: get(key), set(key, value), setMany(array)
Keys: website_name, phone, email, logo, meta_banner, fav_icon, address,
      fb_share_for_withdraw, youtube, twit, linkend, meta_tags, meta_description,
      about_us, description, faq, size_guide, refund_policy, buyback_policy,
      exchange_policy, shipping_policy, cancel_policy, privacy_policy,
      terms_of_service, topnotice1
```

### PathaoOrder
```
Fields: store_id, merchant_order_id, recipient_name, recipient_phone, recipient_address, city_id, zone_id, area_id, item_type, delivery_type, item_quantity, item_weight, item_description, amount_to_collect, consignment_id, pathao_response (JSON)
```

### MemberShipCard
```
Fields: name, discount, status
Note: Customer এর loyalty card। discount % দেওয়া হয়।
```

### Contact
```
Fields: name, email, phone, subject, message
Note: Website contact form এ submit করলে এখানে save হয়।
```

---

## 6. Routes (সব Route)

### Public Routes (Login লাগে না)

```
GET  /                          HomeController@index               (Home)
GET  /collection/{name}         HomeController@collectionProducts  (Tag-based products)
GET  /search                    HomeController@search              (Product search)
GET  /about                     HomeController@about
GET  /faq                       HomeController@faq
GET  /size-guide                HomeController@sizeguide
GET  /refund-policy             HomeController@refundpolicy
GET  /cancellation-policy       HomeController@cancellation
GET  /privacypolicy             HomeController@privacy
GET  /terms-policy              HomeController@terms
GET  /contact                   HomeController@contact
POST /contactstore              HomeController@contactstore
GET  /frontend/category/{id}/products  HomeController@products    (Category products)
GET  /frontend/section/{id}/products   HomeController@sectionProducts (Section products)
GET  /singelProduct/{slug}      HomeController@show                (Single product by slug)

GET  /cart                      Closure (session cart view)
POST /cart/add                  CartController@addToCart
POST /cart/update/{id}          CartController@update
POST /cart/remove/{id}          CartController@remove

GET  /checkout                  CheckoutController@index
POST /checkout                  CheckoutController@store

GET  /pathao/cities             PathaoCourierController@getCities
GET  /get-zones/{cityId}        PathaoCourierController@getZones
GET  /get-areas/{zoneId}        PathaoCourierController@getAreas

GET  /order                     PaymentController@order (test SSLCommerz)
POST /sslcommerz/success        PaymentController@success
POST /sslcommerz/failure        PaymentController@failure
POST /sslcommerz/cancel         PaymentController@cancel
POST /sslcommerz/ipn            PaymentController@ipn

GET  /sslcommerz/success        Closure (success page view)
```

### Customer Auth Routes (prefix: customer)

```
GET  /customer/login            CustomerLoginController@showLoginForm
POST /customer/login            CustomerLoginController@login
POST /customer/logout           CustomerLoginController@logout
GET  /customer/register         CustomerRegisterController@showRegisterForm
POST /customer/register         CustomerRegisterController@register

--- auth:customer middleware ---
GET  /customer/dashboard        CustomerDashboardController@dashboard
GET  /customer/orders           CustomerDashboardController@orders
GET  /customer/orders/{id}      CustomerDashboardController@orderDetails
```

### Admin Routes (auth middleware)

```
GET  /dashboard                 DashboardController (invokable)
GET  /profile                   ProfileController@edit
PATCH /profile                  ProfileController@update
DELETE /profile                 ProfileController@destroy

RESOURCE /branch               BranchController (full CRUD)
RESOURCE /user                 UserController (full CRUD)
DELETE /products/photos/{photo} HomeController@destroyphoto
GET  /contactlist              HomeController@contactlist
```

#### Setting Routes (prefix: setting)

```
RESOURCE /setting/category
RESOURCE /setting/productsection
POST /setting/productsection/update-order  (drag-drop order)
RESOURCE /setting/collectioncategory
RESOURCE /setting/brand
RESOURCE /setting/unit
RESOURCE /setting/color
RESOURCE /setting/size
RESOURCE /setting/tailormeasurement
RESOURCE /setting/warranty
RESOURCE /setting/product
RESOURCE /setting/barcode
RESOURCE /setting/slider
RESOURCE /setting/member-ship-card
RESOURCE /setting/sitesetting
```

#### Inventory Routes (prefix: inventory)

```
RESOURCE /inventory/purchase        (no edit/update)
RESOURCE /inventory/purchase-return (no edit/update)
RESOURCE /inventory/damage          (no edit/update)
RESOURCE /inventory/sell            (no edit/update)
GET      /inventory/pos-sale        SellController@posSale
RESOURCE /inventory/sale-return     (no edit/update)
RESOURCE /inventory/product-exchange (no edit/update)
RESOURCE /inventory/initial-stock   (no edit/update)
```

#### Account Routes (prefix: accounts)

```
RESOURCE /accounts/account
RESOURCE /accounts/chart-of-account
RESOURCE /accounts/payment-method  (no edit/update)
RESOURCE /accounts/expense          (no edit/update)
RESOURCE /accounts/income
```

#### Order Routes (prefix: order)

```
RESOURCE /order/online-order
```

#### Party Routes (prefix: party)

```
RESOURCE /party/parties
RESOURCE /party/supplier
RESOURCE /party/supplier-payment    (no edit/update)
RESOURCE /party/group
RESOURCE /party/customer
RESOURCE /party/customer-due-collection
```

#### Report Routes (prefix: report)

```
GET /report/customer-ledger
GET /report/supplier-ledger
GET /report/product-stock-summery
GET /report/customers-wise-sale
GET /report/product-ledger
GET /report/cash-flow
GET /report/daily-transactions
GET /report/date-wise-stock
GET /report/customer-due-collection
GET /report/daily-summery
GET /report/daily-cash-flow-summery
```

#### Internal API Routes (prefix: api, auth middleware)

```
GET /api/product     Api\ProductController@index
GET /api/customer    Api\CustomerController@index
GET /api/group       Api\GroupController@index
GET /api/supplier    Api\SupplierController@index
GET /api/supplier-products  SupplierProductsController (invokable)
```

#### Pathao Admin Routes

```
GET  /pathao/index              PathaoCourierController@index (admin list)
POST /pathao/store              PathaoCourierController@createStore
POST /pathao/parcel             PathaoCourierController@createParcel (direct)
GET  /pathao/stores             PathaoCourierController@getStores
GET  /pathao/order/{id}         PathaoCourierController@getOrderDetails
GET  /checkout/send-to-pathao/{invoice}  PathaoCourierController@sendPathao
```

#### Variation Routes

```
RESOURCE /variation             VariationController
GET /ajax/variations            VariationController@ajaxSearch
GET /ajax/variation-values      VariationController@getVariationValues
POST /ajax/variation-values     VariationController@storeVariationValue
```

---

## 7. Admin Menu Structure (Navigation)

`resources/views/layouts/navigation.blade.php`

```
Sidebar Menu:
├── Dashboard
├── Sales (permission: sell-read)
│   ├── Sale
│   ├── Sale Return (permission: sale-return-read)
│   └── Product Exchange (permission: product-exchange-read)
├── Purchases (permission: purchase-read)
│   ├── Purchase
│   ├── Purchase Return (permission: purchase-return-read)
│   ├── Damage (permission: damage-read)
│   └── Initial Stock
├── Party
│   ├── Supplier (permission: supplier-read)
│   ├── Supplier Payment (permission: supplier-payment-read)
│   ├── Group
│   ├── Customer
│   └── Due Collection
├── Branch (admin only)
├── User (admin only)
├── Online Order
├── Pathao Courier Order
├── Contact List
├── Settings (dropdown)
│   ├── Category
│   ├── Tag (Collection Category)
│   ├── Brand
│   ├── Unit
│   ├── Size
│   ├── Tailor Measurement
│   ├── Color
│   ├── Warranty
│   ├── Product
│   ├── Barcode
│   ├── Product Section
│   ├── Slider
│   ├── Membership
│   └── Website Setting
├── Accounts (dropdown)
│   ├── Account
│   ├── Chart of Account
│   ├── Payment Method
│   ├── Parties
│   ├── Expense
│   └── Income
├── Reports (dropdown)
│   ├── Customers Ledger
│   ├── Customers Sells
│   ├── Supplier Ledger
│   ├── Products Summary
│   ├── Products Ledger
│   ├── Cash Flow
│   ├── Cash Flow Summary
│   ├── Daily Transactions
│   ├── Date Wise Stock
│   ├── Due Collection
│   └── Daily Summary
└── Roles & Permissions Assignment (Laratrust)
```

**Permission Guard System:**
- Menu item গুলো `@can('permission-name')` দিয়ে guard করা
- Admin সব দেখতে পায়, অন্য roles শুধু তাদের permitted items দেখে

---

## 8. Controllers — সব Controller ও তাদের Logic

### HomeController
`app/Http/Controllers/HomeController.php`

| Method | Route | Logic |
|---|---|---|
| index() | GET / | Sliders, Collections, ProductSections লোড করে। সব GCS URL convert করে। |
| collectionProducts() | GET /collection/{name} | Products where tags JSON contains name. Filter by price/brand/color/size. Sort support. Paginate 12. |
| products() | GET /frontend/category/{id}/products | Category-wise products. Same filter/sort. |
| sectionProducts() | GET /frontend/section/{id}/products | Section এর items JSON থেকে product IDs নিয়ে products লোড। |
| show() | GET /singelProduct/{slug} | Single product with photos + variations |
| search() | GET /search | name বা code দিয়ে LIKE search |
| contactstore() | POST /contactstore | Contact form save to DB |
| destroyphoto() | DELETE /products/photos/{photo} | GCS থেকে photo delete + DB record delete |

### CartController
`app/Http/Controllers/CartController.php`

Cart session based (`session('cart', [])`):
- `addToCart()` — product_id, variation_id (optional), quantity, tailor_service, tailor_price, tailormeasurement নিয়ে session এ add করে
- `update()` — quantity update
- `remove()` — item remove

Cart item structure:
```json
{
  "product_id": 1,
  "name": "Product Name",
  "price": 1200,
  "quantity": 2,
  "variation_id": 5,
  "sku": "SKU-001",
  "tailor_service": true,
  "tailor_price": 500,
  "tailormeasurement": {"chest": "40", "waist": "34"}
}
```

### CheckoutController
`app/Http/Controllers/CheckoutController.php`

| Method | Logic |
|---|---|
| index() | Cart session + Pathao cities লোড |
| store() | Order create, products create, COD হলে redirect to success, Online payment হলে SSLCommerz initiate |

**Checkout Flow:**
1. Cart items থেকে subtotal calculate
2. Delivery charge (60 = Dhaka, 120 = Outside)
3. Total = subtotal + delivery_charge (tailor price separate field)
4. OnlineOrder create
5. OnlineOrderProduct গুলো create (variation_id, tailor_service সহ)
6. COD → Cart clear, success message
7. SSLCommerz/bKash → SSLCommerz::make_payment()

### PaymentController
`app/Http/Controllers/PaymentController.php`

| Method | Logic |
|---|---|
| order() | Test SSLCommerz payment initiate |
| success() | Payment validate → OnlineOrder.payment_status = 'Paid' → Redirect to success page |
| failure() | Redirect to fail page |
| cancel() | Redirect to cancel page |
| ipn() | IPN notification (instant payment notification) |

### PathaoCourierController
`app/Http/Controllers/PathaoCourierController.php`

| Method | Logic |
|---|---|
| index() | Admin: Online orders where courier='Sent To Pathao' |
| getCities() | Pathao API থেকে cities |
| getZones($cityId) | Zone by city |
| getAreas($zoneId) | Area by zone |
| sendPathao($id) | Online order → Pathao parcel create → order.courier = 'Sent To Pathao' |
| createStore() | Pathao store create |
| getStores() | Store list |
| getOrderDetails($id) | Order tracking |

### DashboardController
`app/Http/Controllers/DashboardController.php`

Invokable (`__invoke`). Role-based cards:
- **admin:** Total users, branches, customers, suppliers, online orders (pending/delivered/canceled), Today/Monthly/Total Sale/Purchase/Income/Expense, Per-branch breakdown
- **branch_admin:** Branch-specific stats
- **other:** Empty

### PurchaseController
`app/Http/Controllers/Inventory/PurchaseController.php`

| Method | Logic |
|---|---|
| index() | DataTables — purchases list |
| create() | Accounts লোড |
| store() | Purchase create + Batch update + Supplier balance + Account balance |
| show() | Purchase with products + variations |
| destroy() | Access denied (commented out restore logic) |

**store() logic detail:**
```
foreach product_id:
  1. Free quantity → createOrUpdateBatch(price=0, qty=free_qty)
  2. Paid quantity → createOrUpdateBatch(price=unit_price, qty=qty)
  3. যদি variation_id থাকে → ProductVariation.stock += total_qty
  4. gross_amount += qty * unit_price
Purchase::create(validated)
Purchase.purchaseProducts().createMany(items)
supplier.decrementBalance(SupplierPurchase, total_amount)   [supplier এর কাছে আমাদের দেনা বাড়লো]
if paid_amount > 0:
  account.decrementBalance(SupplierPayment, paid_amount)    [cash কমলো]
  supplier.incrementBalance(SupplierPayment, paid_amount)   [supplier এর কাছে দেনা কমলো]
```

### SellController
`app/Http/Controllers/Inventory/SellController.php`

| Method | Logic |
|---|---|
| index() | DataTables — sales list (type=Sale only) |
| create() | Customers + Accounts লোড |
| store() | SellStoreRequest delegate করে |
| show($id) | Sell with products + variations |
| posSale() | POS sale view |

**SellStoreRequest (core sell logic):**
`app/Http/Requests/SellStoreRequest.php`

```
init():
  - Products load (with batches, variations)
  - Stock check per item
  - যদি variation_id → variation.stock check
  - যদি regular → batch.available sum check
  - Batch deduction plan তৈরি
  - unit_price = variation.price OR (discount_price > 0 ? discount_price : sale_price)
  - gross_amount accumulate

creteSell():
  1. storeSell() → Sell::create + sellProducts().createMany
  2. deductStock() → batch.decrement OR variation.decrement
  3. completePayments():
     - account.incrementBalance(Sale, paid_amount)
     - customer.decrementBalance(CustomerSellDue, total_amount)  [customer এর কাছে পাচ্ছি]
     - customer.incrementBalance(Sale, paid_amount)              [এটুকু পেয়েছি]
```

### SaleReturnController
`app/Http/Controllers/Inventory/SaleReturnController.php`

```
store():
  1. প্রতিটি returned item এর জন্য batch restore (createOrUpdateBatch)
  2. যদি variation_id → variation.stock += return_qty
  3. Sell (type=Sale_Return) create + SellProducts create
  4. account.decrementBalance(SaleReturn, paid_amount)  [cash দিলাম customer কে]
  5. যদি payment_type = Customer_Account:
     customer.incrementBalance(SaleReturn, paid_amount) [customer এর account এ জমা]
```

### ProductExchangeController
`app/Http/Controllers/Inventory/ProductExchangeController.php`

```
store():
  1. Old product batch restore (stock ফেরত)
  2. New product batch থেকে deduct
  3. Price difference calculate (new_price - old_price) * qty
  4. Sell (type=Exchange) create
  5. account.incrementBalance(Product_Exchange, price_difference) [বেশি টাকা পেলাম]
  6. Original sale এর child_id update হয়
```

### OnlineOrderController
`app/Http/Controllers/OnlineOrderController.php`

```
update():
  যদি status = Delivered:
    1. প্রতিটি product এর stock check
    2. যদি variation → variation.stock check/deduct
    3. যদি regular → batch FIFO stock deduct (oldest batch first)
    4. batch.outStock() log করে
    যদি account_id + amount দেওয়া হয়:
      account.incrementBalance(OnlineOrderCollection, amount) [payment collected]
      order.payment_status = 'Paid'
  order.status update
```

### ExpenseController
`app/Http/Controllers/Account/ExpenseController.php`

```
store():
  1. Expense::create (+ HeadExpense line items)
  2. account.decrementBalance(Expense, total_amount) [cash কমলো]
```

### IncomeController
`app/Http/Controllers/Account/IncomeController.php`

```
store():
  1. Income::create (+ HeadIncome line items)
  2. account.incrementBalance(Income, total_amount) [cash বাড়লো]
```

### SupplierPaymentController
`app/Http/Controllers/Inventory/SupplierPaymentController.php`

```
store():
  1. account.decrementBalance(SupplierPayment, amount) [cash কমলো]
  2. supplier.incrementBalance(SupplierPayment, amount) [দেনা কমলো]
```

### DueCollectionController (Customer)
`app/Http/Controllers/Customer/DueCollectionController.php`

```
store():
  1. account.incrementBalance(CustomerDueCollection, amount) [cash বাড়লো]
  2. customer.incrementBalance(CustomerDueCollection, amount) [customer এর debt কমলো]
```

### ReportController
`app/Http/Controllers/Reports/ReportController.php`

| Method | Data Source |
|---|---|
| customerLedger() | Transaction where transactionable=Customer, date range filter |
| supplierLedger() | Transaction where transactionable=Supplier |
| productStockSummery() | ProductInOutLog grouped by product |
| customerSales() | Sell where type=Sale, date range, customer filter |
| productLedger() | ProductInOutLog with product, date range |
| cashFlow() | Transaction where transactionable=Account, date range |
| dailyTransaction() | Today's all Transactions |
| dateWiseStock() | ProductInOutLog date range + product filter |
| customerDueCollection() | Transaction type=CustomerDueCollection |
| dailySummery() | Grouped transaction totals by type |
| dailyCashFlowSummery() | In/Out totals per transaction type |

### SitesettingController
`app/Http/Controllers/Admin/SitesettingController.php`

- ConfigDictionary তে site settings save করে
- Images GCS এ upload করে

---

## 9. Product Variant System

দুটো স্তরে variant system:

### স্তর ১: Simple Attributes (Product এ সরাসরি JSON)

Product table এ:
- `colors` — JSON array: `["Red", "Blue", "Green"]`
- `sizes` — JSON array: `["S", "M", "L", "XL"]`
- `tailormeasurement` — JSON array: measurement options
- `tags` — JSON array: collection tags `["summer", "formal"]`

এগুলো শুধু display purpose। Stock tracking করে না।

### স্তর ২: Full Variant System (ProductVariation)

**Tables involved:**
```
variations (type name: "Color", "Size")
  → variation_values (values: "Red", "XL")

product_variations (actual variant combination):
  - product_id
  - variation_data: {"Color": "Red", "Size": "XL"}  ← JSON
  - sku
  - sku_code
  - price    ← এই variant এর বিক্রয় মূল্য
  - purchase_price
  - stock    ← এই variant এর নিজস্ব stock
  - branch_id
```

**Create করার সময় (ProductController@store):**
```
variations[] array পাঠানো হয়:
  variations[0][id] = variation_id (e.g., Color variation)
  variations[0][values][] = variation_value_ids

Product::create()
তারপর প্রতিটি variant combination এর জন্য:
ProductVariation::create([
  'product_id' => product->id,
  'variation_data' => {"Color": "Red", "Size": "XL"},
  'price' => variant_price,
  'purchase_price' => variant_purchase_price,
  'stock' => initial_stock,
  'sku' => generated_sku,
])
```

**Sell এর সময়:**
- `variation_id` পাঠানো হলে → ProductVariation.stock থেকে deduct
- পাঠানো না হলে → Batch system থেকে deduct

**Purchase এর সময়:**
- `variation_id` পাঠানো হলে → ProductVariation.stock += quantity
- পাঠানো না হলে → Batch system এ stock add

**Online Order Delivery এর সময় (OnlineOrderController@update):**
- variation_id থাকলে → ProductVariation.stock deduct (FIFO নয়, direct)
- না থাকলে → Batch FIFO deduct

---

## 10. Stock / Inventory System

### Regular Product Stock (Batch-based)

```
batches table:
  branch_id | product_id | purchase_price | available | expiry_date | serial

Stock movements log হয় product_in_out_logs table এ:
  branch_id | product_id | type | quantity | note
```

**Batch methods:**
- `inStock(qty, note)` — available += qty + log (type: Purchase/SaleReturn/InitialStock)
- `outStock(qty, note)` — available -= qty + log (type: Sale/Exchange/Damage)
- `saleReturnStock(qty)` — inStock alias for returns
- `initialStock(qty)` — initial stock entry

**Stock Calculation:**
```php
$product->productStock() = Batch::where('product_id', $id)->sum('available')
// Branch filter apply হয় if branch user
```

**FIFO (First In First Out) for Online Orders:**
- `Batch::sortBy('created_at')` → পুরনো batch আগে deduct

### Variant Product Stock

```
product_variations.stock — direct integer field
Purchase → stock += qty
Sale/Online Order Delivery → stock -= qty
Sale Return → stock += return_qty
```

### ProductLogType Enum
```
Purchase = 1
Sale = 5
Sale_Return = 10
Damage = 11
Purchase_Return = 12
InitialStock = 13
```

---

## 11. Accounting System

### System Type: Single-Entry Running Balance

এটা **Double-Entry Bookkeeping নয়**। প্রতিটি entity (Account, Supplier, Customer) নিজের balance maintain করে।

**HasAccount Trait:**
```php
// 3 fields on each entity:
balance      — current running balance
balance_in   — total credited (cumulative)
balance_out  — total debited (cumulative)

// Methods:
incrementBalance(type, amount, note, date, reference)  → balance বাড়ে
decrementBalance(type, amount, note, date, reference)  → balance কমে
```

**Transaction Record:**
```
প্রতিটি balance change এর সাথে একটি Transaction record create হয়:
- transactionable = কার balance বদলেছে (Account/Supplier/Customer)
- reference = কেন বদলেছে (Purchase/Sell/Expense/Income)
- amount = positive (credit) বা negative (debit)
- balance = balance এর snapshot ঐ moment এ
```

### Account (Cash/Bank) Balance Logic:
- `incrementBalance` → cash বাড়া (income, sale collection, purchase return)
- `decrementBalance` → cash কমা (expense, purchase payment, sale return refund)

### Supplier Balance Logic:
- `decrementBalance(SupplierPurchase)` → supplier এর কাছে আমাদের debt বাড়লো (negative balance = আমরা পাই)
- `incrementBalance(SupplierPayment)` → আমরা payment দিলাম, debt কমলো

**Supplier balance interpretation:**
- balance = 0 → কোনো debt নেই
- balance = -5000 → supplier 5000 টাকা পাচ্ছে আমাদের কাছ থেকে

### Customer Balance Logic:
- `decrementBalance(CustomerSellDue)` → customer এর কাছে আমরা পাচ্ছি
- `incrementBalance(Sale)` → customer payment করলো
- `incrementBalance(DueCollection)` → due collection হলো

**Customer balance interpretation:**
- balance = 0 → কোনো due নেই
- balance = -3000 → customer 3000 টাকা বাকি আছে আমাদের কাছে

### Chart of Accounts
- `chart_of_accounts` table এ GL accounts define করা
- `gl_account` field = Income বা Expense type
- Expense/Income create করার সময় ChartOfAccount থেকে head select করে line items তৈরি হয়

---

## 12. Transaction Flow — কখন কোন Transaction হয়

### Purchase হলে:
```
1. supplier.decrementBalance(SupplierPurchase, total_amount)
   → Transaction: type=101, transactionable=Supplier, amount=-total, reference=Purchase

2. যদি paid_amount > 0:
   account.decrementBalance(SupplierPayment, paid_amount)
   → Transaction: type=111, transactionable=Account, amount=-paid, reference=Purchase

   supplier.incrementBalance(SupplierPayment, paid_amount)
   → Transaction: type=111, transactionable=Supplier, amount=+paid, reference=Account
```

### Sale হলে:
```
1. account.incrementBalance(Sale, paid_amount)
   → Transaction: type=115, transactionable=Account, amount=+paid

2. customer.decrementBalance(CustomerSellDue, total_amount)
   → Transaction: type=112, transactionable=Customer, amount=-total

3. customer.incrementBalance(Sale, paid_amount)
   → Transaction: type=115, transactionable=Customer, amount=+paid
   [2 - 3 = actual due remaining]
```

### Expense হলে:
```
account.decrementBalance(Expense, total_amount)
→ Transaction: type=106, transactionable=Account, amount=-total, reference=Expense
```

### Income হলে:
```
account.incrementBalance(Income, total_amount)
→ Transaction: type=5, transactionable=Account, amount=+total, reference=Income
```

### Supplier Payment হলে:
```
account.decrementBalance(SupplierPayment, amount)
supplier.incrementBalance(SupplierPayment, amount)
```

### Sale Return হলে:
```
account.decrementBalance(SaleReturn, paid_amount)  [refund দিলাম]
if payment_type = Customer_Account:
  customer.incrementBalance(SaleReturn, paid_amount)  [customer account এ জমলো]
```

### Product Exchange হলে:
```
account.incrementBalance(Product_Exchange, price_difference)  [বেশি মূল্যের পণ্য দেওয়া হলো]
```

### Online Order Delivery হলে:
```
if account_id + amount provided:
  account.incrementBalance(OnlineOrderCollection, amount)  [cash collected]
```

### Customer Due Collection হলে:
```
account.incrementBalance(CustomerDueCollection, amount)
customer.incrementBalance(CustomerDueCollection, amount)
```

---

## 13. Frontend — কোন Page এ কী Data

### Home Page (/)
**View:** `resources/views/frontend/home.blade.php`
**Data:** Dynamic (DB থেকে)
```
- sliders: Slider::where(status=Active) → GCS URL convert করে পাঠানো হয়
- collections: Collectioncategory::where(status=Active)
- productSections: ProductSection::orderBy('serial', 'asc')
  প্রতিটি section এ:
    - block_type=Item → product_items (products with photos, variations)
    - block_type=Image → product_images (GCS image URLs)
```

### Collection Products (/collection/{name})
**View:** `resources/views/frontend/collectionProducts.blade.php`
**Data:** Dynamic
```
- products: whereJsonContains('tags', name) + filters → paginate(12)
- allBrands, allColors, allSizes: filter options
- priceRange: min/max price
- Filters: min_price, max_price, brands[], colors[], sizes[], sort_by, sort_order
- Sort: name, price_low, price_high, newest
```

### Category Products (/frontend/category/{id}/products)
**View:** `resources/views/frontend/categoryproducts.blade.php`
**Data:** Dynamic (same filters as collection)

### Section Products (/frontend/section/{id}/products)
**View:** `resources/views/frontend/sectionproducts.blade.php`
**Data:** Dynamic (section এর items JSON থেকে product IDs filter করে)

### Single Product (/singelProduct/{slug})
**View:** `resources/views/frontend/singelProduct.blade.php`
**Data:** Dynamic
```
- product: Product::where('slug', slug)->with(['photos', 'variations'])
- photos: Multiple product photos (GCS)
- variations: ProductVariation array (variation_data JSON, price, stock)
```

### Search (/search?q=term)
**View:** `resources/views/frontend/search-results.blade.php`
**Data:** Dynamic
```
- products: LIKE search by name or code → paginate(12)
```

### Cart (/cart)
**View:** `resources/views/cart/index.blade.php`
**Data:** Semi-dynamic (session based)
```
- cart: session('cart', [])
```

### Checkout (/checkout)
**View:** `resources/views/checkout/index.blade.php`
**Data:** Dynamic
```
- cart: session('cart', [])
- cities: PathaoCourier::area()->city() [live Pathao API call]
- Zone/Area: AJAX call when city/zone selected
- Payment methods: cod, sslcommerz, bkash
- Delivery: 60 (Dhaka), 120 (Outside)
```

### Static Pages
```
/about          → views/frontend/about.blade.php   (Content from ConfigDictionary)
/faq            → views/frontend/faq.blade.php
/size-guide     → views/frontend/sizeguide.blade.php
/refund-policy  → views/frontend/refundpolicy.blade.php
/cancellation-policy → views/frontend/cancellation.blade.php
/privacypolicy  → views/frontend/privacy.blade.php
/terms-policy   → views/frontend/terms.blade.php
/contact        → views/frontend/contact.blade.php (form submit → Contact model)
```

### SSLCommerz Success Page (/sslcommerz/success)
**View:** `resources/views/sslcommerz/success.blade.php`
**Data:**
```
- order: OnlineOrder with products
- order_id, transactionable_id, amount, payment_status, package
```

### Customer Dashboard (/customer/dashboard)
**View:** Customer dashboard
**Data:** Dynamic — logged-in customer এর orders

### Customer Orders (/customer/orders)
**Data:** Customer এর সব OnlineOrders

---

## 14. Customer Authentication

**Guard:** `auth:customer` (custom guard)
**Config:** `config/auth.php` এ `customers` guard define করা

```php
// Guards:
'customer' => [
    'driver' => 'session',
    'provider' => 'customers',
],

// Providers:
'customers' => [
    'driver' => 'eloquent',
    'model' => App\Models\Customer::class,
],
```

**Customer Model:** `app/Models/Customer.php` extends `Authenticatable`

**Controllers:**
- `app/Http/Controllers/Customer/Auth/CustomerLoginController.php`
- `app/Http/Controllers/Customer/Auth/CustomerRegisterController.php`
- `app/Http/Controllers/Customer/CustomerDashboardController.php`

**Registration:**
- Customer নিজে register করতে পারে → Customer model এ password field save হয়
- যদি আগে থেকে phone দিয়ে Customer exist করে → সেটাতে password set করা হয়

**Checkout:**
- Logged in customer → `auth('customer')->id()` দিয়ে customer_id online order এ save হয়
- না হলে → customer_id = null (guest checkout)

---

## 15. Admin Authentication ও Permission (Laratrust)

### Authentication
**Package:** Laravel Breeze
**Routes:** `routes/auth.php`
**Guard:** Default `auth` (web guard, User model)

### Permission System
**Package:** Laratrust v8.3
**Config:** `config/laratrust.php`

**Tables:**
- `roles` — admin, branch_admin, branch
- `permissions` — e.g., sell-read, sell-create, purchase-read, etc.
- `role_user` — user থেকে role assign
- `permission_role` — role থেকে permission assign
- `permission_user` — direct user permission (optional)

**User Model:**
```php
class User implements LaratrustUser {
    use HasRolesAndPermissions;
}
```

**Permission Check করা হয়:**
1. Blade: `@can('sell-read')`, `@cannot`, `@role('admin')`
2. Controller: `ChecksPermission` trait
3. Middleware: route middleware

**ChecksPermission Trait:**
`app/Traits/ChecksPermission.php`
```
$permissionPrefix = 'sell'  →  checks 'sell-read', 'sell-create' etc.
```

**Permission naming convention:**
```
{resource}-read    → index, show
{resource}-create  → create, store
{resource}-update  → edit, update
{resource}-delete  → destroy
```

**Roles (inferred from code):**
- `admin` → সব কিছু দেখতে পারে, সব branch এর data
- `branch_admin` → নিজের branch এর data
- `branch` → limited access

**Dashboard role check:**
```php
Auth::user()->hasRole('admin')        → admin cards
Auth::user()->hasRole('branch_admin') → branch_admin cards
```

**Roles & Permissions Assignment UI:**
Laratrust built-in UI: `/laratrust/roles-assignment`

---

## 16. Branch System & Permission Matrix (Superadmin vs Branch Panel)

### Roles — কতটি Role আছে

সিস্টেমে **4টি Laratrust Role** আছে (`config/laratrust_seeder.php`):

| Role | Description | branch_id | Default User |
|------|-------------|-----------|--------------|
| `admin` | Superadmin, সব branch দেখে | `NULL` | admin@gmail.com |
| `branch_admin` | Branch manager | branch.id set | branchadmin@gmail.com |
| `salesman` | Sales staff | branch.id set | — |
| `website` | E-commerce manager | branch.id set | website@gmail.com |

---

### Superadmin (`admin` role) — সব Menu দেখে

admin role এ সব permission থাকে। Navigation এ যা দেখা যায়:

```
Dashboard                          ← always visible (no @can)
├── Sales (@can sell-read)
│   ├── Sale
│   ├── Sale Return (@can sale-return-read)
│   └── Product Exchange (@can product-exchange-read)
├── Purchases (@can purchase-read)
│   ├── Purchase
│   ├── Purchase Return (@can purchase-return-read)
│   ├── Damage (@can damage-read)
│   └── Initial Stock (@can initial-stock-read)
├── Suppliers (@can supplier-read)
│   ├── Supplier
│   └── Supplier Payment (@can supplier-payment-read)
├── Customers (@can customer-read)
│   ├── Group (@can group-read)
│   ├── Customer
│   └── Due Collection (@can customer-due-collection-read)
├── Branch (@can branch-read)              ← ADMIN ONLY
├── User (@can user-read)                  ← ADMIN ONLY
├── Online Order (@can online-order-read)
├── Pathao (@can pathao-read)
├── Contact List (@can contactlist-read)   ← ADMIN ONLY
├── Settings ← NO top-level @can (heading সবসময় দেখা যায়)
│   ├── Category (@can category-read)
│   ├── Brand (@can brand-read)
│   ├── Unit (@can unit-read)
│   ├── Size (@can size-read)
│   ├── Color (@can color-read)
│   ├── Tailor Measurement (@can tailormeasurement-read)
│   ├── Site Settings (@can sitesetting-read)
│   ├── Collection Category (@can collectioncategory-read)
│   ├── Product Section (@can productsection-read)
│   ├── Slider (@can slider-read)
│   ├── Warranty (@can warranty-read)
│   ├── Membership Card (@can member-ship-card-read)
│   ├── Barcode (@can barcode-read)
│   └── Adjust (@can adjust-read)
├── Accounts ← NO top-level @can (heading সবসময় দেখা যায়)
│   ├── Accounts (@can account-read)
│   ├── Chart of Accounts (@can chart-of-account-read)
│   ├── Payment Methods (@can payment-method-read)
│   ├── Parties (@can parties-read)
│   ├── Expense (@can expense-read)
│   └── Income (@can income-read)
├── Reports ← NO top-level @can (heading সবসময় দেখা যায়)
│   ├── Customer Ledger (@can customer-ledger-read)
│   ├── Customer Wise Sale (@can customers-wise-sale-read)
│   ├── Supplier Ledger (@can supplier-ledger-read)
│   ├── Product Stock Summary (@can product-stock-summery-read)
│   ├── Product Ledger (@can product-ledger-read)
│   ├── Cash Flow (@can cash-flow-read)
│   ├── Daily Cash Flow Summary (@can daily-cash-flow-summery-read)
│   ├── Daily Transactions (@can daily-transactions-read)
│   ├── Date Wise Stock (@can date-wise-stock-read)
│   ├── Customer Due Collection (@can customer-due-collection-read)
│   └── Daily Summary (@can daily-summery-read)
└── Access Management (@can access-management-read)  ← ADMIN ONLY
    └── Roles & Permissions (Laratrust UI)
```

---

### Branch Admin (`branch_admin` role) — Branch-Scoped Menu

`branch_admin` এর permissions (`config/laratrust_seeder.php` থেকে):

**দেখা যায়:**
```
Dashboard
├── Sales (sell-read ✓)
│   ├── Sale
│   ├── Sale Return (sale-return-read ✓)
│   └── Product Exchange (product-exchange-read ✓)
├── Purchases (purchase-read ✓)
│   ├── Purchase
│   ├── Purchase Return (purchase-return-read ✓)
│   ├── Damage (damage-read ✓)
│   └── Initial Stock (initial-stock-read ✓)
├── Suppliers (supplier-read ✓)
│   ├── Supplier
│   └── Supplier Payment (supplier-payment-read ✓)
├── Customers (customer-read ✓)
│   ├── Group (group-read ✓)
│   ├── Customer
│   └── Due Collection (customer-due-collection-read ✓)
├── Online Order (online-order-read ✓)
├── Pathao (pathao-read ✓)
├── Settings heading (visible, no guard)
│   ├── Category ✓, Brand ✓, Unit ✓, Tailor Measurement ✓
│   ├── Product Section ✓, Barcode ✓
│   └── [Size, Color, Site Settings, Slider, Warranty, Membership Card, Adjust — HIDDEN]
├── Accounts heading (visible, no guard)
│   ├── Accounts (account-read ✓ — read only)
│   ├── Chart of Accounts ✓, Payment Methods ✓, Parties ✓
│   ├── Expense ✓, Income ✓
└── Reports heading (visible, no guard)
    ├── Customer Ledger ✓, Customer Wise Sale ✓
    ├── Supplier Ledger ✓, Product Stock Summary ✓
    ├── Product Ledger ✓, Daily Cash Flow Summary ✓
    ├── Customer Due Collection ✓, Daily Summary ✓
    └── [Cash Flow, Daily Transactions, Date Wise Stock — HIDDEN]
```

**দেখা যায় না (admin-only):**
```
Branch               ← branch-read নেই
User                 ← user-read নেই
Contact List         ← contactlist-read নেই
Access Management    ← access-management-read নেই
Settings > Site Settings, Slider, Collection Category,
          Membership Card, Warranty (admin/website role এর)
```

---

### Salesman Role — সীমিত Access

```
Dashboard
├── Purchases (purchase c,r,u — read দেখা যাবে)
├── Sales (sell c,r,u — read দেখা যাবে)
├── Due Collection (due-collection c,r)
└── Online Order (online-order r,u)
```

Reports, Accounts, Settings, Suppliers, Customers — সব hidden।

---

### Website Role — E-commerce কেন্দ্রিক

```
Dashboard
├── Settings
│   ├── Category, Brand, Unit, Tailor Measurement, Size, Color
│   ├── Site Settings, Slider, Collection Category, Product Section
│   ├── Warranty, Membership Card
│   └── Barcode
└── Accounts
    ├── Accounts, Chart of Accounts, Payment Methods
```

Sales, Purchase, Supplier, Customer, Reports — সব hidden।

---

### Branch User System — কিভাবে কাজ করে

#### users table structure
```
id | name         | email                 | branch_id | role
---|--------------|----------------------|-----------|-------------
1  | Admin        | admin@gmail.com       | NULL      | admin
2  | Website      | website@gmail.com     | 1         | website
3  | Branch Admin | branchadmin@gmail.com | 1         | branch_admin
N  | Any Staff    | ...                  | X         | salesman/branch_admin
```

- `branch_id = NULL` → **Superadmin** (সব branch এর data দেখে)
- `branch_id = X` → **Branch user** (শুধু নিজের branch দেখে)

#### User তৈরির flow
1. Admin → User → Create User
2. Branch dropdown থেকে branch select
3. User create হয়, `branch_id` set হয়
4. `/laratrust` panel থেকে role assign করা হয়

#### UserController বিশেষ নিয়ম
```php
// app/Http/Controllers/UserController.php
User::with('branch')->get()->except(1)
// User ID 1 (superadmin) listing এ দেখা যায় না — লুকানো থাকে
```

---

### Data Isolation — Branch Filtering কিভাবে হয়

#### HasBranch Trait (`app/Traits/HasBranch.php`)
```php
public function scopeOwnBranch($query)
{
    $user = Auth::user();
    if ($user->hasRole('admin')) {
        return $query;                              // admin → কোনো filter নেই, সব দেখে
    }
    return $query->where('branch_id', $user->branch_id); // branch user → শুধু নিজের branch
}
```

#### Models যেগুলোতে branch filtering কাজ করে
Sell, Purchase, Customer, Supplier, Account, Expense, Income, OnlineOrder — সব এই trait দিয়ে scope হয়।

#### scopeOwnBranch vs scopeBranchWise
| Scope | কোথায় | কাজ |
|-------|--------|-----|
| `scopeOwnBranch()` | HasBranch trait | Auth-based: admin=all, others=own branch |
| `scopeBranchWise($id)` | Product model | Explicit branch_id pass করলে filter হয় |

#### Dashboard এ branch breakdown
`DashboardController` এ admin হলে per-branch stats দেখায়, branch user হলে শুধু নিজের branch stats।

---

### Permission Control — Navigation vs Controller

#### Navigation level (`@can` directive)
Blade template এ `@can('permission-name')` দিয়ে menu item show/hide করা হয়।
`permissions_as_gates = true` থাকায় Laratrust permissions Laravel Gate এ কাজ করে।

#### Controller level (`ChecksPermission` trait)
```php
// প্রতিটি controller এ permissionPrefix define করা থাকে:
protected $permissionPrefix = 'sell';

// তারপর ChecksPermission trait check করে:
// index()  → sell-read
// create() → sell-create
// store()  → sell-create
// edit()   → sell-update
// update() → sell-update
// destroy()→ sell-delete
```

**Double-layer protection:** navigation এ দেখা না গেলেও URL সরাসরি hit করলে controller এ permission check block করে।

---

### BranchController — Delete সবসময় Block

```php
// app/Http/Controllers/BranchController.php
public function destroy(Branch $branch)
{
    return response()->error('Access Permission Denied');
}
```

Branch delete করা **সম্পূর্ণ blocked** — code এ hardcoded। কোনো role থেকেই branch delete হবে না।

---

### Access Management UI (Laratrust Panel)

- **Route:** `/laratrust` (built-in Laratrust admin panel)
- **Guard:** `@can('access-management-read')` → শুধু `admin` role দেখতে পারে
- **Features:**
  - Role create/edit/delete
  - Permission create/edit
  - User এ role assign
  - User এ direct permission assign (`assign_permissions_to_user = true` config এ)
  - Role-wise permission assign

---

### Complete Permission Name List (সব `@can` names)

```
# Sales & Inventory
sell-read, sale-return-read, product-exchange-read
purchase-read, purchase-return-read, damage-read, initial-stock-read
adjust-read

# Parties
supplier-read, supplier-payment-read
customer-read, group-read, customer-due-collection-read

# Admin-Only Menus
branch-read, user-read, contactlist-read, access-management-read

# Online Business
online-order-read, pathao-read

# Settings items
category-read, brand-read, unit-read, size-read, color-read
tailormeasurement-read, sitesetting-read, collectioncategory-read
productsection-read, slider-read, warranty-read
member-ship-card-read, barcode-read

# Accounts items
account-read, chart-of-account-read, payment-method-read
parties-read, expense-read, income-read

# Reports items
customer-ledger-read, customers-wise-sale-read, supplier-ledger-read
product-stock-summery-read, product-ledger-read, cash-flow-read
daily-cash-flow-summery-read, daily-transactions-read
date-wise-stock-read, customer-due-collection-read, daily-summery-read
```

> **Note:** Settings, Accounts, Reports এর dropdown heading এ কোনো `@can` নেই।
> Heading সবসময় দেখা যায়, ভেতরের items permission অনুযায়ী hide/show হয়।

---

## 17. GCP (Google Cloud Storage) Setup

### Package
```
composer require google/cloud-storage
```

### Config
`config/filesystems.php`:
```php
'gcs' => [
    'driver' => 'gcs',
    'project_id' => env('GOOGLE_CLOUD_PROJECT_ID', 'aaparajita'),
    'key_file' => env('GOOGLE_CLOUD_KEY_FILE', storage_path('aaparajita-20cde0222b1f.json')),
    'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET', ''),
    'path_prefix' => env('GOOGLE_CLOUD_STORAGE_PATH_PREFIX', ''),
    'storage_api_uri' => env('GOOGLE_CLOUD_STORAGE_API_URI'),
    'throw' => false,
],
```

**.env variables:**
```
FILESYSTEM_DISK=gcs
GOOGLE_CLOUD_PROJECT_ID=aaparajita
GOOGLE_CLOUD_STORAGE_BUCKET=your-bucket-name
GOOGLE_CLOUD_KEY_FILE=storage/aaparajita-20cde0222b1f.json
```

### Credentials File
`storage/aaparajita-20cde0222b1f.json` — Service Account JSON (git ignore এ আছে)

### Services

**GoogleCloudStorageService** (`app/Services/GoogleCloudStorageService.php`):
- `uploadImage($file, $directory, $filename)` → GCS upload, returns path
- `deleteImage($path)` → GCS delete
- `imageExists($path)` → check exists
- `getPublicUrl($path)` → `https://storage.googleapis.com/{bucket}/{path}`

**UploadService** (`app/Services/UploadService.php`):
- Wraps GoogleCloudStorageService
- `upload($file, $directory, $disk, $customName)` → returns `['path', 'url', 'filename', 'size', 'mime_type']`
- `delete($path, $disk)` → delete
- For `disk='gcs'` → uses GoogleCloudStorageService
- For other disks → uses Laravel Storage

**Image Lib** (`app/Lib/Resource/Image.php`):
- `Image::store($requestKey, $uploadPath, $name)` → GCS upload, returns path
- `Image::url($model, $attribute)` → GCS public URL generate
- `Image::delete($model, $attribute)` → GCS delete
- URL format: `https://storage.googleapis.com/{bucket}/{path}`

**ImageField Cast** (`app/Casts/ImageField.php`):
- Model এ `'image' => ImageField::class . ':image,images/blank.png'` cast
- Automatic URL conversion when accessing $model->image

### Upload Directories
```
products/          → Product main images
products/photos/   → Product additional photos
products/chest_sizes/ → Chest size guide images
sliders/           → Slider images
website/           → Logo, meta_banner, fav_icon
```

---

## 18. Pathao Courier Integration

### Package
```
composer require codeboxr/pathao-courier
```

### Config
`config/pathao.php`:
```php
[
    "sandbox" => env("PATHAO_SANDBOX", false),
    "client_id" => env("PATHAO_CLIENT_ID", ""),
    "client_secret" => env("PATHAO_CLIENT_SECRET", ""),
    "username" => env("PATHAO_USERNAME", ""),
    "password" => env("PATHAO_PASSWORD", ""),
    "disk" => env("PATHAO_DISK", "local")
]
```

### Workflow

**Checkout এ:**
1. `PathaoCourier::area()->city()` → City list
2. AJAX: `/get-zones/{cityId}` → Zone list
3. AJAX: `/get-areas/{zoneId}` → Area list
4. User selected city_id, zone_id, area_id online order এ save হয়

**Admin → Online Order → Send to Pathao:**
```
GET /checkout/send-to-pathao/{invoice}
→ PathaoCourierController@sendPathao()

Logic:
1. PathaoCourier::store()->list() → first store_id নেয়
2. Online Order with products load
3. PathaoCourier::order()->create([...]) → parcel create
4. OnlineOrder.courier = 'Sent To Pathao' update
```

**Parcel create payload:**
```php
[
    'store_id' => $firstStoreId,
    'merchant_order_id' => 'INV-' . $invoice->id,
    'recipient_name' => $invoice->name,
    'recipient_phone' => $invoice->phone,
    'recipient_address' => $invoice->address,
    'city_id' => $invoice->city_id,
    'zone_id' => $invoice->zone_id,
    'area_id' => $invoice->area_id,
    'item_type' => 2,           // 2 = Parcel
    'delivery_type' => '48',   // 48 hours
    'item_quantity' => total_qty,
    'item_weight' => 0.5,
    'item_description' => product names,
    'amount_to_collect' => order total,
]
```

**Admin Pathao Orders List:**
`GET /pathao/index` → Online orders where courier='Sent To Pathao'

---

## 19. SSLCommerz Payment Gateway

### Package
```
composer require dgvai/laravel-sslcommerz
```

### Config
`config/sslcommerz.php` — Store ID, Store Password, sandbox mode

### Payment Flow

**Initiate (Checkout):**
```php
$sslc = new SSLCommerz();
$sslc->amount($total)
    ->trxid($validated['phone'])
    ->product('Online Purchase')
    ->customer($name, $phone)
    ->setExtras($phone, $order->id, $order->id);
return $sslc->make_payment();
→ Redirect to SSLCommerz payment page
```

**Callback Routes:**
```
POST /sslcommerz/success  → PaymentController@success
POST /sslcommerz/failure  → PaymentController@failure
POST /sslcommerz/cancel   → PaymentController@cancel
POST /sslcommerz/ipn      → PaymentController@ipn
```

**Success Handler:**
```php
SSLCommerz::validate_payment($request)  // Validate signature
→ OnlineOrder::find(order_id).payment_status = 'Paid'
→ Redirect to /sslcommerz/success?order_id=...&amount=...
```

**Supported Payment Methods (at checkout):**
- `cod` — Cash on Delivery
- `sslcommerz` — SSLCommerz (cards, net banking, etc.)
- `bkash` — bKash (via SSLCommerz)

---

## 20. Online Order Flow (E-commerce)

### Complete Flow

```
1. Customer → Frontend product page
2. Add to cart (CartController@addToCart)
   - Session এ store: product_id, name, price, qty, variation_id, tailor_service, etc.

3. Checkout page (/checkout)
   - Pathao cities load
   - Customer info form + payment method select

4. Order Submit (CheckoutController@store)
   - Validation
   - Subtotal + delivery_charge + tailor_price calculate
   - OnlineOrder::create(customer_id, name, email, phone, address, city_id, zone_id, area_id, ...)
   - OnlineOrderProduct::create per cart item
   - Session cart clear
   - COD → success message
   - Online → SSLCommerz redirect

5. Payment callback (PaymentController@success)
   - Validate SSLCommerz signature
   - OnlineOrder.payment_status = 'Paid'
   - Redirect to success page

6. Admin → Online Order list (/order/online-order)
   - Status: pending → processing → shipped → delivered → canceled

7. Admin → Update to Delivered (OnlineOrderController@update)
   - Stock check for all items
   - Batch FIFO deduct (regular products)
   - ProductVariation.stock deduct (variant products)
   - Optional: Account receive payment → incrementBalance(OnlineOrderCollection)
   - Order.payment_status = 'Paid'

8. Admin → Send to Pathao (/checkout/send-to-pathao/{id})
   - PathaoCourier::order()->create()
   - OnlineOrder.courier = 'Sent To Pathao'
```

### Online Order Statuses
```
OrderStatus enum (app/Enum/OrderStatus.php):
  Pending
  Processing
  Shipped
  Delivered
  Canceled
```

### Payment Statuses
```
payment_status: 'pending' | 'Paid'
```

---

## 21. Site Settings (ConfigDictionary)

**Model:** `app/Models/ConfigDictionary.php`
**Table:** `config_dictionaries`

```php
// Static methods:
ConfigDictionary::get('website_name')  // key দিয়ে value পাওয়া
ConfigDictionary::set('key', 'value') // set
ConfigDictionary::setMany(['key' => 'value', ...]) // bulk set
```

**All settings keys:**
```
website_name, phone, email, address
logo, meta_banner, fav_icon (GCS paths)
fb_share_for_withdraw, youtube, twit, linkend
meta_tags, meta_description
about_us, description (MD form), faq, size_guide
refund_policy, buyback_policy, exchange_policy
shipping_policy, cancel_policy, privacy_policy, terms_of_service
topnotice1 (top banner notice text)
```

**Usage in views:**
```blade
{{ \App\Models\ConfigDictionary::get('website_name') }}
{{ \App\Models\ConfigDictionary::get('phone') }}
```

---

## 22. Import System (Excel)

**Package:** Maatwebsite Excel v3.1

**Route:** `GET /system/import`, `POST /system/supplier-import`, `POST /system/product-import`, `POST /system/customer-import`

**Controller:** `app/Http/Controllers/SystemController.php`

**Import Classes:** `app/Imports/`
- `SupplierImport` — Supplier bulk import
- `ProductImport` — Product bulk import
- `CustomerImport` — Customer bulk import

---

## 23. Key Enums

> Project এ দুটো আলাদা Enum namespace আছে:
> - `App\Enums\` → নতুন, actively used (PHP 8.1+ backed enums)
> - `App\Enum\` → mixed — কিছু active, কিছু legacy (পুরনো project থেকে আসা, বর্তমানে ব্যবহার হয় না)

---

### `App\Enums\` — সক্রিয় Enums (সব actively used)

সব `App\Enums\` enum এ `Commons` trait আছে যা দেয়:
- `getInstances()` — select dropdown এর জন্য
- `getKeys()`, `getValues()`, `asSelectArray()`
- `fromValue()`, `fromKey()`

---

#### TransactionType (int) — `App\Enums\TransactionType`
```
Opening_Balance    = 3
Income             = 5
CustomerDueCollection = 9
PurchaseReturn     = 10
SupplierPurchase   = 101
Expense            = 106
SupplierPayment    = 111
CustomerSellDue    = 112
SaleReturn         = 113
Product_Exchange   = 114
Sale               = 115
OnlineOrderCollection = 116

Helper methods:
  getIncome()  → [Income, CustomerDueCollection, PurchaseReturn]
  getExpense() → [Expense, SupplierPayment, SaleReturn]
  getInOut()   → সব income + expense types একসাথে

Used in: HasAccount trait, ReportController, DashboardController
```

#### SaleType (int) — `App\Enums\SaleType`
```
Sale        = 1   → Normal sale
Sale_Return = 5   → Sale return (parent_sale_id set থাকে)
Exchange    = 10  → Product exchange (child_id set হয় original sale এ)

Used in: Sell model (casts), SellController, SaleReturnController,
         ProductExchangeController, SellStoreRequest
```

#### PurchaseType (int) — `App\Enums\PurchaseType`
```
Purchase       = 1  → Normal purchase
Damage         = 2  → Damaged goods entry
Purchase_Return = 5 → Purchase return
InitialStock   = 6  → Opening/initial stock entry

Used in: Purchase model (casts), PurchaseController, DamageController,
         PurchaseReturnController, InitialStockController
```

#### ProductLogType (int) — `App\Enums\ProductLogType`
```
Purchase       = 1   → Stock ঢুকেছে purchase থেকে
Sale           = 5   → Stock বেরিয়েছে sale এ
Sale_Return    = 10  → Stock ফিরে এসেছে sale return এ
Damage         = 11  → Stock damage হয়েছে
Purchase_Return = 12 → Stock বেরিয়েছে purchase return এ
InitialStock   = 13  → Initial stock entry

Used in: Batch model (inStock/outStock methods), ProductInOutLog,
         ReportController@productStockSummery, dateWiseStock
```

#### CommonStatus (int) — `App\Enums\CommonStatus`
```
Pending  = 0
Active   = 1
InActive = 2

Used in: Product, Category, Brand, Account, Supplier, Customer,
         ProductVariation, Variation, VariationValue, Slider, Collectioncategory
Scope: scopeActive() via Status trait
```

#### PurchaseReceivedPayment (int) — `App\Enums\PurchaseReceivedPayment`
```
Cash             = 0  → নগদ পরিশোধ
Supplier_Account = 5  → Supplier এর account এ credit

Used in: Purchase model (payment_type field)
```

#### ReceivedPaymentMethod (int) — `App\Enums\ReceivedPaymentMethod`
```
Cash             = 0  → নগদ পেয়েছি
Customer_Account = 5  → Customer এর account এ জমা (due payment)

Used in: Sell model (payment_type field), SaleReturnController
```

#### GlaccountType (int) — `App\Enums\GlaccountType`
```
Assets      = 0
Liabilities = 1
Income      = 2
Expense     = 4

Used in: ChartOfAccount model (gl_account field)
ExpenseController → gl_account = Expense এর heads load করে
IncomeController  → gl_account = Income এর heads load করে
```

#### AccountHeadType (int) — `App\Enums\AccountHeadType`
```
Out = 0  → টাকা বের হয় (Debit)
In  = 1  → টাকা ঢোকে (Credit)

Used in: ChartOfAccount model (head_type field)
```

#### ProductType (int) — `App\Enums\ProductType`
```
RegularSale = 1  → সাধারণ বিক্রয়
WholeSale   = 2  → পাইকারি বিক্রয়

Used in: ProductController@create (product_types list)
```

#### UserStatus (int) — `App\Enums\UserStatus`
```
InActive = 0
Active   = 1

Used in: User management
```

#### ShowMenuStatus (int) — `App\Enums\ShowMenuStatus`
```
Yes = 1
No  = 2

Used in: Menu visibility control
```

---

### `App\Enum\` — Mixed Enums (কিছু active, কিছু legacy)

#### ✅ ACTIVE — বর্তমানে ব্যবহার হচ্ছে

**OrderStatus (int)** — `App\Enum\OrderStatus`
```
Pending    = 1
Processing = 2
Shipping   = 3
Delivered  = 5
Canceled   = 6

Used in: OnlineOrder model (status cast), OnlineOrderController,
         DashboardController, CustomerDashboardController
Logic:
  - Delivered হলে stock deduct হয়
  - Canceled হলে কিছু হয় না
  - Admin update করলে status change হয়
```

**PaymentStatus (int)** — `App\Enum\PaymentStatus`
```
Unpaid      = 1
Paid        = 2
PartialPaid = 3
Canceled    = 6

Used in: OnlineOrderController (imported কিন্তু string 'Paid' use করা হয়)
```

**BlockType (int)** — `App\Enum\Product\BlockType`
```
Image = 1  → Section এ image/banner দেখাবে
Item  = 2  → Section এ products দেখাবে

Used in: ProductSection model (block_type cast)
ProductSection@getProductItemsAttribute — Item type হলে products load করে
ProductSection@getProductImagesAttribute — Image type হলে image URLs দেয়
```

**LayoutType (int)** — `App\Enum\Product\LayoutType`
```
Image_Block = 1  → Image block layout
Slider      = 2  → Slider layout

Used in: ProductSection model (layout_type cast)
```

**AdjustmentType (int)** — `App\Enum\AdjustmentType`
```
Increase = 1
Decrease = 2

Used in: Stock adjustment (AdjustController — commented out in nav)
```

**CommonBoolean (string)** — `App\Enum\CommonBoolean`
```
Yes = 'yes'
No  = 'no'
```

**Wallet (int)** — `App\Enum\Wallet`
```
Withdraw = 10
Pv       = 20

Used in: PaymentController (imported)
```

---

#### ❌ LEGACY — বর্তমানে ব্যবহার হয় না (পুরনো project থেকে আসা)

এগুলো `app/Enum/` এ আছে কিন্তু বর্তমান codebase এ কোনো controller/model এ use করা হচ্ছে না।
Future কাজে লাগতে পারে বা delete করা যেতে পারে।

| Enum | Values | Note |
|---|---|---|
| `AccountType` | Asset=1, Liability=2, Equity=3, Income=4, Expenses=5 | Double-entry accounting এর জন্য |
| `AddFundStatus` | Pending=0, Approved=1, Rejected=2 | MLM/wallet fund system |
| `AdvanceType` | Given=1, Received=2 | Advance payment |
| `CashFlowType` | Operating=1, Investing=2, Financing=3 | Formal accounting |
| `PaymentMethod` | ByAdmin=1, Bkash=5, Nagad=10, Rocket=15, Cash=20 | Payment options (SSLCommerz দিয়ে handle হয়) |
| `PlacementDirection` | Left=0, Right=1 | UI layout |
| `ProductTypeStatus` | FinishedGoods=10, Raw=8, Pipeline=9 | Manufacturing |
| `PromotionType` | Asratio=1, Manual=2 | Promotion system |
| `PurchaseStatus` | Pending=1, Bill=2, Draft=3, Return=5, Canceled=6, Requisition=7 | Procurement workflow |
| `RecivedStatus` | Unreceived=1, Received=2, PartialReceived=3, Cancel=5 | Goods receipt |
| `SaleStatus` | Pending=0, Sale=1, Return=2 | Sale status (SaleType দিয়ে handle হয়) |
| `SerialStatus` | Pending=0, Sale=1, Rejected=2 | Serial number tracking |
| `TransactionType` (Enum\) | AddFund=1 | MLM/wallet (Enums\TransactionType থেকে আলাদা!) |
| `UserPackage` | None=0, Silver=5, Gold=10, Platinum=15, Diamond=20 | MLM package |
| `UserRank` | None=0 → Royal_Crown_President=50 (11 levels) | MLM rank system |
| `VoucherType` | Receipt=1, Payment=2, Journal=3, Contra=4, Expenses=5, StockAdjustment=6, Advance=7, Refund=8, Reconciliation=9 | Formal voucher system |

> **Note:** `App\Enum\TransactionType` (AddFund=1) এবং `App\Enums\TransactionType` (Opening_Balance=3, Income=5...) — দুটো সম্পূর্ণ আলাদা! Import করার সময় সতর্ক থাকতে হবে।

---

## 24. Traits

### HasAccount (`app/Traits/HasAccount.php`)
```
Used by: Account, Supplier, Customer

Methods:
- transactions() → morphMany Transaction
- incrementBalance(type, amount, note, date, reference)
- decrementBalance(type, amount, note, date, reference)
- modifyBalance() → private, updates balance/balance_in/balance_out + creates Transaction
```

### HasBranch (`app/Traits/HasBranch.php`)
```
Used by: Most models

Provides:
- scopeOwnBranch($query) → Auth::user()->branch_id filter
- scopeBranchWise() → admin bypasses, others filtered
```

### ChecksPermission (`app/Traits/ChecksPermission.php`)
```
Used by: Controllers

Requires:
- $permissionPrefix = 'sell' (define করতে হয়)

Checks: {prefix}-read, {prefix}-create, {prefix}-update, {prefix}-delete
on respective controller methods
```

### DeletesImage (`app/Traits/DeletesImage.php`)
```
Used by: User, Product

Auto-deletes image from GCS/storage on model delete
```

### Status (`app/Traits/Status.php`)
```
scopeActive() → where status = Active
```

---

## 25. Services

### GoogleCloudStorageService (`app/Services/GoogleCloudStorageService.php`)
- Direct GCS client wrapper
- `uploadImage($file, $directory, $filename)` → path
- `deleteImage($path)` → bool
- `imageExists($path)` → bool
- `getPublicUrl($path)` → `https://storage.googleapis.com/{bucket}/{path}`

### UploadService (`app/Services/UploadService.php`)
- High-level upload abstraction
- GCS বা local disk দুটোই handle করে
- Return: `['path', 'url', 'filename', 'size', 'mime_type']`

---

## Quick Reference: Key File Locations

```
Routes:                 routes/web.php
Main sell logic:        app/Http/Requests/SellStoreRequest.php
Transaction trait:      app/Traits/HasAccount.php
GCS upload:            app/Services/UploadService.php
Image helper:           app/Lib/Resource/Image.php
Navigation/Menu:        resources/views/layouts/navigation.blade.php
Site config:            app/Models/ConfigDictionary.php
Pathao config:          config/pathao.php
SSLCommerz config:      config/sslcommerz.php
GCS config:             config/filesystems.php (gcs disk)
Permission config:      config/laratrust.php
```

---

## Important Business Rules

1. **Sale এ discount থাকলে sale return করা যাবে না** (SaleReturnController@store check করে)
2. **একবার exchange হলে আর হবে না** (Sell.child_id check করে)
3. **Purchase delete blocked** (response()->error('Permission denied'))
4. **Sell delete blocked** (response()->error('Access denied'))
5. **Delivered order edit করা যাবে না** (OnlineOrderController@edit check করে)
6. **Stock কম হলে sell করা যাবে না** (SellStoreRequest@init check করে, `site.allow_negative_inventory` false হলে)
7. **Account এ balance কম হলে purchase payment দেওয়া যাবে না** (PurchaseController@store check করে)
8. **Default customer** (is_default=1) কে due sale করা যায় না/যায় (config based)
9. **Slug auto-generate** হয় product name থেকে (unique guaranteed)
10. **Admin** (branch_id=null) সব branch এর data দেখতে পারে
11. **Branch user** শুধু নিজের branch এর data দেখতে পারে

---

*এই documentation টি Aaprajita Coolness project এর সম্পূর্ণ A-Z analysis। Last updated: 2026-05-14*
