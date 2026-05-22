<?php

namespace App\Swagger;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Digital Wallet API",
 *     description="API RESTful para carteira digital pessoal. Permite autenticação, depósitos, saques e consulta de histórico financeiro.",
 *     @OA\Contact(email="dev@digitalwallet.com"),
 *     @OA\License(name="MIT")
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Servidor principal"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Token de autenticação Sanctum. Envie como: Authorization: Bearer {token}"
 * )
 *
 * @OA\Schema(
 *     schema="ApiResponse",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Operation completed successfully"),
 *     @OA\Property(property="data", type="object")
 * )
 *
 * @OA\Schema(
 *     schema="ApiError",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Mensagem de erro"),
 *     @OA\Property(property="errors", type="object", nullable=true)
 * )
 */
class OpenApi
{
}
