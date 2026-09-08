<?php

namespace App\Enums;

enum SystemAccountKey: string
{
    case CashAndBank = 'cash_and_bank';
    case CashInHand = 'cash_in_hand';
    case BankAccount = 'bank_account';
    case SslCommerz = 'sslcommerz';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Inventory = 'inventory';
    case ProductInventory = 'product_inventory';
    case BranchInventory = 'branch_inventory';
    case AccountsReceivable = 'accounts_receivable';
    case CustomerReceivables = 'customer_receivables';
    case IntercompanyReceivable = 'intercompany_receivable';
    case AccountsPayable = 'accounts_payable';
    case SupplierPayables = 'supplier_payables';
    case IntercompanyPayable = 'intercompany_payable';
    case LoansPayable = 'loans_payable';
    case AdvanceFromCustomer = 'advance_from_customer';
    case CustomerCoinPayable = 'customer_coin_payable';
    case TaxesPayable = 'taxes_payable';
    case OutputVat = 'output_vat';
    case OwnersCapital = 'owners_capital';
    case RetainedEarnings = 'retained_earnings';
    case CurrentYearEarnings = 'current_year_earnings';
    case OwnersDrawings = 'owners_drawings';
    case OpeningBalanceEquity = 'opening_balance_equity';
    case OpeningBalanceClearing = 'opening_balance_clearing';
    case SalesRevenue = 'sales_revenue';
    case ProductSales = 'product_sales';
    case SalesReturns = 'sales_returns';
    case OtherIncome = 'other_income';
    case Expenses = 'expenses';
    case CostOfGoodsSold = 'cost_of_goods_sold';
    case InventoryDamage = 'inventory_damage';
    case StockAdjustmentGain = 'stock_adjustment_gain';
    case StockAdjustmentLoss = 'stock_adjustment_loss';
    case PurchaseReturns = 'purchase_returns';
    case RentExpense = 'rent_expense';
    case SalaryExpense = 'salary_expense';
    case UtilitiesExpense = 'utilities_expense';
    case DiscountApplied = 'discount_applied';
    case CoinDiscountApplied = 'coin_discount_applied';
    case TaxesPaid = 'taxes_paid';
    case SubscriptionPayable = 'subscription_payable';
    case SubscriptionExpense = 'subscription_expense';
    case SubscriptionIncome = 'subscription_income';

    public function accountNumber(): string
    {
        return 'SYS:'.$this->value;
    }

    public function defaultName(): string
    {
        return match ($this) {
            self::CashAndBank => 'Cash & Bank',
            self::CashInHand => 'Cash in Hand',
            self::BankAccount => 'Bank Account',
            self::SslCommerz => 'SSLCommerz',
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
            self::Inventory => 'Inventory',
            self::ProductInventory => 'Product Inventory',
            self::BranchInventory => 'Branch Inventory',
            self::AccountsReceivable => 'Accounts Receivable',
            self::CustomerReceivables => 'Customer Receivables',
            self::IntercompanyReceivable => 'Intercompany Receivable',
            self::AccountsPayable => 'Accounts Payable',
            self::SupplierPayables => 'Supplier Payables',
            self::IntercompanyPayable => 'Intercompany Payable',
            self::SubscriptionPayable => 'Subscription Payable',
            self::LoansPayable => 'Loans Payable',
            self::AdvanceFromCustomer => 'Advance from Customer',
            self::CustomerCoinPayable => 'Customer Coin Payable',
            self::TaxesPayable => 'Taxes Payable',
            self::OutputVat => 'VAT Payable',
            self::OwnersCapital => "Owner's Capital",
            self::RetainedEarnings => 'Retained Earnings',
            self::CurrentYearEarnings => 'Current Year Earnings',
            self::OwnersDrawings => "Owner's Drawings",
            self::OpeningBalanceEquity => 'Opening Balance Equity',
            self::OpeningBalanceClearing => 'Opening Balance Clearing',
            self::SalesRevenue => 'Sales Revenue',
            self::ProductSales => 'Product Sales',
            self::SalesReturns => 'Sales Returns',
            self::SubscriptionIncome => 'Subscription Income',
            self::OtherIncome => 'Other Income',
            self::Expenses => 'Expenses',
            self::CostOfGoodsSold => 'Cost of Goods Sold',
            self::InventoryDamage => 'Inventory Damage / Write-off',
            self::StockAdjustmentGain => 'Stock Adjustment Gain',
            self::StockAdjustmentLoss => 'Stock Adjustment Loss',
            self::PurchaseReturns => 'Purchase Returns',
            self::RentExpense => 'Rent',
            self::SalaryExpense => 'Salary',
            self::UtilitiesExpense => 'Utilities',
            self::SubscriptionExpense => 'Subscription Expense',
            self::DiscountApplied => 'Discount Applied',
            self::CoinDiscountApplied => 'Coin Discount Applied',
            self::TaxesPaid => 'Taxes Paid',
        };
    }

