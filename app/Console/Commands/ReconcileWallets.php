<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily integrity check on the money ledger: every user's stored balance must equal credits minus debits, and the
 * ledger must be a gap-free chain. A mismatch means a bug, a manual DB edit or tampering, so it is logged as an
 * error (wire your alerting to it) and the command exits non-zero.
 */
class ReconcileWallets extends Command
{
    protected $signature = 'wallet:reconcile';

    protected $description = 'Verify wallet balances against the transaction ledger';

    public function handle(): int
    {
        $bad = 0;

        $sums = WalletTransaction::query()
            ->selectRaw("user_id, SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE -amount_minor END) AS net")
            ->groupBy('user_id')->pluck('net', 'user_id');

        foreach (User::query()->where(fn ($q) => $q->where('wallet_balance_minor', '!=', 0)->orWhereIn('id', $sums->keys()))->select('id', 'email', 'wallet_balance_minor')->cursor() as $user) {
            $net = (int) ($sums[$user->id] ?? 0);
            if ((int) $user->wallet_balance_minor !== $net) {
                $bad++;
                Log::error('Wallet reconcile mismatch', ['user_id' => $user->id, 'stored_minor' => (int) $user->wallet_balance_minor, 'ledger_minor' => $net]);
                $this->error("User #{$user->id}: stored {$user->wallet_balance_minor} vs ledger {$net}");
            }
        }

        // Chain continuity: each entry must start where the previous one ended.
        $prev = [];
        foreach (WalletTransaction::query()->orderBy('user_id')->orderBy('id')->select('id', 'user_id', 'balance_before_minor', 'balance_after_minor')->cursor() as $t) {
            if (isset($prev[$t->user_id]) && (int) $t->balance_before_minor !== $prev[$t->user_id]) {
                $bad++;
                Log::error('Wallet ledger chain break', ['user_id' => $t->user_id, 'transaction_id' => $t->id]);
                $this->error("Ledger gap for user #{$t->user_id} at transaction #{$t->id}");
            }
            $prev[$t->user_id] = (int) $t->balance_after_minor;
        }

        $this->info($bad === 0 ? 'Wallets reconcile.' : "{$bad} problem(s) found.");

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }
}
