<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionFilterRequest;
use App\Http\Resources\TransactionResource;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Transactions', description: 'Histórico de transações')]
class TransactionController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

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
                schema: new OA\Schema(type: 'string', enum: ['credit', 'debit']),
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
        $paginator = $this->walletService->getTransactions(
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
}
