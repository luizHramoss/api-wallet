<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Digital Wallet API',
    description: 'API RESTful para carteira digital pessoal. Permite autenticação, depósitos, saques e consulta de histórico financeiro.',
    contact: new OA\Contact(email: 'dev@digitalwallet.com'),
    license: new OA\License(name: 'MIT'),
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'Servidor principal',
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Token de autenticação Sanctum. Envie como: Authorization: Bearer {token}',
)]
#[OA\Schema(
    schema: 'ApiResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Operation completed successfully'),
        new OA\Property(property: 'data', type: 'object'),
    ],
)]
#[OA\Schema(
    schema: 'ApiError',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Mensagem de erro'),
        new OA\Property(property: 'errors', type: 'object', nullable: true),
    ],
)]
class OpenApi {}
