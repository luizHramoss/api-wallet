<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionFilterRequest;
use App\Http\Requests\TransactionStoreRequest;
use App\Http\Requests\TransactionUpdateRequest;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Transactions', description: 'Receitas, despesas e transferências')]
class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transactionService) {}

    #[OA\Get(
        path: '/api/transactions',
        tags: ['Transactions'],
        summary: 'Listar transações paginadas',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'type',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['income', 'expense', 'transfer']),
            ),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['planned', 'realized']),
            ),
            new OA\Parameter(
                name: 'category_id',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\Parameter(
                name: 'account_id',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\Parameter(
                name: 'date_from',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-01'),
            ),
            new OA\Parameter(
                name: 'date_to',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-31'),
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 15),
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Transações listadas com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function index(TransactionFilterRequest $request): JsonResponse
    {
        $paginator = $this->transactionService->getTransactions(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'Transações listadas com sucesso.',
            'data' => TransactionResource::collection($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/transactions',
        tags: ['Transactions'],
        summary: 'Criar transação (receita, despesa ou transferência)',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['account_id', 'type', 'amount'],
                properties: [
                    new OA\Property(property: 'account_id', type: 'integer'),
                    new OA\Property(property: 'to_account_id', type: 'integer', description: 'Obrigatório quando type=transfer'),
                    new OA\Property(property: 'category_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'type', type: 'string', enum: ['income', 'expense', 'transfer']),
                    new OA\Property(property: 'status', type: 'string', enum: ['planned', 'realized']),
                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 150.00),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'occurred_at', type: 'string', format: 'date'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Transação criada com sucesso'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ],
    )]
    public function store(TransactionStoreRequest $request): JsonResponse
    {
        $transaction = $this->transactionService->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Transação criada com sucesso.',
            'data' => new TransactionResource($transaction),
        ], 201);
    }

    #[OA\Patch(
        path: '/api/transactions/{transaction}',
        tags: ['Transactions'],
        summary: 'Atualizar transação',
        description: 'Transferências não podem ser editadas por aqui - exclua e crie novamente.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'transaction', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Transação atualizada com sucesso'),
            new OA\Response(response: 404, description: 'Transação não encontrada'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ],
    )]
    public function update(TransactionUpdateRequest $request, int $transaction): JsonResponse
    {
        $model = $request->user()->transactions()->findOrFail($transaction);
        $updated = $this->transactionService->update($model, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Transação atualizada com sucesso.',
            'data' => new TransactionResource($updated),
        ]);
    }

    #[OA\Delete(
        path: '/api/transactions/{transaction}',
        tags: ['Transactions'],
        summary: 'Excluir transação',
        description: 'Reverte o impacto no saldo da conta se a transação estava realizada.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'transaction', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Transação excluída com sucesso'),
            new OA\Response(response: 404, description: 'Transação não encontrada'),
        ],
    )]
    public function destroy(Request $request, int $transaction): JsonResponse
    {
        $model = $request->user()->transactions()->findOrFail($transaction);
        $this->transactionService->delete($model);

        return response()->json([
            'success' => true,
            'message' => 'Transação excluída com sucesso.',
        ]);
    }
}
