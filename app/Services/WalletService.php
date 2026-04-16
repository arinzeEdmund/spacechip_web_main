<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function balanceMinor(int $userId): int
    {
        $user = User::query()->select(['id', 'wallet_balance_minor'])->find($userId);

        return $user ? (int) ($user->wallet_balance_minor ?? 0) : 0;
    }

    public function formatUsd(int $amountMinor): string
    {
        $value = number_format($amountMinor / 100, 2);

        return '$'.$value;
    }

    public function credit(int $userId, int $amountMinor, string $action, array $meta = [], ?int $paymentId = null): WalletTransaction
    {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }

        return DB::transaction(function () use ($userId, $amountMinor, $action, $meta, $paymentId) {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $before = (int) ($user->wallet_balance_minor ?? 0);
            $after = $before + $amountMinor;

            $user->wallet_balance_minor = $after;
            $user->save();

            return WalletTransaction::create([
                'user_id' => $userId,
                'direction' => 'credit',
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

    public function debit(int $userId, int $amountMinor, string $action, array $meta = [], ?int $paymentId = null, int $minBalanceMinor = 0): WalletTransaction
    {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }

        return DB::transaction(function () use ($userId, $amountMinor, $action, $meta, $paymentId, $minBalanceMinor) {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            $before = (int) ($user->wallet_balance_minor ?? 0);

            if ($before < $amountMinor) {
                throw new \RuntimeException('Insufficient wallet balance.');
            }

            $after = $before - $amountMinor;
            if ($after < $minBalanceMinor) {
                throw new \RuntimeException('Wallet balance would fall below minimum threshold.');
            }

            $user->wallet_balance_minor = $after;
            $user->save();

            return WalletTransaction::create([
                'user_id' => $userId,
                'direction' => 'debit',
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
