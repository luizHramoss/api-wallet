<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountStoreRequest;
use App\Http\Requests\AccountUpdateRequest;
use App\Http\Resources\AccountResource;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Accounts', description: 'Contas do usuário (corrente, poupança, dinheiro, investimento)')]
class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accountService) {}

    #[OA\Get(
        path: '/api/accounts',
        tags: ['Accounts'],
        summary: 'Listar contas do usuário',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Contas listadas com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $accounts = $this->accountService->listFor($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Contas listadas com sucesso.',
            'data' => AccountResource::collection($accounts),
        ]);
    }

    #[OA\Post(
        path: '/api/accounts',
        tags: ['Accounts'],
        summary: 'Criar nova conta',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'type'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Nubank'),
                    new OA\Property(property: 'type', type: 'string', enum: ['checking', 'savings', 'cash', 'investment']),
                    new OA\Property(property: 'balance', type: 'number', format: 'float', example: 0),
                    new OA\Property(property: 'color', type: 'string', example: '#8A05BE'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Conta criada com sucesso'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ],
    )]
    public function store(AccountStoreRequest $request): JsonResponse
    {
        $account = $this->accountService->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Conta criada com sucesso.',
            'data' => new AccountResource($account),
        ], 201);
    }

    #[OA\Patch(
        path: '/api/accounts/{account}',
        tags: ['Accounts'],
        summary: 'Atualizar conta',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'account', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Conta atualizada com sucesso'),
            new OA\Response(response: 404, description: 'Conta não encontrada'),
        ],
    )]
    public function update(AccountUpdateRequest $request, int $account): JsonResponse
    {
        $model = $request->user()->accounts()->findOrFail($account);
        $updated = $this->accountService->update($model, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Conta atualizada com sucesso.',
            'data' => new AccountResource($updated),
        ]);
    }

    #[OA\Delete(
        path: '/api/accounts/{account}',
        tags: ['Accounts'],
        summary: 'Arquivar conta',
        description: 'A conta é arquivada (soft), não excluída - preserva o histórico de transações.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'account', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Conta arquivada com sucesso'),
            new OA\Response(response: 404, description: 'Conta não encontrada'),
        ],
    )]
    public function destroy(Request $request, int $account): JsonResponse
    {
        $model = $request->user()->accounts()->findOrFail($account);
        $this->accountService->archive($model);

        return response()->json([
            'success' => true,
            'message' => 'Conta arquivada com sucesso.',
        ]);
    }
}
