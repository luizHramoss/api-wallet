<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryStoreRequest;
use App\Http\Requests\CategoryUpdateRequest;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Categories', description: 'Categorias e subcategorias de receitas/despesas')]
class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService) {}

    #[OA\Get(
        path: '/api/categories',
        tags: ['Categories'],
        summary: 'Listar categorias do usuário',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'type',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['income', 'expense']),
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Categorias listadas com sucesso'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->listFor($request->user(), $request->query('type'));

        return response()->json([
            'success' => true,
            'message' => 'Categorias listadas com sucesso.',
            'data' => CategoryResource::collection($categories),
        ]);
    }

    #[OA\Post(
        path: '/api/categories',
        tags: ['Categories'],
        summary: 'Criar categoria',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'type'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Alimentação'),
                    new OA\Property(property: 'type', type: 'string', enum: ['income', 'expense']),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'icon', type: 'string', example: '🍔'),
                    new OA\Property(property: 'color', type: 'string', example: '#F59E0B'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Categoria criada com sucesso'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ],
    )]
    public function store(CategoryStoreRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Categoria criada com sucesso.',
            'data' => new CategoryResource($category),
        ], 201);
    }

    #[OA\Patch(
        path: '/api/categories/{category}',
        tags: ['Categories'],
        summary: 'Atualizar categoria',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Categoria atualizada com sucesso'),
            new OA\Response(response: 404, description: 'Categoria não encontrada'),
        ],
    )]
    public function update(CategoryUpdateRequest $request, int $category): JsonResponse
    {
        $model = $request->user()->categories()->findOrFail($category);
        $updated = $this->categoryService->update($model, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Categoria atualizada com sucesso.',
            'data' => new CategoryResource($updated),
        ]);
    }

    #[OA\Delete(
        path: '/api/categories/{category}',
        tags: ['Categories'],
        summary: 'Excluir categoria',
        description: 'Transações e subcategorias vinculadas ficam sem categoria (não são excluídas).',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Categoria excluída com sucesso'),
            new OA\Response(response: 404, description: 'Categoria não encontrada'),
        ],
    )]
    public function destroy(Request $request, int $category): JsonResponse
    {
        $model = $request->user()->categories()->findOrFail($category);
        $this->categoryService->delete($model);

        return response()->json([
            'success' => true,
            'message' => 'Categoria excluída com sucesso.',
        ]);
    }
}
