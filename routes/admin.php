<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\BusinessSessionController;
use App\Http\Controllers\Account\VoucherController;
use App\Http\Controllers\Api\CustomerDueSalesController;
use App\Http\Controllers\Api\CustomerSearchController;
use App\Http\Controllers\Api\ProductCatalogOptionsController;
use App\Http\Controllers\Api\ProductSearchController;
use App\Http\Controllers\Api\PurchaseLookupController;
use App\Http\Controllers\Api\SaleLookupController;
use App\Http\Controllers\Api\SupplierController as SupplierApiController;
use App\Http\Controllers\Api\SupplierDuePurchasesController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ContactListController;
use App\Http\Controllers\Customer\CustomerController;
use App\Http\Controllers\Inventory\CustomerDueAlertController;
use App\Http\Controllers\Inventory\CustomerDueCollectionController;
use App\Http\Controllers\Inventory\DamageController;
use App\Http\Controllers\Inventory\ProductExchangeController;
use App\Http\Controllers\Inventory\PurchaseController;
use App\Http\Controllers\Inventory\PurchaseReturnController;
use App\Http\Controllers\Inventory\SaleReturnController;
use App\Http\Controllers\Inventory\SellController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockDistributionController;
use App\Http\Controllers\Inventory\SupplierController;
use App\Http\Controllers\Inventory\SupplierPaymentController;
use App\Http\Controllers\OnlineCustomerController;
use App\Http\Controllers\OnlineOrderController;
use App\Http\Controllers\PanelGuideController;
use App\Http\Controllers\Party\PartyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Reports\InventoryStockController;
use App\Http\Controllers\Reports\OpeningStockController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\StockAgingController;
use App\Http\Controllers\Reports\StockValuationController;
use App\Http\Controllers\Reports\SubscriptionBillingReportController;
use App\Http\Controllers\BranchPanel\BranchPanelSubscriptionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Setting\AdminProfileController;
use App\Http\Controllers\Setting\BranchClientController;
use App\Http\Controllers\Setting\BranchProfileController;
use App\Http\Controllers\Setting\BrandController;
use App\Http\Controllers\Setting\BusinessSetupController;
use App\Http\Controllers\Setting\CategoryController;
use App\Http\Controllers\Setting\CoinSettingsController;
use App\Http\Controllers\Setting\ColorController;
use App\Http\Controllers\Setting\FaqController;
use App\Http\Controllers\Setting\PageContentController;
use App\Http\Controllers\Setting\PosTermsController;
use App\Http\Controllers\Setting\ProductSectionController;
use App\Http\Controllers\Setting\PromotionController;
use App\Http\Controllers\Setting\SizeController;
use App\Http\Controllers\Setting\SliderController;
use App\Http\Controllers\Setting\SpecialDiscountController;
use App\Http\Controllers\Setting\TagController;
use App\Http\Controllers\Setting\UnitController;
use App\Http\Controllers\Setting\WarrantyController;
use App\Http\Controllers\Setting\WebsiteSettingController;
use App\Http\Controllers\SubscriberListController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VariationController;
use App\Support\PageContent;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->get('/admin', function () {
    if (auth()->user()?->usesBranchPanel()) {
        return redirect()->route('branch-panel.dashboard');
    }

    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->get('panel-guide', PanelGuideController::class)->name('panel-guide');

Route::middleware(['auth', 'verified', 'superadmin'])->group(function () {
    Route::resource('branch', BranchController::class)->except(['create', 'edit', 'show']);
    Route::get('branch-clients', [BranchClientController::class, 'index'])->name('branch-clients.index');
    Route::put('branch-clients/{branch}', [BranchClientController::class, 'update'])->name('branch-clients.update');
    Route::post('branch-clients/{branch}/renew', [BranchClientController::class, 'renew'])->name('branch-clients.renew');
    Route::get('branch-clients/{branch}/payments', [BranchClientController::class, 'payments'])->name('branch-clients.payments');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('user', UserController::class)->except(['create', 'edit', 'show']);
    Route::resource('role', RoleController::class)->except(['show']);
    Route::get('role/{role}/permissions', [RoleController::class, 'editPermissions'])->name('role.permissions');
    Route::put('role/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('role.permissions.update');
});

Route::middleware(['auth', 'verified', 'ecommerce.panel'])->group(function () {
    Route::get('contact-list', [ContactListController::class, 'index'])->name('contact-list.index');
    Route::delete('contact-list/{contact}', [ContactListController::class, 'destroy'])->name('contact-list.destroy');
    Route::get('subscriber-list', [SubscriberListController::class, 'index'])->name('subscriber-list.index');
    Route::post('subscriber-list/mail/bulk', [SubscriberListController::class, 'sendBulkMail'])->name('subscriber-list.send-bulk-mail');
    Route::post('subscriber-list/{subscriber}/mail', [SubscriberListController::class, 'sendMail'])->name('subscriber-list.send-mail');
    Route::delete('subscriber-list/{subscriber}', [SubscriberListController::class, 'destroy'])->name('subscriber-list.destroy');
    Route::get('setting/website', [WebsiteSettingController::class, 'edit'])->name('setting.website.edit');
    Route::put('setting/website', [WebsiteSettingController::class, 'update'])->name('setting.website.update');
    Route::get('setting/website/preview/email/order', [WebsiteSettingController::class, 'previewOrderEmail'])->name('setting.website.preview-email.order');
    Route::get('setting/website/preview/email/payment', [WebsiteSettingController::class, 'previewPaymentEmail'])->name('setting.website.preview-email.payment');
    Route::get('setting/website/preview/invoice', [WebsiteSettingController::class, 'previewInvoice'])->name('setting.website.preview-invoice');
    Route::get('setting/page-content/{page}', [PageContentController::class, 'edit'])
        ->whereIn('page', PageContent::slugs())
        ->name('setting.page-content.edit');
    Route::put('setting/page-content/{page}', [PageContentController::class, 'update'])
        ->whereIn('page', PageContent::slugs())
        ->name('setting.page-content.update');
    Route::prefix('setting')->name('setting.')->group(function () {
        Route::post('faq/update-order', [FaqController::class, 'updateOrder'])
            ->name('faq.update-order');
        Route::resource('faq', FaqController::class)
            ->except(['create', 'edit', 'show'])
            ->parameters(['faq' => 'faq']);
    });
    Route::get('online-customer', [OnlineCustomerController::class, 'index'])->name('online-customer.index');
    Route::get('online-customer/{customer}', [OnlineCustomerController::class, 'show'])->name('online-customer.show');
    Route::get('online-order', [OnlineOrderController::class, 'index'])->name('online-order.index');
    Route::get('online-order/{onlineOrder}', [OnlineOrderController::class, 'show'])->name('online-order.show');
    Route::post('online-order/{onlineOrder}/steadfast', [OnlineOrderController::class, 'sendToSteadfast'])->name('online-order.send-steadfast');
    Route::patch('online-order/{onlineOrder}/steadfast/sync', [OnlineOrderController::class, 'syncCourierStatus'])->name('online-order.sync-steadfast');
    Route::patch('online-order/{onlineOrder}/fulfill', [OnlineOrderController::class, 'fulfill'])->name('online-order.fulfill');
    Route::patch('online-order/{onlineOrder}/status', [OnlineOrderController::class, 'updateStatus'])->name('online-order.update-status');
    Route::get('online-order/{onlineOrder}/invoice', [OnlineOrderController::class, 'downloadInvoice'])->name('online-order.invoice');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('product/{product}/receive', [ProductController::class, 'receive'])->name('product.receive');
    Route::get('product/export/excel', [ProductController::class, 'exportExcel'])->name('product.export-excel');
    Route::get('product/export/pdf', [ProductController::class, 'exportPdf'])->name('product.export-pdf');
    Route::get('product/export/print', [ProductController::class, 'exportPrint'])->name('product.export-print');
    Route::delete('product-photo/{productPhoto}', [ProductController::class, 'destroyPhoto'])->name('product.photo.destroy');
    Route::resource('product', ProductController::class)->except(['show']);
    Route::get('barcode', [BarcodeController::class, 'index'])->name('barcode.index');
    Route::get('barcode/serial-range', [BarcodeController::class, 'serialRange'])->name('barcode.serial-range');
    Route::get('barcode/print', [BarcodeController::class, 'print'])->name('barcode.print');
    Route::post('variation', [VariationController::class, 'store'])->name('variation.store');
    Route::delete('variation/{variation}', [VariationController::class, 'destroy'])->name('variation.destroy');
});

Route::middleware(['auth', 'verified'])->prefix('inventory')->name('inventory.')->group(function () {
    Route::redirect('stock', '/report/inventory-stock');
    Route::get('purchase/export/excel', [PurchaseController::class, 'exportExcel'])->name('purchase.export-excel');
    Route::get('purchase/export/pdf', [PurchaseController::class, 'exportPdf'])->name('purchase.export-pdf');
    Route::get('purchase/export/print', [PurchaseController::class, 'exportPrint'])->name('purchase.export-print');
    Route::resource('purchase', PurchaseController::class);
    Route::get('purchase-return/export/excel', [PurchaseReturnController::class, 'exportExcel'])->name('purchase-return.export-excel');
    Route::get('purchase-return/export/pdf', [PurchaseReturnController::class, 'exportPdf'])->name('purchase-return.export-pdf');
    Route::get('purchase-return/export/print', [PurchaseReturnController::class, 'exportPrint'])->name('purchase-return.export-print');
    Route::resource('purchase-return', PurchaseReturnController::class);
    Route::post('purchase-return/{purchase_return}/receive-payment', [PurchaseReturnController::class, 'receivePayment'])->name('purchase-return.receive-payment');
    Route::get('damage/export/excel', [DamageController::class, 'exportExcel'])->name('damage.export-excel');
    Route::get('damage/export/pdf', [DamageController::class, 'exportPdf'])->name('damage.export-pdf');
    Route::get('damage/export/print', [DamageController::class, 'exportPrint'])->name('damage.export-print');
    Route::resource('damage', DamageController::class);
    Route::get('stock-adjustment/export/excel', [StockAdjustmentController::class, 'exportExcel'])->name('stock-adjustment.export-excel');
    Route::get('stock-adjustment/export/pdf', [StockAdjustmentController::class, 'exportPdf'])->name('stock-adjustment.export-pdf');
    Route::get('stock-adjustment/export/print', [StockAdjustmentController::class, 'exportPrint'])->name('stock-adjustment.export-print');
    Route::resource('stock-adjustment', StockAdjustmentController::class)->except(['edit', 'update']);
    Route::post('sell/pause', [SellController::class, 'pause'])->name('sell.pause');
    Route::resource('sell', SellController::class);
    Route::resource('sale-return', SaleReturnController::class);
    Route::put('sale-return/{sale_return}/refund', [SaleReturnController::class, 'settleRefund'])->name('sale-return.refund');
    Route::put('product-exchange/{product_exchange}/payment', [ProductExchangeController::class, 'settlePayment'])->name('product-exchange.payment');
    Route::resource('product-exchange', ProductExchangeController::class);
    Route::get('stock-distribution/received', [StockDistributionController::class, 'receivedIndex'])->name('stock-distribution.received');
    Route::get('stock-distribution/export/excel', [StockDistributionController::class, 'exportExcel'])->name('stock-distribution.export-excel');
    Route::get('stock-distribution/export/pdf', [StockDistributionController::class, 'exportPdf'])->name('stock-distribution.export-pdf');
    Route::get('stock-distribution/export/print', [StockDistributionController::class, 'exportPrint'])->name('stock-distribution.export-print');
    Route::post('stock-distribution/{stock_distribution}/receive', [StockDistributionController::class, 'receive'])->name('stock-distribution.receive');
    Route::post('stock-distribution/{stock_distribution}/send-return', [StockDistributionController::class, 'sendReturn'])->name('stock-distribution.send-return');
    Route::post('stock-distribution/{stock_distribution}/receive-return', [StockDistributionController::class, 'receiveReturn'])->name('stock-distribution.receive-return');
    Route::resource('stock-distribution', StockDistributionController::class);
});

Route::middleware(['auth', 'verified'])->prefix('party')->name('party.')->group(function () {
    Route::resource('parties', PartyController::class)->except(['create', 'edit', 'show']);
    Route::get('supplier/export/excel', [SupplierController::class, 'exportExcel'])->name('supplier.export-excel');
    Route::get('supplier/export/pdf', [SupplierController::class, 'exportPdf'])->name('supplier.export-pdf');
    Route::get('supplier/export/print', [SupplierController::class, 'exportPrint'])->name('supplier.export-print');
    Route::resource('supplier', SupplierController::class)->except(['create', 'edit', 'show']);
    Route::get('supplier-payment/export/excel', [SupplierPaymentController::class, 'exportExcel'])->name('supplier-payment.export-excel');
    Route::get('supplier-payment/export/pdf', [SupplierPaymentController::class, 'exportPdf'])->name('supplier-payment.export-pdf');
    Route::get('supplier-payment/export/print', [SupplierPaymentController::class, 'exportPrint'])->name('supplier-payment.export-print');
    Route::resource('supplier-payment', SupplierPaymentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('customer-due-collection', CustomerDueCollectionController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['customer-due-collection' => 'customerPayment']);
    Route::resource('customer-due-alert', CustomerDueAlertController::class)->except(['create', 'edit', 'show']);
    Route::get('customer/report/export', [CustomerController::class, 'bulkReport'])->name('customer.bulk-report');
    Route::get('customer/{customer}/report', [CustomerController::class, 'report'])->name('customer.report');
    Route::resource('customer', CustomerController::class)->except(['create', 'edit', 'show']);
});

Route::middleware(['auth', 'verified'])->prefix('api')->name('api.')->group(function () {
    Route::get('products/catalog-options', ProductCatalogOptionsController::class)->name('products.catalog-options');
    Route::get('suppliers/{supplier}/due-purchases', SupplierDuePurchasesController::class)->name('suppliers.due-purchases');
    Route::get('suppliers', [SupplierApiController::class, 'index'])->name('suppliers');
    Route::post('suppliers', [SupplierApiController::class, 'store'])->name('suppliers.store');
    Route::get('products/for-purchase', [ProductSearchController::class, 'forPurchase'])->name('products.purchase');
    Route::get('products/for-sell', [ProductSearchController::class, 'forSell'])->name('products.sell');
    Route::get('products/for-distribution', [ProductSearchController::class, 'forDistribution'])->name('products.distribution');
    Route::get('products/for-stock-adjustment', [ProductSearchController::class, 'forStockAdjustment'])->name('products.stock-adjustment');
    Route::get('purchases/lookup', PurchaseLookupController::class)->name('purchases.lookup');
    Route::get('sales/lookup', SaleLookupController::class)->name('sales.lookup');
    Route::get('customers', [CustomerSearchController::class, 'index'])->name('customers');
    Route::post('customers', [CustomerSearchController::class, 'store'])->name('customers.store');
    Route::get('customers/{customer}/due-sales', CustomerDueSalesController::class)->name('customers.due-sales');
    Route::get('customers/{customer}/due-alert', [CustomerSearchController::class, 'dueAlert'])->name('customers.due-alert');
    Route::get('customers/{customer}/coins', [CustomerSearchController::class, 'coins'])->name('customers.coins');
});

Route::middleware(['auth', 'verified'])->prefix('accounts')->name('accounts.')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('index');
    Route::post('/', [AccountController::class, 'store'])->name('store');
    Route::get('next-code', [AccountController::class, 'nextCode'])->name('next-code');
    Route::patch('{chartOfAccount}', [AccountController::class, 'update'])->name('update');
    Route::delete('{chartOfAccount}', [AccountController::class, 'destroy'])->name('destroy');
    Route::get('vouchers/next-number', [VoucherController::class, 'nextNumber'])->name('vouchers.next-number');
    Route::get('vouchers/{voucher}', [VoucherController::class, 'show'])->name('vouchers.show');
    Route::get('vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
    Route::post('vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
    Route::put('vouchers/{voucher}', [VoucherController::class, 'update'])->name('vouchers.update');
    Route::delete('vouchers/{voucher}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');
    Route::redirect('journal-voucher', '/accounts/vouchers?type=journal')->name('journal-voucher.index');
    Route::redirect('contra-voucher', '/accounts/vouchers?type=contra')->name('contra-voucher.index');
    Route::redirect('income-voucher', '/accounts/vouchers?type=income')->name('income-voucher.index');
    Route::redirect('expense-voucher', '/accounts/vouchers?type=expense')->name('expense-voucher.index');

    Route::get('daily-sessions', [BusinessSessionController::class, 'index'])->name('daily-sessions.index');
    Route::post('daily-sessions', [BusinessSessionController::class, 'store'])->name('daily-sessions.store');
    Route::get('daily-sessions/close', [BusinessSessionController::class, 'closePreview'])->name('daily-sessions.close-preview');
    Route::post('daily-sessions/close', [BusinessSessionController::class, 'closeConfirm'])->name('daily-sessions.close-confirm');
    Route::post('daily-sessions/close/cancel', [BusinessSessionController::class, 'closeCancel'])->name('daily-sessions.close-cancel');
    Route::get('daily-sessions/{dailySession}/report', [BusinessSessionController::class, 'report'])->name('daily-sessions.report');
    Route::get('daily-sessions/{dailySession}/export/excel', [BusinessSessionController::class, 'exportExcel'])->name('daily-sessions.export-excel');
    Route::post('daily-sessions/{dailySession}/reopen', [BusinessSessionController::class, 'reopen'])->name('daily-sessions.reopen');
});

Route::middleware(['auth', 'verified'])->prefix('report')->name('report.')->group(function () {
    Route::get('customer-ledger', [ReportController::class, 'customerLedger'])->name('customer-ledger');
    Route::get('cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
    Route::get('cash-flow-summary', [ReportController::class, 'cashFlowSummary'])->name('cash-flow-summary');
    Route::get('daily-transactions', [ReportController::class, 'dailyTransactions'])->name('daily-transactions');
    Route::get('date-wise-stock', [ReportController::class, 'dateWiseStock'])->name('date-wise-stock');
    Route::get('stock-ledger', [ReportController::class, 'stockLedger'])->name('stock-ledger');
    Route::get('inventory-stock/export/excel', [InventoryStockController::class, 'exportExcel'])->name('inventory-stock.export-excel');
    Route::get('inventory-stock/export/pdf', [InventoryStockController::class, 'exportPdf'])->name('inventory-stock.export-pdf');
    Route::get('inventory-stock/export/print', [InventoryStockController::class, 'exportPrint'])->name('inventory-stock.export-print');
    Route::get('inventory-stock', InventoryStockController::class)->name('inventory-stock');
    Route::get('stock-valuation', StockValuationController::class)->name('stock-valuation');
    Route::get('stock-aging', StockAgingController::class)->name('stock-aging');
    Route::get('opening-stock', OpeningStockController::class)->name('opening-stock');
    Route::get('daily-summary', [ReportController::class, 'dailySummary'])->name('daily-summary');
    Route::get('sales-summary', [ReportController::class, 'salesSummary'])->name('sales-summary');
    Route::get('products/search', [ReportController::class, 'searchProducts'])->name('products.search');
    Route::get('account-ledger', [ReportController::class, 'accountLedger'])->name('account-ledger');
    Route::get('account-transactions', [ReportController::class, 'accountTransactions'])->name('account-transactions');
    Route::get('balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
    Route::get('trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
    Route::get('profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
    Route::get('sales-report/export/excel', [ReportController::class, 'salesReportExportExcel'])->name('sales-report.export-excel');
    Route::get('sales-report/export/pdf', [ReportController::class, 'salesReportExportPdf'])->name('sales-report.export-pdf');
    Route::get('sales-report/export/csv', [ReportController::class, 'salesReportExportCsv'])->name('sales-report.export-csv');
    Route::get('sales-report', [ReportController::class, 'salesReport'])->name('sales-report');
    Route::get('sales-profit-trend', [ReportController::class, 'salesProfitTrend'])->name('sales-profit-trend');
    Route::get('purchase-report', [ReportController::class, 'purchaseReport'])->name('purchase-report');
    Route::get('subscription-billing/export/excel', [SubscriptionBillingReportController::class, 'exportExcel'])->name('subscription-billing.export-excel');
    Route::get('subscription-billing/export/csv', [SubscriptionBillingReportController::class, 'exportCsv'])->name('subscription-billing.export-csv');
    Route::get('subscription-billing/export/pdf', [SubscriptionBillingReportController::class, 'exportPdf'])->name('subscription-billing.export-pdf');
    Route::get('subscription-billing/export/print', [SubscriptionBillingReportController::class, 'exportPrint'])->name('subscription-billing.export-print');
    Route::get('subscription-billing', [SubscriptionBillingReportController::class, 'index'])->name('subscription-billing');
});

Route::middleware(['auth', 'verified'])->prefix('setting')->name('setting.')->group(function () {
    Route::get('category/export/excel', [CategoryController::class, 'exportExcel'])->name('category.export-excel');
    Route::get('category/export/pdf', [CategoryController::class, 'exportPdf'])->name('category.export-pdf');
    Route::get('category/export/print', [CategoryController::class, 'exportPrint'])->name('category.export-print');
    Route::resource('category', CategoryController::class)
        ->except(['create', 'edit'])
        ->parameters(['category' => 'category:id']);
    Route::resource('tag', TagController::class)->except(['create', 'edit']);
    Route::get('brand/export/excel', [BrandController::class, 'exportExcel'])->name('brand.export-excel');
    Route::get('brand/export/pdf', [BrandController::class, 'exportPdf'])->name('brand.export-pdf');
    Route::get('brand/export/print', [BrandController::class, 'exportPrint'])->name('brand.export-print');
    Route::resource('brand', BrandController::class)
        ->except(['create', 'edit'])
        ->parameters(['brand' => 'brand:id']);
    Route::resource('unit', UnitController::class)->except(['create', 'edit']);
    Route::get('color/export/excel', [ColorController::class, 'exportExcel'])->name('color.export-excel');
    Route::get('color/export/pdf', [ColorController::class, 'exportPdf'])->name('color.export-pdf');
    Route::get('color/export/print', [ColorController::class, 'exportPrint'])->name('color.export-print');
    Route::resource('color', ColorController::class)->except(['create', 'edit']);
    Route::get('size/export/excel', [SizeController::class, 'exportExcel'])->name('size.export-excel');
    Route::get('size/export/pdf', [SizeController::class, 'exportPdf'])->name('size.export-pdf');
    Route::get('size/export/print', [SizeController::class, 'exportPrint'])->name('size.export-print');
    Route::resource('size', SizeController::class)->except(['create', 'edit']);
    Route::resource('warranty', WarrantyController::class)->except(['create', 'edit']);
    Route::resource('slider', SliderController::class)->except(['create', 'edit', 'show']);
    Route::resource('productsection', ProductSectionController::class)->except(['create', 'edit', 'show']);
    Route::post('productsection/update-order', [ProductSectionController::class, 'updateOrder'])
        ->name('productsection.update-order');
    Route::resource('special-discount', SpecialDiscountController::class)
        ->except(['create', 'edit', 'show'])
        ->parameters(['special-discount' => 'specialDiscount']);
    Route::resource('promotion', PromotionController::class)
        ->except(['create', 'edit', 'show'])
        ->parameters(['promotion' => 'promotion']);
    Route::get('admin-profile', [AdminProfileController::class, 'edit'])->name('admin-profile.edit');
    Route::put('admin-profile', [AdminProfileController::class, 'update'])->name('admin-profile.update');
    Route::get('branch-profile', [BranchProfileController::class, 'edit'])->name('branch-profile.edit');
    Route::put('branch-profile', [BranchProfileController::class, 'update'])->name('branch-profile.update');
    Route::get('pos-terms', [PosTermsController::class, 'edit'])->name('pos-terms.edit');
    Route::put('pos-terms', [PosTermsController::class, 'update'])->name('pos-terms.update');
    Route::get('coin-settings', [CoinSettingsController::class, 'index'])->name('coin-settings.index');
    Route::get('coin-settings/create', [CoinSettingsController::class, 'create'])->name('coin-settings.create');
    Route::post('coin-settings', [CoinSettingsController::class, 'store'])->name('coin-settings.store');
    Route::get('coin-settings/edit', [CoinSettingsController::class, 'edit'])->name('coin-settings.edit');
    Route::put('coin-settings', [CoinSettingsController::class, 'update'])->name('coin-settings.update');
    Route::get('business-setup', [BusinessSetupController::class, 'edit'])->name('business-setup.edit');
    Route::put('business-setup', [BusinessSetupController::class, 'updateSettings'])->name('business-setup.update');
    Route::put('business-setup/branch/{branch}', [BusinessSetupController::class, 'updateBranchSubscription'])->name('business-setup.branch.update');
    Route::post('business-setup/branch/{branch}/renew', [BusinessSetupController::class, 'renewBranchSubscription'])->name('business-setup.branch.renew');
    Route::get('business-setup/branch/{branch}/payments', [BusinessSetupController::class, 'branchPaymentHistory'])->name('business-setup.branch.payments');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('branch-panel/subscription', [BranchPanelSubscriptionController::class, 'index'])->name('branch-panel.subscription.index');
    Route::post('branch-panel/subscription/pay', [BranchPanelSubscriptionController::class, 'submitPayment'])->name('branch-panel.subscription.pay');
});
