<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function index(): Response
    {
        $usersTotal = User::count();
        $walletsTotal = Wallet::count();
        $balanceTotal = (float) Wallet::sum('balance');

        $transactionsByType = Transaction::selectRaw('type, count(*) as total, sum(amount) as amount_total')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $lines = [
            '# HELP wallet_users_total Total number of registered users.',
            '# TYPE wallet_users_total gauge',
            "wallet_users_total {$usersTotal}",
            '# HELP wallet_wallets_total Total number of wallets.',
            '# TYPE wallet_wallets_total gauge',
            "wallet_wallets_total {$walletsTotal}",
            '# HELP wallet_balance_total Sum of all wallet balances.',
            '# TYPE wallet_balance_total gauge',
            "wallet_balance_total {$balanceTotal}",
            '# HELP wallet_transactions_total Total number of transactions, by type.',
            '# TYPE wallet_transactions_total counter',
        ];

        foreach (['credit', 'debit'] as $type) {
            $count = $transactionsByType->get($type)?->total ?? 0;
            $lines[] = "wallet_transactions_total{type=\"{$type}\"} {$count}";
        }

        $lines[] = '# HELP wallet_transactions_amount_total Sum of transaction amounts, by type.';
        $lines[] = '# TYPE wallet_transactions_amount_total counter';

        foreach (['credit', 'debit'] as $type) {
            $amount = (float) ($transactionsByType->get($type)?->amount_total ?? 0);
            $lines[] = "wallet_transactions_amount_total{type=\"{$type}\"} {$amount}";
        }

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; version=0.0.4');
    }
}
