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
    case AccountsPayable = 'accounts_payable';
    case SupplierPayables = 'supplier_payables';
    case LoansPayable = 'loans_payable';
    case AdvanceFromCustomer = 'advance_from_customer';
    case TaxesPayable = 'taxes_payable';
    case OutputVat = 'output_vat';
    case OwnersCapital = 'owners_capital';
    case RetainedEarnings = 'retained_earnings';
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
    case PurchaseReturns = 'purchase_returns';
    case RentExpense = 'rent_expense';
    case SalaryExpense = 'salary_expense';
    case UtilitiesExpense = 'utilities_expense';

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
            self::AccountsPayable => 'Accounts Payable',
            self::SupplierPayables => 'Supplier Payables',
            self::LoansPayable => 'Loans Payable',
            self::AdvanceFromCustomer => 'Advance from Customer',
            self::TaxesPayable => 'Taxes Payable',
            self::OutputVat => 'Output VAT',
            self::OwnersCapital => "Owner's Capital",
            self::RetainedEarnings => 'Retained Earnings',
            self::OwnersDrawings => "Owner's Drawings",
            self::OpeningBalanceEquity => 'Opening Balance Equity',
            self::OpeningBalanceClearing => 'Opening Balance Clearing',
            self::SalesRevenue => 'Sales Revenue',
            self::ProductSales => 'Product Sales',
            self::SalesReturns => 'Sales Returns',
            self::OtherIncome => 'Other Income',
            self::Expenses => 'Expenses',
            self::CostOfGoodsSold => 'Cost of Goods Sold',
            self::InventoryDamage => 'Inventory Damage / Write-off',
            self::PurchaseReturns => 'Purchase Returns',
            self::RentExpense => 'Rent',
            self::SalaryExpense => 'Salary',
            self::UtilitiesExpense => 'Utilities',
        };
    }

    public function accountType(): AccountType
    {
        return match ($this) {
            self::CashAndBank, self::CashInHand, self::BankAccount, self::SslCommerz, self::Bkash, self::Nagad,
            self::Inventory, self::ProductInventory, self::BranchInventory,
            self::AccountsReceivable, self::CustomerReceivables => AccountType::Asset,
            self::AccountsPayable, self::SupplierPayables, self::LoansPayable,
            self::AdvanceFromCustomer, self::TaxesPayable, self::OutputVat => AccountType::Liability,
            self::OwnersCapital, self::RetainedEarnings, self::OwnersDrawings,
            self::OpeningBalanceEquity, self::OpeningBalanceClearing => AccountType::Equity,
            self::SalesRevenue, self::ProductSales, self::SalesReturns, self::OtherIncome => AccountType::Income,
            self::Expenses, self::CostOfGoodsSold, self::InventoryDamage, self::PurchaseReturns,
            self::RentExpense, self::SalaryExpense, self::UtilitiesExpense => AccountType::Expenses,
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
            self::CustomerReceivables => self::AccountsReceivable,
            self::SupplierPayables => self::AccountsPayable,
            self::OutputVat => self::TaxesPayable,
            self::OpeningBalanceClearing => self::OpeningBalanceEquity,
            self::ProductSales, self::SalesReturns => self::SalesRevenue,
            self::CostOfGoodsSold, self::InventoryDamage, self::PurchaseReturns,
            self::RentExpense, self::SalaryExpense, self::UtilitiesExpense => self::Expenses,
            default => null,
        };
    }

    public function isDefaultSeeded(): bool
    {
        return match ($this) {
            self::BankAccount, self::BranchInventory => false,
            default => true,
        };
    }

    /**
     * @return list<SystemAccountKey>
     */
    public static function defaultSeededCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $key) => $key->isDefaultSeeded(),
        ));
    }
}
