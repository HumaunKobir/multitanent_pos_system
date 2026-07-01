<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * একক ট্রানজ্যাকশন ও সংশ্লিষ্ট debit/credit ledger এন্ট্রি রেকর্ড করে।
     *
     * @param  array{
     *   source_type: string,
     *   source_id: int|string,
     *   performed_by_type?: ?string,
     *   performed_by_id?: int|string|null,
     *   date: string,
     *   amount: float|int,
     *   debit_account_id?: int,
     *   credit_account_id?: int,
     *   debit_decrease?: bool,
     *   credit_decrease?: bool,
     *   debit_performed_by_type?: ?string,
     *   debit_performed_by_id?: int|string|null,
     *   credit_performed_by_type?: ?string,
     *   credit_performed_by_id?: int|string|null,
     *   description?: ?string,
     *   debit_description?: ?string,
     *   credit_description?: ?string
     * }  $data
     * @param  bool  $validateBalance  কমতে যাওয়া ব্যালেন্স আগে যাচাই করবে কি না।
     *
     * @throws \Exception
     */
    public static function recordTransaction(array $data, bool $validateBalance = true): Transaction
    {
        return DB::transaction(function () use ($data, $validateBalance) {
            [$sourceType, $sourceId] = self::resolveAndValidateSource($data);
            [$performedByType, $performedById] = self::resolvePerformedBy($data);

            if ($validateBalance) {
                self::validateAccountBalances($data);
            }

            $transaction = Transaction::create([
                ...$data,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'performed_by_type' => $performedByType,
                'performed_by_id' => $performedById,
                'business_session_id' => self::resolveBusinessSessionId($data),
            ]);
            $entries = self::buildTwoSideEntries($data, $performedByType, $performedById);

            foreach ($entries as $entry) {
                self::persistLedgerEntryAndUpdateBalance([
                    'account_id' => (int) $entry['account_id'],
                    'transaction_id' => $transaction->id,
                    'date' => $data['date'],
                    'debit' => (float) $entry['debit'],
                    'credit' => (float) $entry['credit'],
                    'decrease' => (bool) $entry['decrease'],
                    'description' => $entry['description'] ?? null,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'line_performed_by_type' => $entry['line_performed_by_type'] ?? null,
                    'line_performed_by_id' => $entry['line_performed_by_id'] ?? null,
                ]);
            }

            return $transaction;
        });
    }

    /**
     * balanced জার্নাল এন্ট্রি রেকর্ড করে (একটি transaction, একাধিক ledger line)।
     *
     * @param  array{
     *   source_type: string,
     *   source_id: int|string,
     *   performed_by_type?: ?string,
     *   performed_by_id?: int|string|null,
     *   date: string,
     *   description?: ?string,
     *   approved_at?: mixed
     * }  $header
     * @param  array<int, array{
     *   account_id: int,
     *   debit: float|int,
     *   credit: float|int,
     *   decrease?: bool,
     *   performed_by_type?: ?string,
     *   performed_by_id?: int|string|null,
     *   description?: ?string
     * }>  $lines
     * @param  bool  $validateBalance  কমতে যাওয়া লাইনের ব্যালেন্স যাচাই করবে কি না।
     *
     * @throws \Exception
     */
    public static function recordJournalEntry(array $header, array $lines, bool $validateBalance = true): Transaction
    {
        return DB::transaction(function () use ($header, $lines, $validateBalance) {
            [$sourceType, $sourceId] = self::resolveAndValidateSource($header);
            [$performedByType, $performedById] = self::resolvePerformedBy($header);

            if ($lines === []) {
                throw new \Exception('Journal entry must have at least one line.');
            }

            [$totalDebit, $totalCredit] = self::calculateJournalTotals($lines);

            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new \Exception('Journal entry is not balanced.');
            }

            if ($validateBalance) {
                foreach ($lines as $line) {
                    self::validateJournalLineBalance($line);
                }
            }

            [$firstDebitLine, $firstCreditLine] = self::resolveFirstJournalLines($lines);

            $transaction = Transaction::create([
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'performed_by_type' => $performedByType,
                'performed_by_id' => $performedById,
                'date' => $header['date'],
                'amount' => $totalDebit,
                'debit_account_id' => $firstDebitLine['account_id'] ?? null,
                'credit_account_id' => $firstCreditLine['account_id'] ?? null,
                'debit_decrease' => $firstDebitLine['decrease'] ?? false,
                'credit_decrease' => $firstCreditLine['decrease'] ?? false,
                'description' => $header['description'] ?? null,
                'approved_at' => $header['approved_at'] ?? null,
                'business_session_id' => self::resolveBusinessSessionId($header),
            ]);

            foreach ($lines as $entry) {
                [$linePerformedByType, $linePerformedById] = self::resolveLinePerformedBy($entry, $performedByType, $performedById);

                self::persistLedgerEntryAndUpdateBalance([
                    'account_id' => (int) $entry['account_id'],
                    'transaction_id' => $transaction->id,
                    'date' => $header['date'],
                    'debit' => (float) ($entry['debit'] ?? 0),
                    'credit' => (float) ($entry['credit'] ?? 0),
                    'decrease' => (bool) ($entry['decrease'] ?? false),
                    'description' => $entry['description'] ?? $header['description'] ?? null,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'line_performed_by_type' => $linePerformedByType,
                    'line_performed_by_id' => $linePerformedById,
                ]);
            }

            return $transaction;
        });
    }

    /**
     * Journal লাইনে decrease=true হলে পর্যাপ্ত ব্যালেন্স আছে কিনা যাচাই করে।
     *
     * @param  array{account_id: int, debit?: float|int, credit?: float|int, decrease?: bool}  $line
     */
    private static function validateJournalLineBalance(array $line): void
    {
        $decrease = $line['decrease'] ?? false;

        if (! $decrease) {
            return;
        }

        // P&L accounts (Income, Expenses) are contra accounts that can freely
        // start at zero and accumulate entries in either direction. Only
        // balance-sheet accounts (Asset, Liability) need an insufficient-balance guard.
        $account = ChartOfAccount::find((int) $line['account_id']);
        if ($account && in_array($account->type, [AccountType::Income, AccountType::Expenses])) {
            return;
        }

        $amount = self::extractEntryAmount($line);
        self::ensureSufficientBalance((int) $line['account_id'], $amount, 'Insufficient balance in account ID '.$line['account_id'].'.');
    }

    /**
     * একক debit/credit ট্রানজ্যাকশনে decrease=true থাকা সাইডের ব্যালেন্স যাচাই করে।
     *
     * @param  array{
     *   amount: float|int,
     *   debit_account_id?: int,
     *   credit_account_id?: int,
     *   debit_decrease?: bool,
     *   credit_decrease?: bool
     * }  $data
     */
    private static function validateAccountBalances(array $data): void
    {
        $amount = (float) $data['amount'];

        if (! empty($data['debit_account_id']) && ($data['debit_decrease'] ?? false)) {
            self::ensureSufficientBalance((int) $data['debit_account_id'], $amount, 'Insufficient balance in debit account.');
        }

        if (! empty($data['credit_account_id']) && ($data['credit_decrease'] ?? false)) {
            self::ensureSufficientBalance((int) $data['credit_account_id'], $amount, 'Insufficient balance in credit account.');
        }
    }

    /**
     * সোর্স তথ্য বাধ্যতামূলকভাবে রিজলভ করে; না থাকলে exception ছোড়ে।
     *
     * @param  array{source_type?: mixed, source_id?: mixed}  $payload
     * @return array{0: string, 1: int|string}
     */
    private static function resolveAndValidateSource(array $payload): array
    {
        $sourceType = $payload['source_type'] ?? null;
        $sourceId = $payload['source_id'] ?? null;

        if (! is_string($sourceType) || $sourceType === '' || $sourceId === null || $sourceId === '') {
            throw new \Exception('Transaction source is required.');
        }

        return [$sourceType, $sourceId];
    }

    /**
     * transaction-level actor resolve করে।
     *
     * @param  array{performed_by_type?: mixed, performed_by_id?: mixed}  $payload
     * @return array{0: ?string, 1: int|string|null}
     */
    private static function resolvePerformedBy(array $payload): array
    {
        $performedByType = $payload['performed_by_type'] ?? null;
        $performedById = $payload['performed_by_id'] ?? null;

        if ($performedByType === null && $performedById === null) {
            return [null, null];
        }

        if (! is_string($performedByType) || $performedByType === '' || $performedById === null || $performedById === '') {
            throw new \Exception('Performed by তথ্য অসম্পূর্ণ। performed_by_type এবং performed_by_id একসাথে দিন।');
        }

        return [$performedByType, $performedById];
    }

    /**
     * line-level actor resolve করে; line override না থাকলে transaction actor ব্যবহার করে।
     *
     * @param  array{performed_by_type?: mixed, performed_by_id?: mixed}  $line
     * @return array{0: ?string, 1: int|string|null}
     */
    private static function resolveLinePerformedBy(array $line, ?string $transactionPerformedByType, int|string|null $transactionPerformedById): array
    {
        $linePerformedByType = $line['performed_by_type'] ?? null;
        $linePerformedById = $line['performed_by_id'] ?? null;

        if ($linePerformedByType === null && $linePerformedById === null) {
            return [$transactionPerformedByType, $transactionPerformedById];
        }

        if (! is_string($linePerformedByType) || $linePerformedByType === '' || $linePerformedById === null || $linePerformedById === '') {
            throw new \Exception('Line performed by তথ্য অসম্পূর্ণ। performed_by_type এবং performed_by_id একসাথে দিন।');
        }

        return [$linePerformedByType, $linePerformedById];
    }

    /**
     * `recordTransaction` ইনপুটকে debit/credit ledger entry array-তে রূপান্তর করে।
     *
     * @param  array{
     *   amount: float|int,
     *   debit_account_id?: int,
     *   credit_account_id?: int,
     *   debit_decrease?: bool,
     *   credit_decrease?: bool,
     *   debit_performed_by_type?: ?string,
     *   debit_performed_by_id?: int|string|null,
     *   credit_performed_by_type?: ?string,
     *   credit_performed_by_id?: int|string|null,
     *   description?: ?string,
     *   debit_description?: ?string,
     *   credit_description?: ?string
     * }  $data
     * @return array<int, array{
     *   account_id: int,
     *   debit: float,
     *   credit: float,
     *   decrease: bool,
     *   description: ?string,
     *   line_performed_by_type: ?string,
     *   line_performed_by_id: int|string|null
     * }>
     */
    private static function buildTwoSideEntries(array $data, ?string $transactionPerformedByType, int|string|null $transactionPerformedById): array
    {
        $entries = [];
        $amount = (float) $data['amount'];

        if (! empty($data['debit_account_id'])) {
            $entries[] = [
                'account_id' => (int) $data['debit_account_id'],
                'debit' => $amount,
                'credit' => 0.0,
                'decrease' => (bool) ($data['debit_decrease'] ?? false),
                'description' => $data['debit_description'] ?? $data['description'] ?? null,
                'line_performed_by_type' => $data['debit_performed_by_type'] ?? $transactionPerformedByType,
                'line_performed_by_id' => $data['debit_performed_by_id'] ?? $transactionPerformedById,
            ];
        }

        if (! empty($data['credit_account_id'])) {
            $entries[] = [
                'account_id' => (int) $data['credit_account_id'],
                'debit' => 0.0,
                'credit' => $amount,
                'decrease' => (bool) ($data['credit_decrease'] ?? false),
                'description' => $data['credit_description'] ?? $data['description'] ?? null,
                'line_performed_by_type' => $data['credit_performed_by_type'] ?? $transactionPerformedByType,
                'line_performed_by_id' => $data['credit_performed_by_id'] ?? $transactionPerformedById,
            ];
        }

        return $entries;
    }

    /**
     * Journal lines যাচাই করে মোট debit ও মোট credit বের করে।
     *
     * @param  array<int, array{debit?: float|int, credit?: float|int}>  $lines
     * @return array{0: float, 1: float}
     */
    private static function calculateJournalTotals(array $lines): array
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) {
                throw new \Exception('Each journal line must have either a debit or a credit amount.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        return [$totalDebit, $totalCredit];
    }

    /**
     * প্রথম debit line এবং প্রথম credit line বের করে Transaction meta-field পূরণে সহায়তা করে।
     *
     * @param  array<int, array{account_id?: int, debit?: float|int, credit?: float|int, decrease?: bool}>  $lines
     * @return array{0: ?array, 1: ?array}
     */
    private static function resolveFirstJournalLines(array $lines): array
    {
        $firstDebitLine = null;
        $firstCreditLine = null;

        foreach ($lines as $line) {
            if ((float) ($line['debit'] ?? 0) > 0 && $firstDebitLine === null) {
                $firstDebitLine = $line;
            }

            if ((float) ($line['credit'] ?? 0) > 0 && $firstCreditLine === null) {
                $firstCreditLine = $line;
            }
        }

        return [$firstDebitLine, $firstCreditLine];
    }

    /**
     * একটি ledger row তৈরি করে এবং সংশ্লিষ্ট অ্যাকাউন্টের `current_balance` আপডেট করে।
     *
     * @param  array{
     *   account_id: int,
     *   transaction_id: int,
     *   date: string,
     *   debit: float,
     *   credit: float,
     *   decrease: bool,
     *   description?: ?string,
     *   source_type: string,
     *   source_id: int|string,
     *   line_performed_by_type?: ?string,
     *   line_performed_by_id?: int|string|null
     * }  $entry
     */
    private static function persistLedgerEntryAndUpdateBalance(array $entry): void
    {
        $account = ChartOfAccount::lockForUpdate()->findOrFail($entry['account_id']);
        $opening = (float) $account->current_balance;
        $amount = self::extractEntryAmount($entry);
        $balanceChange = $entry['decrease'] ? -$amount : $amount;
        $closing = $opening + $balanceChange;

        Ledger::create([
            'account_id' => $account->id,
            'transaction_id' => $entry['transaction_id'],
            'date' => $entry['date'],
            'opening_balance' => $opening,
            'debit' => $entry['debit'],
            'credit' => $entry['credit'],
            'closing_balance' => $closing,
            'description' => $entry['description'] ?? null,
            'source_type' => $entry['source_type'],
            'source_id' => $entry['source_id'],
            'line_performed_by_type' => $entry['line_performed_by_type'] ?? null,
            'line_performed_by_id' => $entry['line_performed_by_id'] ?? null,
        ]);

        $account->update([
            'current_balance' => $closing,
        ]);
    }

    /**
     * Entry থেকে কার্যকর amount নির্ধারণ করে (debit থাকলে debit, নইলে credit)।
     *
     * @param  array{debit?: float|int, credit?: float|int}  $entry
     */
    private static function extractEntryAmount(array $entry): float
    {
        $debit = (float) ($entry['debit'] ?? 0);
        $credit = (float) ($entry['credit'] ?? 0);

        return $debit > 0 ? $debit : $credit;
    }

    private static function ensureSufficientBalance(int $accountId, float $amount, string $message): void
    {
        $balance = ChartOfAccount::lockForUpdate()
            ->findOrFail($accountId)
            ->current_balance;

        if ($balance < $amount) {
            throw new \RuntimeException($message);
        }
    }

    /**
     * ট্রানজ্যাকশন রিভার্স করে: balance rollback, ledger delete, তারপর transaction delete।
     */
    public static function reverseTransaction(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $ledgers = Ledger::where('transaction_id', $transaction->id)->get();
            $accounts = ChartOfAccount::lockForUpdate()
                ->whereIn('id', $ledgers->pluck('account_id')->unique()->values())
                ->get()
                ->keyBy('id');

            foreach ($ledgers as $ledger) {
                $account = $accounts->get($ledger->account_id);

                if ($account) {
                    $balanceChange = $ledger->closing_balance - $ledger->opening_balance;
                    $account->decrement('current_balance', $balanceChange);
                }

                $ledger->delete();
            }
            $transaction->delete();
        });
    }

    /**
     * @param  array{business_session_id?: int|null}  $payload
     */
    private static function resolveBusinessSessionId(array $payload): ?int
    {
        if (array_key_exists('business_session_id', $payload)) {
            return $payload['business_session_id'];
        }

        $user = Auth::user();

        if ($user instanceof User) {
            return app(BusinessSessionService::class)->activeSessionIdForUser($user);
        }

        return null;
    }
}
