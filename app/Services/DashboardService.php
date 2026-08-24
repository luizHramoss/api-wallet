<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

class DashboardService
{
    /**
     * Retorna os dados do dashboard: saldo total, últimas transações, totais do mês.
     *
     * Fase 1: agrega através de todas as contas do usuário. As Fases seguintes
     * (cartões, investimentos, planejamento) estendem este método com mais dados
     * consolidados, sem mudar o formato já retornado aqui.
     */
    public function getDashboard(User $user): array
    {
        $totalBalance = $user->accounts()->where('is_archived', false)->sum('balance');

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $monthTransactions = Transaction::query()
            ->whereHas('account', fn ($q) => $q->where('user_id', $user->id))
            ->where('status', Transaction::STATUS_REALIZED)
            ->whereBetween('occurred_at', [$startOfMonth, $endOfMonth]);

        $totalIncome = (clone $monthTransactions)
            ->where('type', Transaction::TYPE_INCOME)
            ->sum('amount');

        $totalExpense = (clone $monthTransactions)
            ->where('type', Transaction::TYPE_EXPENSE)
            ->sum('amount');

        $lastTransactions = Transaction::query()
            ->whereHas('account', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return [
            'balance' => round((float) $totalBalance, 2),
            'last_transactions' => $lastTransactions,
            'monthly_summary' => [
                'total_income' => round((float) $totalIncome, 2),
                'total_expense' => round((float) $totalExpense, 2),
                'period' => [
                    'from' => $startOfMonth->toDateString(),
                    'to' => $endOfMonth->toDateString(),
                ],
            ],
        ];
    }
}
