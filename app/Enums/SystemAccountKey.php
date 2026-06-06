<?php

namespace App\Enums;

enum SystemAccountKey: string
{
    case CurrentAssets = 'current_assets';
    case Inventory = 'inventory';
    case InputVat = 'input_vat';
    case AccountsReceivable = 'accounts_receivable';
    case CurrentLiabilities = 'current_liabilities';
    case AccountsPayable = 'accounts_payable';
    case OutputVat = 'output_vat';
    case Equity = 'equity';
    case OpeningBalanceEquity = 'opening_balance_equity';
    case Income = 'income';
    case SalesRevenue = 'sales_revenue';
    case SalesReturns = 'sales_returns';
    case Expenses = 'expenses';
    case CostOfGoodsSold = 'cost_of_goods_sold';
    case InventoryDamage = 'inventory_damage';

    public function accountNumber(): string
    {
        return 'SYS:'.$this->value;
    }

    public function defaultName(): string
    {
        return match ($this) {
            self::CurrentAssets => 'Current Assets',
            self::Inventory => 'Inventory',
            self::InputVat => 'Input VAT',
            self::AccountsReceivable => 'Accounts Receivable',
            self::CurrentLiabilities => 'Current Liabilities',
            self::AccountsPayable => 'Accounts Payable',
            self::OutputVat => 'Output VAT',
            self::Equity => 'Equity',
            self::OpeningBalanceEquity => 'Opening Balance Equity',
            self::Income => 'Income',
            self::SalesRevenue => 'Sales Revenue',
            self::SalesReturns => 'Sales Returns',
            self::Expenses => 'Expenses',
            self::CostOfGoodsSold => 'Cost of Goods Sold',
            self::InventoryDamage => 'Inventory Damage / Write-off',
        };
    }

    public function accountType(): AccountType
    {
        return match ($this) {
            self::CurrentAssets, self::Inventory, self::InputVat, self::AccountsReceivable => AccountType::Asset,
            self::CurrentLiabilities, self::AccountsPayable, self::OutputVat => AccountType::Liability,
            self::Equity, self::OpeningBalanceEquity => AccountType::Equity,
            self::Income, self::SalesRevenue, self::SalesReturns => AccountType::Income,
            self::Expenses, self::CostOfGoodsSold, self::InventoryDamage => AccountType::Expenses,
        };
    }

    public function isHead(): bool
    {
        return match ($this) {
            self::CurrentAssets, self::CurrentLiabilities, self::Equity, self::Income, self::Expenses => true,
            default => false,
        };
    }

    public function parentKey(): ?SystemAccountKey
    {
        return match ($this) {
            self::Inventory, self::InputVat, self::AccountsReceivable => self::CurrentAssets,
            self::AccountsPayable, self::OutputVat => self::CurrentLiabilities,
            self::OpeningBalanceEquity => self::Equity,
            self::SalesRevenue, self::SalesReturns => self::Income,
            self::CostOfGoodsSold, self::InventoryDamage => self::Expenses,
            default => null,
        };
    }
}
