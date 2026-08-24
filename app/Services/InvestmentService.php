<?php

namespace App\Services;

use App\Exceptions\InsufficientQuantityException;
use App\Exceptions\InvalidTransactionException;
use App\Models\Investment;
use App\Models\InvestmentMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class InvestmentService
{
    public function __construct(private readonly AccountService $accountService) {}

    public function listFor(User $user): Collection
    {
        return $user->investments()->orderBy('name')->get();
    }

    /**
     * Cria só o "casco" do ativo (sem posição ainda) - a primeira compra é
     * registrada separadamente via recordMovement(), igual qualquer outra,
     * pra manter o ledger de movimentos como única fonte de verdade da
     * posição (quantidade/preço médio nunca são setados diretamente).
     */
    public function create(User $user, array $data): Investment
    {
        return $user->investments()->create([
            'account_id' => $data['account_id'],
            'name' => $data['name'],
            'symbol' => $data['symbol'] ?? null,
            'type' => $data['type'],
        ]);
    }

    /**
     * Atualiza metadados do ativo e/ou o preço atual (cotação manual, sem
     * integração automática por enquanto). Não mexe em quantity/average_price
     * - isso só muda através de movimentos.
     */
    public function update(Investment $investment, array $data): Investment
    {
        $investment->fill(array_intersect_key($data, array_flip(['name', 'symbol', 'type', 'current_price'])));
        $investment->save();

        return $investment;
    }

    public function delete(Investment $investment): void
    {
        $investment->delete();
    }

    /**
     * Registra uma compra/venda/dividendo, ajustando a posição (preço médio
     * ponderado) e o saldo da conta vinculada de forma atômica:
     * - buy: debita a conta (dinheiro saiu pra comprar), aumenta quantidade,
     *   recalcula o preço médio ponderado.
     * - sell: credita a conta (dinheiro voltou), reduz quantidade - preço
     *   médio do que sobra não muda (custo médio ponderado padrão).
     * - dividend: credita a conta, não mexe em quantidade/preço médio.
     *
     * @throws InsufficientQuantityException|InvalidTransactionException|Throwable
     */
    public function recordMovement(Investment $investment, array $data): InvestmentMovement
    {
        $type = $data['type'];
        $occurredAt = $data['occurred_at'] ?? Carbon::today();

        return DB::transaction(function () use ($investment, $type, $data, $occurredAt) {
            $account = $this->accountService->lockById($investment->account_id);
            $investment = Investment::lockForUpdate()->findOrFail($investment->id);

            if ($type === InvestmentMovement::TYPE_DIVIDEND) {
                $amount = (float) $data['amount'];
                $this->assertPositiveAmount($amount);
                $this->accountService->credit($account, $amount);

                return $investment->movements()->create([
                    'type' => $type,
                    'quantity' => null,
                    'price' => null,
                    'amount' => $amount,
                    'occurred_at' => $occurredAt,
                ]);
            }

            $quantity = (float) $data['quantity'];
            $price = (float) $data['price'];
            $this->assertPositiveAmount($quantity);
            $this->assertPositiveAmount($price);
            $amount = round($quantity * $price, 2);

            if ($type === InvestmentMovement::TYPE_BUY) {
                $this->accountService->debit($account, $amount);

                $newQuantity = (float) $investment->quantity + $quantity;
                $newAveragePrice = (((float) $investment->quantity * (float) $investment->average_price) + $amount) / $newQuantity;

                $investment->quantity = $newQuantity;
                $investment->average_price = round($newAveragePrice, 4);
                $investment->save();
            } else { // sell
                if ($quantity > (float) $investment->quantity) {
                    throw new InsufficientQuantityException(
                        'Quantidade insuficiente. Você possui '.number_format((float) $investment->quantity, 8, ',', '.').' unidades.'
                    );
                }

                $this->accountService->credit($account, $amount);

                $investment->quantity = (float) $investment->quantity - $quantity;
                $investment->save();
            }

            return $investment->movements()->create([
                'type' => $type,
                'quantity' => $quantity,
                'price' => $price,
                'amount' => $amount,
                'occurred_at' => $occurredAt,
            ]);
        });
    }

    /**
     * Resumo da carteira: totais investidos/atuais, rentabilidade média
     * ponderada, e quanto entrou (compras) e saiu/voltou (dividendos) em
     * caixa este mês - a resposta direta pra "quanto guardei/investi
     * esse mês".
     */
    public function portfolioSummary(User $user): array
    {
        $investments = $this->listFor($user);

        $totalInvested = round($investments->sum(fn (Investment $i) => $i->investedValue()), 2);
        $totalCurrent = round($investments->sum(fn (Investment $i) => $i->currentValue()), 2);
        $rentabilityPercent = $totalInvested > 0
            ? round((($totalCurrent - $totalInvested) / $totalInvested) * 100, 2)
            : null;

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $investedThisMonth = (float) InvestmentMovement::query()
            ->whereHas('investment', fn ($q) => $q->where('user_id', $user->id))
            ->where('type', InvestmentMovement::TYPE_BUY)
            ->whereBetween('occurred_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $dividendsThisMonth = (float) InvestmentMovement::query()
            ->whereHas('investment', fn ($q) => $q->where('user_id', $user->id))
            ->where('type', InvestmentMovement::TYPE_DIVIDEND)
            ->whereBetween('occurred_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        return [
            'total_invested' => $totalInvested,
            'total_current_value' => $totalCurrent,
            'rentability_percent' => $rentabilityPercent,
            'invested_this_month' => round($investedThisMonth, 2),
            'dividends_this_month' => round($dividendsThisMonth, 2),
            'period' => [
                'from' => $startOfMonth->toDateString(),
                'to' => $endOfMonth->toDateString(),
            ],
        ];
    }

    /**
     * @throws InvalidTransactionException
     */
    private function assertPositiveAmount(float $value): void
    {
        if ($value <= 0) {
            throw new InvalidTransactionException('O valor deve ser maior que zero.');
        }
    }
}
