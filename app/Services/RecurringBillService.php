<?php

namespace App\Services;

use App\Models\RecurringBill;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class RecurringBillService
{
    public function listFor(User $user): Collection
    {
        return $user->recurringBills()->with('category', 'account')->orderBy('day_of_month')->get();
    }

    public function create(User $user, array $data): RecurringBill
    {
        return $user->recurringBills()->create([
            'account_id' => $data['account_id'],
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'day_of_month' => $data['day_of_month'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'status' => $data['status'] ?? RecurringBill::STATUS_ACTIVE,
        ]);
    }

    public function update(RecurringBill $bill, array $data): RecurringBill
    {
        $bill->fill(array_intersect_key($data, array_flip([
            'account_id', 'category_id', 'name', 'type', 'amount',
            'day_of_month', 'start_date', 'end_date', 'status',
        ])));
        $bill->save();

        return $bill;
    }

    /**
     * Exclui a conta fixa. Ocorrências já geradas (Transaction.recurring_bill_id)
     * continuam existindo, só perdem o vínculo (nullOnDelete) - excluir uma
     * recorrência não deve apagar histórico financeiro já lançado.
     */
    public function delete(RecurringBill $bill): void
    {
        $bill->delete();
    }

    /**
     * Materializa, para cada conta fixa ativa, as ocorrências (Transaction
     * status=planned) que faltam gerar desde a última geração até um mês à
     * frente - dá visibilidade do comprometido antes mesmo do vencimento
     * chegar. Idempotente: cada conta fixa lembra até onde já gerou
     * (last_generated_at), então rodar de novo no mesmo dia não duplica.
     *
     * @return int total de ocorrências geradas nesta execução
     */
    public function generateDueOccurrences(): int
    {
        $horizon = Carbon::today()->addMonthNoOverflow();
        $total = 0;

        RecurringBill::where('status', RecurringBill::STATUS_ACTIVE)
            ->chunkById(50, function (Collection $bills) use (&$total, $horizon) {
                foreach ($bills as $bill) {
                    $total += $this->generateForBill($bill, $horizon);
                }
            });

        return $total;
    }

    private function generateForBill(RecurringBill $bill, Carbon $horizon): int
    {
        // Sem geração prévia: começa no mês atual (ou no mês de início, se a
        // conta fixa só começa no futuro) - nunca no passado, pra não gerar
        // em massa meses antigos quando uma conta fixa antiga é cadastrada.
        $cursor = $bill->last_generated_at
            ? Carbon::parse($bill->last_generated_at)->startOfMonth()->addMonthNoOverflow()
            : Carbon::parse($bill->start_date)->startOfMonth()->max(Carbon::today()->startOfMonth());

        $generated = 0;

        while ($cursor->lte($horizon)) {
            if ($bill->end_date && $cursor->gt(Carbon::parse($bill->end_date))) {
                break;
            }

            $occurrenceDate = $this->clampToMonth($cursor, (int) $bill->day_of_month);

            if ($occurrenceDate->gte(Carbon::parse($bill->start_date))) {
                Transaction::create([
                    'account_id' => $bill->account_id,
                    'category_id' => $bill->category_id,
                    'recurring_bill_id' => $bill->id,
                    'type' => $bill->type,
                    'status' => Transaction::STATUS_PLANNED,
                    'amount' => $bill->amount,
                    'description' => $bill->name,
                    'occurred_at' => $occurrenceDate,
                ]);

                $bill->last_generated_at = $occurrenceDate;
                $generated++;
            }

            $cursor = $cursor->copy()->addMonthNoOverflow();
        }

        if ($generated > 0) {
            $bill->save();
        }

        return $generated;
    }

    private function clampToMonth(Carbon $month, int $dayOfMonth): Carbon
    {
        $day = min($dayOfMonth, $month->daysInMonth);

        return $month->copy()->startOfMonth()->addDays($day - 1);
    }
}
