<?php

namespace App\Services;

use App\Enums\VoucherLineSide;
use App\Enums\VoucherType;
use App\Models\ChartOfAccount;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use Illuminate\Support\Facades\DB;

class VoucherService
{
    public static function nextVoucherNo(VoucherType $type): string
    {
        $prefix = $type->prefix();
        $last = Voucher::withTrashed()
            ->where('type', $type)
            ->where('voucher_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('voucher_no');

        $next = 1001;
        if ($last && preg_match('/'.preg_quote($prefix, '/').'(\d+)/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return $prefix.$next;
    }

    public static function nextTransactionReference(): string
    {
        return 'TXN-'.now()->format('Ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, User $user): Voucher
    {
        return DB::transaction(function () use ($data, $user) {
            $voucher = $this->createVoucherRecord($data, $user);
            $transaction = $this->postToLedger($voucher, $data, $user);
            $voucher->update(['transaction_id' => $transaction->id]);

            return $voucher->fresh(['lines.account', 'party', 'fromAccount', 'toAccount', 'paymentAccount']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Voucher $voucher, array $data, ?User $user = null): Voucher
    {
        return DB::transaction(function () use ($voucher, $data, $user) {
            if ($voucher->transaction_id) {
                $existing = Transaction::find($voucher->transaction_id);
                if ($existing) {
                    TransactionService::reverseTransaction($existing);
                }
            }

            $voucher->lines()->delete();
            $this->fillVoucher($voucher, $data);
            $voucher->save();
            $this->syncLines($voucher, $data);

            $transaction = $this->postToLedger($voucher->fresh(['lines.account']), $data, $user);
            $voucher->update(['transaction_id' => $transaction->id]);

            return $voucher->fresh(['lines.account', 'party', 'fromAccount', 'toAccount', 'paymentAccount']);
        });
    }

    public function destroy(Voucher $voucher): void
    {
        DB::transaction(function () use ($voucher) {
            if ($voucher->transaction_id) {
                $existing = Transaction::find($voucher->transaction_id);
                if ($existing) {
                    TransactionService::reverseTransaction($existing);
                }
            }

            $voucher->lines()->delete();
            $voucher->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createVoucherRecord(array $data, User $user): Voucher
    {
        $voucher = new Voucher;
        $this->fillVoucher($voucher, $data);
        $voucher->created_by = $user->id;
        $voucher->branch_id = $user->branch_id;
        $voucher->save();
        $this->syncLines($voucher, $data);

        return $voucher->fresh(['lines']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillVoucher(Voucher $voucher, array $data): void
    {
        $type = VoucherType::from((int) $data['type']);

        $voucher->type = $type;
        $voucher->voucher_no = $data['voucher_no'];
        $voucher->date = $data['date'];
        $voucher->transaction_reference = $data['transaction_reference'] ?? self::nextTransactionReference();
        $voucher->party_type = $data['party_type'] ?? null;
        $voucher->party_id = $data['party_id'] ?? null;
        $voucher->from_account_id = $data['from_account_id'] ?? null;
        $voucher->to_account_id = $data['to_account_id'] ?? null;
        $voucher->payment_account_id = $data['payment_account_id'] ?? null;
        $voucher->narration = $data['narration'] ?? null;
        $voucher->total_amount = $this->resolveTotalAmount($type, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncLines(Voucher $voucher, array $data): void
    {
        $type = $voucher->type;

        if ($type === VoucherType::Contra) {
            return;
        }

        $defaultSide = match ($type) {
            VoucherType::Expense => VoucherLineSide::Debit,
            VoucherType::Income => VoucherLineSide::Credit,
            default => null,
        };

        $lines = $data['lines'] ?? [];
        foreach ($lines as $index => $line) {
            $side = isset($line['side'])
                ? VoucherLineSide::from($line['side'])
                : $defaultSide;

            VoucherLine::create([
                'voucher_id' => $voucher->id,
                'side' => $side,
                'account_id' => $line['account_id'],
                'amount' => $line['amount'],
                'narration' => $line['narration'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postToLedger(Voucher $voucher, array $data, ?User $user = null): Transaction
    {
        $type = $voucher->type;
        $performedBy = $this->performedByPayload($voucher);
        $masterNarration = $voucher->narration;
        $sessionPayload = $this->businessSessionPayload($user);

        if ($type === VoucherType::Contra) {
            return TransactionService::recordTransaction([
                'source_type' => Voucher::class,
                'source_id' => $voucher->id,
                ...$performedBy,
                ...$sessionPayload,
                'date' => $voucher->date->format('Y-m-d'),
                'amount' => (float) $voucher->total_amount,
                'debit_account_id' => $voucher->to_account_id,
                'credit_account_id' => $voucher->from_account_id,
                'debit_decrease' => false,
                'credit_decrease' => true,
                'description' => $masterNarration,
                'debit_description' => $data['debit_description'] ?? $masterNarration,
                'credit_description' => $data['credit_description'] ?? $masterNarration,
            ]);
        }

        $glLines = $this->buildGlLines($type, $data, $voucher);

        return TransactionService::recordJournalEntry([
            'source_type' => Voucher::class,
            'source_id' => $voucher->id,
            ...$performedBy,
            ...$sessionPayload,
            'date' => $voucher->date->format('Y-m-d'),
            'description' => $masterNarration,
        ], $glLines);
    }

    /**
     * @return array{business_session_id?: int}
     */
    private function businessSessionPayload(?User $user): array
    {
        $sessionId = app(BusinessSessionService::class)->activeSessionIdForUser($user);

        if ($sessionId === null) {
            return [];
        }

        return ['business_session_id' => $sessionId];
    }

    /**
     * @return array{performed_by_type?: string, performed_by_id?: int}
     */
    private function performedByPayload(Voucher $voucher): array
    {
        if (! $voucher->created_by) {
            return [];
        }

        return [
            'performed_by_type' => User::class,
            'performed_by_id' => $voucher->created_by,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{account_id: int, debit: float, credit: float, decrease: bool, description?: ?string}>
     */
    private function buildGlLines(VoucherType $type, array $data, ?Voucher $voucher = null): array
    {
        $masterNarration = $data['narration'] ?? null;
        $creditDescription = $data['credit_description'] ?? $masterNarration;
        $debitDescription = $data['debit_description'] ?? $masterNarration;

        if ($type === VoucherType::Journal) {
            return $this->buildJournalGlLines($data['lines'] ?? [], $masterNarration);
        }

        if ($type === VoucherType::Expense) {
            $lines = $data['lines'] ?? [];
            $glLines = $this->buildHeadLines($lines, VoucherLineSide::Debit, $masterNarration);
            $total = array_sum(array_column($glLines, 'debit'));
            $paymentAccountId = (int) ($data['payment_account_id'] ?? 0);
            $paymentType = ChartOfAccount::findOrFail($paymentAccountId)->type;

            $glLines[] = [
                'account_id' => $paymentAccountId,
                'debit' => 0.0,
                'credit' => $total,
                'decrease' => AccountPostingRules::decreaseForSide($paymentType, false),
                'description' => $creditDescription,
            ];

            return $glLines;
        }

        if ($type === VoucherType::Income) {
            $lines = $data['lines'] ?? [];
            $glLines = $this->buildHeadLines($lines, VoucherLineSide::Credit, $masterNarration);
            $total = array_sum(array_column($glLines, 'credit'));
            $paymentAccountId = (int) ($data['payment_account_id'] ?? 0);
            $paymentType = ChartOfAccount::findOrFail($paymentAccountId)->type;

            $glLines[] = [
                'account_id' => $paymentAccountId,
                'debit' => $total,
                'credit' => 0.0,
                'decrease' => AccountPostingRules::decreaseForSide($paymentType, true),
                'description' => $debitDescription,
            ];

            return $glLines;
        }

        return [];
    }

    /**
     * @param  array<int, array{side: string, account_id: int, amount: float|int, narration?: ?string}>  $lines
     * @return array<int, array{account_id: int, debit: float, credit: float, decrease: bool, description?: ?string}>
     */
    private function buildJournalGlLines(array $lines, ?string $masterNarration): array
    {
        $accountIds = collect($lines)->pluck('account_id')->unique()->all();
        $accounts = ChartOfAccount::query()->whereIn('id', $accountIds)->get()->keyBy('id');
        $glLines = [];

        foreach ($lines as $line) {
            $side = VoucherLineSide::from($line['side']);
            $amount = (float) $line['amount'];
            $account = $accounts->get((int) $line['account_id']);
            if (! $account) {
                throw new \Exception('Invalid account on journal line.');
            }

            $isDebit = $side->isDebit();
            $glLines[] = [
                'account_id' => $account->id,
                'debit' => $isDebit ? $amount : 0.0,
                'credit' => $isDebit ? 0.0 : $amount,
                'decrease' => AccountPostingRules::decreaseForSide($account->type, $isDebit),
                'description' => $line['narration'] ?? $masterNarration,
            ];
        }

        return $glLines;
    }

    /**
     * @param  array<int, array{account_id: int, amount: float|int, narration?: ?string}>  $lines
     * @return array<int, array{account_id: int, debit: float, credit: float, decrease: bool, description?: ?string}>
     */
    private function buildHeadLines(array $lines, VoucherLineSide $side, ?string $masterNarration): array
    {
        $accountIds = collect($lines)->pluck('account_id')->unique()->all();
        $accounts = ChartOfAccount::query()->whereIn('id', $accountIds)->get()->keyBy('id');
        $glLines = [];
        $isDebit = $side->isDebit();

        foreach ($lines as $line) {
            $amount = (float) $line['amount'];
            $account = $accounts->get((int) $line['account_id']);
            if (! $account) {
                throw new \Exception('Invalid account on voucher line.');
            }

            $glLines[] = [
                'account_id' => $account->id,
                'debit' => $isDebit ? $amount : 0.0,
                'credit' => $isDebit ? 0.0 : $amount,
                'decrease' => AccountPostingRules::decreaseForSide($account->type, $isDebit),
                'description' => $line['narration'] ?? $masterNarration,
            ];
        }

        return $glLines;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveTotalAmount(VoucherType $type, array $data): float
    {
        if ($type === VoucherType::Contra) {
            return (float) ($data['total_amount'] ?? 0);
        }

        $lines = $data['lines'] ?? [];
        if ($type === VoucherType::Journal) {
            return (float) collect($lines)
                ->filter(fn ($l) => ($l['side'] ?? '') === VoucherLineSide::Debit->value)
                ->sum('amount');
        }

        return (float) collect($lines)->sum('amount');
    }
}
