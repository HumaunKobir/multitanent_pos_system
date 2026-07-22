<?php

namespace App\Http\Requests\Account;

use App\Enums\AccountType;
use App\Enums\SystemAccountKey;
use App\Enums\VoucherLineSide;
use App\Enums\VoucherType;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Party;
use App\Models\Supplier;
use App\Services\VoucherContactPicker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('party_key')) {
            $this->merge(VoucherContactPicker::parsePartyKey($this->input('party_key')));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = VoucherType::tryFrom((int) $this->input('type'));

        if (! $type) {
            return [
                'type' => ['required', Rule::enum(VoucherType::class)],
            ];
        }

        $base = [
            'type' => ['required', Rule::enum(VoucherType::class)],
            'voucher_no' => ['nullable', 'string', 'max:50'],
            'date' => ['required', 'date'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'narration' => ['nullable', 'string'],
            'debit_description' => ['nullable', 'string', 'max:500'],
            'credit_description' => ['nullable', 'string', 'max:500'],
        ];

        return match ($type) {
            VoucherType::Journal => array_merge($base, [
                'lines' => ['required', 'array', 'min:1'],
                'lines.*.side' => ['required', Rule::enum(VoucherLineSide::class)],
                'lines.*.account_id' => ['required', 'exists:chart_of_accounts,id'],
                'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
                'lines.*.narration' => ['nullable', 'string', 'max:500'],
            ]),
            VoucherType::Contra => array_merge($base, [
                'from_account_id' => ['required', 'exists:chart_of_accounts,id', 'different:to_account_id'],
                'to_account_id' => ['required', 'exists:chart_of_accounts,id'],
                'total_amount' => ['required', 'numeric', 'min:0.01'],
            ]),
            VoucherType::Expense => array_merge($base, [
                'party_key' => ['nullable', 'string', 'regex:/^(party|supplier|customer):\d+$/'],
                'party_type' => ['nullable', 'string'],
                'party_id' => ['nullable', 'integer'],
                'payment_account_id' => ['required', 'exists:chart_of_accounts,id'],
                'lines' => ['required', 'array', 'min:1'],
                'lines.*.account_id' => ['required', 'exists:chart_of_accounts,id'],
                'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
                'lines.*.narration' => ['nullable', 'string', 'max:500'],
            ]),
            VoucherType::Income => array_merge($base, [
                'party_key' => ['nullable', 'string', 'regex:/^(party|supplier|customer):\d+$/'],
                'party_type' => ['nullable', 'string'],
                'party_id' => ['nullable', 'integer'],
                'payment_account_id' => ['required', 'exists:chart_of_accounts,id'],
                'lines' => ['required', 'array', 'min:1'],
                'lines.*.account_id' => ['required', 'exists:chart_of_accounts,id'],
                'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
                'lines.*.narration' => ['nullable', 'string', 'max:500'],
            ]),
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'voucher type',
            'voucher_no' => 'voucher number',
            'date' => 'date',
            'transaction_reference' => 'transaction reference',
            'narration' => 'narration',
            'debit_description' => 'debit description',
            'credit_description' => 'credit description',
            'total_amount' => 'amount',
            'from_account_id' => 'from account',
            'to_account_id' => 'to account',
            'payment_account_id' => 'payment account',
            'party_key' => 'contact',
            'lines' => 'lines',
            'lines.*.side' => 'side',
            'lines.*.account_id' => 'account',
            'lines.*.amount' => 'amount',
            'lines.*.narration' => 'narration',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = VoucherType::tryFrom((int) $this->input('type'));
            if (! $type) {
                return;
            }

            if ($type === VoucherType::Journal) {
                $this->validateJournalBalance($validator);
                $this->validateLeafAccounts($validator, collect($this->input('lines', []))->pluck('account_id')->all());
            }

            if ($type === VoucherType::Contra) {
                $this->validateAssetAccount($validator, 'from_account_id');
                $this->validateAssetAccount($validator, 'to_account_id');
            }

            if ($type === VoucherType::Expense) {
                $this->validateExpenseLines($validator);
                $this->validateAssetAccount($validator, 'payment_account_id');
                $this->validateParty($validator);
            }

            if ($type === VoucherType::Income) {
                $this->validateIncomeLines($validator);
                $this->validateAssetAccount($validator, 'payment_account_id');
                $this->validateParty($validator);
            }
        });
    }

    private function validateJournalBalance(Validator $validator): void
    {
        $lines = $this->input('lines', []);
        $debit = 0.0;
        $credit = 0.0;

        foreach ($lines as $line) {
            $amount = (float) ($line['amount'] ?? 0);
            if (($line['side'] ?? '') === VoucherLineSide::Debit->value) {
                $debit += $amount;
            } else {
                $credit += $amount;
            }
        }

        if (abs($debit - $credit) > 0.01) {
            $validator->errors()->add('lines', 'Journal entry must be balanced (total debit must equal total credit).');
        }
    }

    /**
     * @param  array<int, int>  $accountIds
     */
    private function validateLeafAccounts(Validator $validator, array $accountIds): void
    {
        $invalid = ChartOfAccount::query()
            ->whereIn('id', $accountIds)
            ->whereNull('parent_id')
            ->exists();

        if ($invalid) {
            $validator->errors()->add('lines', 'Only posting (child) accounts can be used.');
        }
    }

    private function validateAssetAccount(Validator $validator, string $field): void
    {
        $id = $this->input($field);
        if (! $id) {
            return;
        }

        $account = ChartOfAccount::query()->find($id);
        if (! $account || ! $account->isPaymentAccount()) {
            $validator->errors()->add($field, 'Account must be an active cash or bank account.');
        }
    }

    private function validateExpenseLines(Validator $validator): void
    {
        $ids = collect($this->input('lines', []))->pluck('account_id')->filter()->all();

        if ($ids === []) {
            return;
        }

        $accounts = ChartOfAccount::query()
            ->whereIn('id', $ids)
            ->get(['id', 'type', 'parent_id', 'account_number']);

        $invalid = $accounts->contains(function (ChartOfAccount $account): bool {
            if ($account->parent_id === null) {
                return true;
            }

            if ($account->type === AccountType::Expenses) {
                return false;
            }

            return $account->account_number !== SystemAccountKey::OutputVat->accountNumber();
        });

        if ($invalid || $accounts->count() !== count(array_unique($ids))) {
            $validator->errors()->add(
                'lines',
                'Expense lines must use expense posting accounts or VAT Payable.',
            );
        }
    }

    private function validateIncomeLines(Validator $validator): void
    {
        $ids = collect($this->input('lines', []))->pluck('account_id')->all();
        $invalid = ChartOfAccount::query()
            ->whereIn('id', $ids)
            ->where(function ($q) {
                $q->where('type', '!=', AccountType::Income)
                    ->orWhereNull('parent_id');
            })
            ->exists();

        if ($invalid) {
            $validator->errors()->add('lines', 'Income lines must use income posting accounts.');
        }
    }

    private function validateParty(Validator $validator): void
    {
        $type = $this->input('party_type');
        $id = $this->input('party_id');

        if (! $type || ! $id) {
            return;
        }

        $exists = match ($type) {
            Party::class => Party::query()->ownBranch()->whereKey($id)->exists(),
            Supplier::class => Supplier::query()->ownBranch()->whereKey($id)->exists(),
            Customer::class => Customer::query()->ownBranch()->whereKey($id)->exists(),
            default => false,
        };

        if (! $exists) {
            $validator->errors()->add('party_key', 'Selected contact is invalid.');
        }
    }
}
