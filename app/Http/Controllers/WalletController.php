<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\TransactionResource;
use App\Services\AccountService;
use App\Services\DashboardService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Wallet', description: 'Operações da carteira digital (conta principal do usuário)')]
class WalletController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly TransactionService $transactionService,
        private readonly DashboardService $dashboardService,
    ) {}

    #[OA\Get(
        path: '/api/wallet',
        tags: ['Wallet'],
        summary: 'Consultar saldo da carteira',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Saldo consultado com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        $account = $this->accountService->firstAccountFor($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Saldo consultado com sucesso.',
            'data' => new AccountResource($account),
        ]);
    }

    #[OA\Post(
        path: '/api/wallet/deposit',
        tags: ['Wallet'],
        summary: 'Realizar depósito',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount'],
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 100.50),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Depósito realizado com sucesso'),
            new OA\Response(response: 422, description: 'Valor inválido'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function deposit(TransactionRequest $request): JsonResponse
    {
        $transaction = $this->transactionService->deposit(
            $request->user(),
            (float) $request->validated('amount')
        );

        return response()->json([
            'success' => true,
            'message' => 'Depósito realizado com sucesso.',
            'data' => new TransactionResource($transaction),
        ]);
    }

    #[OA\Post(
        path: '/api/wallet/withdraw',
        tags: ['Wallet'],
        summary: 'Realizar saque',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount'],
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 50.00),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Saque realizado com sucesso'),
            new OA\Response(response: 422, description: 'Saldo insuficiente ou valor inválido'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function withdraw(TransactionRequest $request): JsonResponse
    {
        $transaction = $this->transactionService->withdraw(
            $request->user(),
            (float) $request->validated('amount')
        );

        return response()->json([
            'success' => true,
            'message' => 'Saque realizado com sucesso.',
            'data' => new TransactionResource($transaction),
        ]);
    }

    #[OA\Get(
        path: '/api/wallet/dashboard',
        tags: ['Wallet'],
        summary: 'Dashboard da carteira',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard carregado com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getDashboard($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Dashboard carregado com sucesso.',
            'data' => [
                'balance' => (float) $data['balance'],
                'last_transactions' => TransactionResource::collection($data['last_transactions']),
                'monthly_summary' => $data['monthly_summary'],
            ],
        ]);
    }
}
