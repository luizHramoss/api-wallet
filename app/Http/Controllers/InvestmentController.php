<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvestmentMovementStoreRequest;
use App\Http\Requests\InvestmentStoreRequest;
use App\Http\Requests\InvestmentUpdateRequest;
use App\Http\Resources\InvestmentMovementResource;
use App\Http\Resources\InvestmentResource;
use App\Services\InvestmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Investments', description: 'Carteira de investimentos - ativos e movimentos (compra/venda/dividendo)')]
class InvestmentController extends Controller
{
    public function __construct(private readonly InvestmentService $investmentService) {}

    #[OA\Get(
        path: '/api/investments',
        tags: ['Investments'],
        summary: 'Listar ativos da carteira',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Ativos listados com sucesso'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $investments = $this->investmentService->listFor($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Ativos listados com sucesso.',
            'data' => InvestmentResource::collection($investments),
        ]);
    }

    #[OA\Get(
        path: '/api/investments/summary',
        tags: ['Investments'],
        summary: 'Resumo da carteira (total investido, valor atual, rentabilidade, investido no mês)',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Resumo carregado com sucesso'),
        ],
    )]
    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Resumo da carteira carregado com sucesso.',
            'data' => $this->investmentService->portfolioSummary($request->user()),
        ]);
    }

    #[OA\Post(
        path: '/api/investments',
        tags: ['Investments'],
        summary: 'Criar ativo',
        description: 'Cria só o ativo (posição zerada) - registre a primeira compra via POST /api/investments/{investment}/movements.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['account_id', 'name', 'type'],
                properties: [
                    new OA\Property(property: 'account_id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string', example: 'Petrobras PN'),
                    new OA\Property(property: 'symbol', type: 'string', example: 'PETR4', nullable: true),
                    new OA\Property(property: 'type', type: 'string', enum: ['stock', 'fixed_income', 'fund', 'crypto', 'other']),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Ativo criado com sucesso'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ],
    )]
    public function store(InvestmentStoreRequest $request): JsonResponse
    {
        $investment = $this->investmentService->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Ativo criado com sucesso.',
            'data' => new InvestmentResource($investment),
        ], 201);
    }

    #[OA\Patch(
        path: '/api/investments/{investment}',
        tags: ['Investments'],
        summary: 'Atualizar ativo (metadados e/ou cotação manual)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'investment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ativo atualizado com sucesso'),
            new OA\Response(response: 404, description: 'Ativo não encontrado'),
        ],
    )]
    public function update(InvestmentUpdateRequest $request, int $investment): JsonResponse
    {
        $model = $request->user()->investments()->findOrFail($investment);
        $updated = $this->investmentService->update($model, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Ativo atualizado com sucesso.',
            'data' => new InvestmentResource($updated),
        ]);
    }

    #[OA\Delete(
        path: '/api/investments/{investment}',
        tags: ['Investments'],
        summary: 'Excluir ativo',
        description: 'Exclui o ativo e todo o histórico de movimentos dele (não reverte o saldo da conta já movimentado).',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'investment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ativo excluído com sucesso'),
            new OA\Response(response: 404, description: 'Ativo não encontrado'),
        ],
    )]
    public function destroy(Request $request, int $investment): JsonResponse
    {
        $model = $request->user()->investments()->findOrFail($investment);
        $this->investmentService->delete($model);

        return response()->json([
            'success' => true,
            'message' => 'Ativo excluído com sucesso.',
        ]);
    }

    #[OA\Get(
        path: '/api/investments/{investment}/movements',
        tags: ['Investments'],
        summary: 'Listar movimentos (compras/vendas/dividendos) de um ativo',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'investment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Movimentos listados com sucesso'),
            new OA\Response(response: 404, description: 'Ativo não encontrado'),
        ],
    )]
    public function movements(Request $request, int $investment): JsonResponse
    {
        $model = $request->user()->investments()->findOrFail($investment);
        $movements = $model->movements()->orderByDesc('occurred_at')->orderByDesc('id')->get();

        return response()->json([
            'success' => true,
            'message' => 'Movimentos listados com sucesso.',
            'data' => InvestmentMovementResource::collection($movements),
        ]);
    }

    #[OA\Post(
        path: '/api/investments/{investment}/movements',
        tags: ['Investments'],
        summary: 'Registrar compra, venda ou dividendo',
        description: 'Compra debita a conta vinculada; venda e dividendo creditam. Compra/venda recalculam quantidade e preço médio.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'investment', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['buy', 'sell', 'dividend']),
                    new OA\Property(property: 'quantity', type: 'number', format: 'float', description: 'Obrigatório em buy/sell'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', description: 'Obrigatório em buy/sell'),
                    new OA\Property(property: 'amount', type: 'number', format: 'float', description: 'Obrigatório em dividend'),
                    new OA\Property(property: 'occurred_at', type: 'string', format: 'date'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Movimento registrado com sucesso'),
            new OA\Response(response: 404, description: 'Ativo não encontrado'),
            new OA\Response(response: 422, description: 'Dados inválidos ou quantidade/saldo insuficiente'),
        ],
    )]
    public function storeMovement(InvestmentMovementStoreRequest $request, int $investment): JsonResponse
    {
        $model = $request->user()->investments()->findOrFail($investment);
        $movement = $this->investmentService->recordMovement($model, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Movimento registrado com sucesso.',
            'data' => new InvestmentMovementResource($movement),
        ], 201);
    }
}
