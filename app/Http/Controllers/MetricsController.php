<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Response;

class MetricsController extends Controller
{
    public function index(): Response
    {
        $usersTotal = User::count();
        $accountsTotal = Account::count();
        $balanceTotal = (float) Account::sum('balance');
        $recurringBillsActiveTotal = RecurringBill::where('status', RecurringBill::STATUS_ACTIVE)->count();

        $transactionsByType = Transaction::selectRaw('type, count(*) as total, sum(amount) as amount_total')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $lines = [
            '# HELP wallet_users_total Total number of registered users.',
            '# TYPE wallet_users_total gauge',
            "wallet_users_total {$usersTotal}",
            '# HELP wallet_accounts_total Total number of accounts.',
            '# TYPE wallet_accounts_total gauge',
            "wallet_accounts_total {$accountsTotal}",
            '# HELP wallet_balance_total Sum of all account balances.',
            '# TYPE wallet_balance_total gauge',
            "wallet_balance_total {$balanceTotal}",
            '# HELP wallet_recurring_bills_active_total Total number of active recurring bills.',
            '# TYPE wallet_recurring_bills_active_total gauge',
            "wallet_recurring_bills_active_total {$recurringBillsActiveTotal}",
            '# HELP wallet_transactions_total Total number of transactions, by type.',
            '# TYPE wallet_transactions_total counter',
        ];

        foreach (Transaction::TYPES as $type) {
            $count = $transactionsByType->get($type)?->total ?? 0;
            $lines[] = "wallet_transactions_total{type=\"{$type}\"} {$count}";
        }

        $lines[] = '# HELP wallet_transactions_amount_total Sum of transaction amounts, by type.';
        $lines[] = '# TYPE wallet_transactions_amount_total counter';

        foreach (Transaction::TYPES as $type) {
            $amount = (float) ($transactionsByType->get($type)?->amount_total ?? 0);
            $lines[] = "wallet_transactions_amount_total{type=\"{$type}\"} {$amount}";
        }

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; version=0.0.4');
    }
}
