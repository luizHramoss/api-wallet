<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\WalletResource;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Wallet", description="Operações da carteira digital")
 */
class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    /**
     * @OA\Get(
     *     path="/api/wallet",
     *     tags={"Wallet"},
     *     summary="Consultar saldo da carteira",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Saldo consultado com sucesso"),
     *     @OA\Response(response=401, description="Não autenticado")
     * )
     */
    public function show(Request $request): JsonResponse
    {
        $wallet = $request->user()->wallet;
        return response()->json([
            'success' => true,
            'message' => 'Saldo consultado com sucesso.',
            'data'    => new WalletResource($wallet),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/wallet/deposit",
     *     tags={"Wallet"},
     *     summary="Realizar depósito",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", format="float", example=100.50)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Depósito realizado com sucesso"),
     *     @OA\Response(response=422, description="Valor inválido"),
     *     @OA\Response(response=401, description="Não autenticado")
     * )
     */
    public function deposit(TransactionRequest $request): JsonResponse
    {
        $transaction = $this->walletService->deposit(
            $request->user(),
            (float) $request->validated('amount')
        );

        return response()->json([
            'success' => true,
            'message' => 'Depósito realizado com sucesso.',
            'data'    => new TransactionResource($transaction),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/wallet/withdraw",
     *     tags={"Wallet"},
     *     summary="Realizar saque",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", format="float", example=50.00)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Saque realizado com sucesso"),
     *     @OA\Response(response=422, description="Saldo insuficiente ou valor inválido"),
     *     @OA\Response(response=401, description="Não autenticado")
     * )
     */
    public function withdraw(TransactionRequest $request): JsonResponse
    {
        $transaction = $this->walletService->withdraw(
            $request->user(),
            (float) $request->validated('amount')
        );

        return response()->json([
            'success' => true,
            'message' => 'Saque realizado com sucesso.',
            'data'    => new TransactionResource($transaction),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/wallet/dashboard",
     *     tags={"Wallet"},
     *     summary="Dashboard da carteira",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Dashboard carregado com sucesso"),
     *     @OA\Response(response=401, description="Não autenticado")
     * )
     */
    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->walletService->getDashboard($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Dashboard carregado com sucesso.',
            'data'    => [
                'balance'           => (float) $data['balance'],
                'last_transactions' => TransactionResource::collection($data['last_transactions']),
                'monthly_summary'   => $data['monthly_summary'],
            ],
        ]);
    }
}
