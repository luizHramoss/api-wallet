<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecurringBillStoreRequest;
use App\Http\Requests\RecurringBillUpdateRequest;
use App\Http\Resources\RecurringBillResource;
use App\Services\RecurringBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'RecurringBills', description: 'Contas fixas / recorrências (aluguel, assinaturas, etc.)')]
class RecurringBillController extends Controller
{
    public function __construct(private readonly RecurringBillService $recurringBillService) {}

    #[OA\Get(
        path: '/api/recurring-bills',
        tags: ['RecurringBills'],
        summary: 'Listar contas fixas do usuário',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Contas fixas listadas com sucesso'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $bills = $this->recurringBillService->listFor($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Contas fixas listadas com sucesso.',
            'data' => RecurringBillResource::collection($bills),
        ]);
    }

    #[OA\Post(
        path: '/api/recurring-bills',
        tags: ['RecurringBills'],
        summary: 'Criar conta fixa',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['account_id', 'name', 'type', 'amount', 'day_of_month', 'start_date'],
                properties: [
                    new OA\Property(property: 'account_id', type: 'integer'),
                    new OA\Property(property: 'category_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'name', type: 'string', example: 'Aluguel'),
                    new OA\Property(property: 'type', type: 'string', enum: ['income', 'expense']),
                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 1500),
                    new OA\Property(property: 'day_of_month', type: 'integer', example: 5),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'paused', 'cancelled']),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Conta fixa criada com sucesso'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ],
    )]
    public function store(RecurringBillStoreRequest $request): JsonResponse
    {
        $bill = $this->recurringBillService->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Conta fixa criada com sucesso.',
            'data' => new RecurringBillResource($bill),
        ], 201);
    }

    #[OA\Patch(
        path: '/api/recurring-bills/{recurring_bill}',
        tags: ['RecurringBills'],
        summary: 'Atualizar conta fixa',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'recurring_bill', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Conta fixa atualizada com sucesso'),
            new OA\Response(response: 404, description: 'Conta fixa não encontrada'),
        ],
    )]
    public function update(RecurringBillUpdateRequest $request, int $recurringBill): JsonResponse
    {
        $model = $request->user()->recurringBills()->findOrFail($recurringBill);
        $updated = $this->recurringBillService->update($model, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Conta fixa atualizada com sucesso.',
            'data' => new RecurringBillResource($updated),
        ]);
    }

    #[OA\Delete(
        path: '/api/recurring-bills/{recurring_bill}',
        tags: ['RecurringBills'],
        summary: 'Excluir conta fixa',
        description: 'Ocorrências (transações) já geradas continuam existindo, só perdem o vínculo.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'recurring_bill', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Conta fixa excluída com sucesso'),
            new OA\Response(response: 404, description: 'Conta fixa não encontrada'),
        ],
    )]
    public function destroy(Request $request, int $recurringBill): JsonResponse
    {
        $model = $request->user()->recurringBills()->findOrFail($recurringBill);
        $this->recurringBillService->delete($model);

        return response()->json([
            'success' => true,
            'message' => 'Conta fixa excluída com sucesso.',
        ]);
    }
}