    public function accountType(): AccountType
    {
        return match ($this) {
            self::CashAndBank, self::CashInHand, self::BankAccount, self::SslCommerz, self::Bkash, self::Nagad,
            self::Inventory, self::ProductInventory, self::BranchInventory,
            self::AccountsReceivable, self::CustomerReceivables, self::IntercompanyReceivable => AccountType::Asset,
            self::AccountsPayable, self::SupplierPayables, self::IntercompanyPayable, self::SubscriptionPayable,
            self::LoansPayable, self::AdvanceFromCustomer, self::CustomerCoinPayable, self::TaxesPayable,
            self::OutputVat, self::TaxesPaid => AccountType::Liability,
            self::OwnersCapital, self::RetainedEarnings, self::CurrentYearEarnings, self::OwnersDrawings,
            self::OpeningBalanceEquity, self::OpeningBalanceClearing => AccountType::Equity,
            self::SalesRevenue, self::ProductSales, self::SalesReturns, self::SubscriptionIncome,
            self::OtherIncome, self::StockAdjustmentGain, self::DiscountApplied, self::CoinDiscountApplied => AccountType::Income,
            self::Expenses, self::CostOfGoodsSold, self::InventoryDamage, self::StockAdjustmentLoss, self::PurchaseReturns,
            self::RentExpense, self::SalaryExpense, self::UtilitiesExpense, self::SubscriptionExpense => AccountType::Expenses,
        };
    }

    public function isHead(): bool
    {
        return match ($this) {
            self::CashAndBank, self::Inventory, self::AccountsReceivable, self::AccountsPayable,
            self::TaxesPayable, self::OpeningBalanceEquity, self::SalesRevenue, self::Expenses => true,
            default => false,
        };
    }

    public function parentKey(): ?SystemAccountKey
    {
        return match ($this) {
            self::CashInHand, self::SslCommerz => self::CashAndBank,
            self::Bkash, self::Nagad => self::CashAndBank,
            self::BankAccount => self::CashAndBank,
            self::ProductInventory => self::Inventory,
            self::BranchInventory => self::Inventory,
            self::CustomerReceivables, self::IntercompanyReceivable => self::AccountsReceivable,
            self::SupplierPayables, self::IntercompanyPayable, self::SubscriptionPayable => self::AccountsPayable,
            self::OutputVat, self::TaxesPaid => self::TaxesPayable,
            self::OpeningBalanceClearing => self::OpeningBalanceEquity,
            self::ProductSales, self::SalesReturns, self::SubscriptionIncome, self::DiscountApplied, self::CoinDiscountApplied => self::SalesRevenue,
            self::StockAdjustmentGain => self::OtherIncome,
            self::CostOfGoodsSold, self::InventoryDamage, self::StockAdjustmentLoss, self::PurchaseReturns,
            self::RentExpense, self::SalaryExpense, self::UtilitiesExpense, self::SubscriptionExpense => self::Expenses,
            default => null,
        };
    }

    public function isDefaultSeeded(?int $branchId = null): bool
    {
        if (in_array($this, [self::BankAccount, self::BranchInventory, self::PurchaseReturns], true)) {
            return false;
        }

        // SuperAdmin global panel (branchId === null): only platform & billing accounts
        if ($branchId === null) {
            return match ($this) {
                self::Inventory, self::ProductInventory, self::BranchInventory,
                self::AccountsReceivable, self::CustomerReceivables, self::IntercompanyReceivable,
                self::AccountsPayable, self::SupplierPayables, self::IntercompanyPayable, self::SubscriptionPayable,
                self::AdvanceFromCustomer, self::CustomerCoinPayable,
                self::ProductSales, self::SalesReturns, self::DiscountApplied, self::CoinDiscountApplied,
                self::CostOfGoodsSold, self::InventoryDamage, self::StockAdjustmentGain, self::StockAdjustmentLoss,
                self::SubscriptionExpense => false,
                default => true,
            };
        }

        // Branch retail POS panel (branchId !== null): full retail store tree + subscription payable/expense
        return match ($this) {
            self::SubscriptionIncome => false, // only SuperAdmin receives subscription income
            default => true,
        };
    }

    /**
     * @return list<SystemAccountKey>
     */
    public static function defaultSeededCases(?int $branchId = null): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $key) => $key->isDefaultSeeded($branchId),
        ));
    }
}
