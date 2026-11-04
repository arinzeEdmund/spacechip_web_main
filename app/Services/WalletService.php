<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The USD wallet ledger. Balances live on users.wallet_balance_minor (cents); every change is
 * written with a WalletTransaction row in the same DB transaction, under a row lock, so
 * concurrent purchases can't overspend.
 */
class WalletService
{
    public function balanceMinor(int $userId): int
    {
        return (int) (User::query()->whereKey($userId)->value('wallet_balance_minor') ?? 0);
    }

    public function formatUsd(int $amountMinor): string
    {
        return '$'.number_format($amountMinor / 100, 2);
    }

    public function credit(int $userId, int $amountMinor, string $action, array $meta = [], ?int $paymentId = null): WalletTransaction
    {
        return $this->apply('credit', $userId, $amountMinor, $action, $meta, $paymentId);
    }

    /** @throws \RuntimeException when the balance is insufficient or would drop below `$minBalanceMinor` */
    public function debit(int $userId, int $amountMinor, string $action, array $meta = [], ?int $paymentId = null, int $minBalanceMinor = 0): WalletTransaction
    {
        return $this->apply('debit', $userId, $amountMinor, $action, $meta, $paymentId, $minBalanceMinor);
    }

    private function apply(string $direction, int $userId, int $amountMinor, string $action, array $meta, ?int $paymentId, int $minBalanceMinor = 0): WalletTransaction
    {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }

        // Retried up to 3 times on deadlock.
        return DB::transaction(function () use ($direction, $userId, $amountMinor, $action, $meta, $paymentId, $minBalanceMinor) {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $before = (int) ($user->wallet_balance_minor ?? 0);

            if ($direction === 'debit') {
                if ($before < $amountMinor) {
                    throw new \RuntimeException('Insufficient wallet balance.');
                }
                if ($before - $amountMinor < $minBalanceMinor) {
                    throw new \RuntimeException('Wallet balance would fall below minimum threshold.');
                }
            }

            $after = $direction === 'debit' ? $before - $amountMinor : $before + $amountMinor;
            $user->wallet_balance_minor = $after;
            $user->save();

            Log::info('Wallet '.ucfirst($direction).": User {$userId}", [
                'amount_minor' => $amountMinor,
                'action' => $action,
                'before' => $before,
                'after' => $after,
                'payment_id' => $paymentId,
            ]);

            return WalletTransaction::create([
                'user_id' => $userId,
                'direction' => $direction,
                'action' => $action,
                'amount_minor' => $amountMinor,
                'balance_before_minor' => $before,
                'balance_after_minor' => $after,
                'currency' => 'USD',
                'payment_id' => $paymentId,
                'meta' => $meta,
            ]);
        }, 3);
    }
}
