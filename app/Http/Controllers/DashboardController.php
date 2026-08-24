<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Dashboard', description: 'Visão consolidada das finanças do usuário')]
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    #[OA\Get(
        path: '/api/dashboard',
        tags: ['Dashboard'],
        summary: 'Dashboard consolidado',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard carregado com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getDashboard($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Dashboard carregado com sucesso.',
            'data' => [
                'balance' => (float) $data['balance'],
                'last_transactions' => TransactionResource::collection($data['last_transactions']),
                'monthly_summary' => $data['monthly_summary'],
                'investments' => $data['investments'],
            ],
        ]);
    }
}
