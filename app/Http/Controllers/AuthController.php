<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Auth', description: 'Autenticação de usuários')]
class AuthController extends Controller
{
    public function __construct(private readonly AccountService $accountService) {}

    #[OA\Post(
        path: '/api/auth/register',
        tags: ['Auth'],
        summary: 'Registrar novo usuário',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'João Silva'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'joao@email.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'senha123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'senha123'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuário registrado com sucesso'),
            new OA\Response(response: 422, description: 'Dados inválidos'),
        ],
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        $this->accountService->createDefaultForUser($user);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Usuário registrado com sucesso.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ], 201);
    }

    #[OA\Post(
        path: '/api/auth/login',
        tags: ['Auth'],
        summary: 'Login do usuário',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'joao@email.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'senha123'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login realizado com sucesso'),
            new OA\Response(response: 401, description: 'Credenciais inválidas'),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciais inválidas.',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login realizado com sucesso.',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/auth/logout',
        tags: ['Auth'],
        summary: 'Logout do usuário',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logout realizado com sucesso'),
            new OA\Response(response: 401, description: 'Não autenticado'),
        ],
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout realizado com sucesso.',
        ]);
    }
}
