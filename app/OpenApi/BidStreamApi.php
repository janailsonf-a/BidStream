<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Auth',
    description: 'Autenticação de usuários'
)]
#[OA\Tag(
    name: 'Auctions',
    description: 'Gerenciamento de leilões'
)]
#[OA\Tag(
    name: 'Bids',
    description: 'Lances em leilões'
)]
#[OA\Post(
    path: '/api/register',
    tags: ['Auth'],
    summary: 'Registrar usuário',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Janailson'),
                new OA\Property(property: 'email', type: 'string', example: 'janailson@example.com'),
                new OA\Property(property: 'password', type: 'string', example: '12345678'),
                new OA\Property(property: 'password_confirmation', type: 'string', example: '12345678'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Usuário registrado com sucesso'),
        new OA\Response(response: 422, description: 'Erro de validação'),
    ]
)]
#[OA\Post(
    path: '/api/login',
    tags: ['Auth'],
    summary: 'Login de usuário',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', example: 'janailson@example.com'),
                new OA\Property(property: 'password', type: 'string', example: '12345678'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Login realizado com sucesso'),
        new OA\Response(response: 422, description: 'Credenciais inválidas'),
    ]
)]
#[OA\Get(
    path: '/api/me',
    tags: ['Auth'],
    summary: 'Retornar usuário autenticado',
    security: [['sanctum' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Usuário autenticado'),
        new OA\Response(response: 401, description: 'Não autenticado'),
    ]
)]
#[OA\Post(
    path: '/api/logout',
    tags: ['Auth'],
    summary: 'Logout do usuário autenticado',
    security: [['sanctum' => []]],
    responses: [
        new OA\Response(response: 200, description: 'Logout realizado com sucesso'),
        new OA\Response(response: 401, description: 'Não autenticado'),
    ]
)]
#[OA\Get(
    path: '/api/auctions',
    tags: ['Auctions'],
    summary: 'Listar leilões',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(
            name: 'status',
            in: 'query',
            required: false,
            schema: new OA\Schema(type: 'string', enum: ['draft', 'active', 'finished', 'cancelled']),
            example: 'active'
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Lista paginada de leilões'),
        new OA\Response(response: 401, description: 'Não autenticado'),
    ]
)]
#[OA\Post(
    path: '/api/auctions',
    tags: ['Auctions'],
    summary: 'Criar leilão',
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'starting_price', 'starts_at', 'ends_at'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Notebook Dell Inspiron'),
                new OA\Property(property: 'description', type: 'string', example: 'Notebook usado em bom estado.'),
                new OA\Property(property: 'starting_price', type: 'number', format: 'float', example: 1500.00),
                new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', example: '2026-05-14 10:00:00'),
                new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', example: '2026-05-14 12:00:00'),
                new OA\Property(property: 'status', type: 'string', enum: ['draft', 'active', 'finished', 'cancelled'], example: 'active'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Leilão criado com sucesso'),
        new OA\Response(response: 401, description: 'Não autenticado'),
        new OA\Response(response: 422, description: 'Erro de validação'),
    ]
)]
#[OA\Get(
    path: '/api/auctions/{auction}',
    tags: ['Auctions'],
    summary: 'Exibir detalhes do leilão',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(
            name: 'auction',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer'),
            example: 1
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Detalhes do leilão'),
        new OA\Response(response: 401, description: 'Não autenticado'),
        new OA\Response(response: 404, description: 'Leilão não encontrado'),
    ]
)]
#[OA\Put(
    path: '/api/auctions/{auction}',
    tags: ['Auctions'],
    summary: 'Atualizar leilão',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(
            name: 'auction',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer'),
            example: 1
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'starting_price', 'starts_at', 'ends_at'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Notebook Dell Inspiron Atualizado'),
                new OA\Property(property: 'description', type: 'string', example: 'Notebook revisado.'),
                new OA\Property(property: 'starting_price', type: 'number', format: 'float', example: 1600.00),
                new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', example: '2026-05-14 10:00:00'),
                new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', example: '2026-05-14 13:00:00'),
                new OA\Property(property: 'status', type: 'string', enum: ['draft', 'active', 'finished', 'cancelled'], example: 'draft'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Leilão atualizado com sucesso'),
        new OA\Response(response: 403, description: 'Sem permissão'),
        new OA\Response(response: 422, description: 'Erro de validação'),
    ]
)]
#[OA\Delete(
    path: '/api/auctions/{auction}',
    tags: ['Auctions'],
    summary: 'Excluir leilão',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(
            name: 'auction',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer'),
            example: 1
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Leilão excluído com sucesso'),
        new OA\Response(response: 403, description: 'Sem permissão'),
        new OA\Response(response: 422, description: 'Leilão não pode ser excluído'),
    ]
)]
#[OA\Get(
    path: '/api/auctions/{auction}/bids',
    tags: ['Bids'],
    summary: 'Listar lances de um leilão',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(
            name: 'auction',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer'),
            example: 1
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Lista paginada de lances'),
        new OA\Response(response: 404, description: 'Leilão não encontrado'),
    ]
)]
#[OA\Post(
    path: '/api/auctions/{auction}/bids',
    tags: ['Bids'],
    summary: 'Enviar lance',
    description: 'Envia um lance para um leilão ativo. Usa Redis Atomic Lock para evitar concorrência.',
    security: [['sanctum' => []]],
    parameters: [
        new OA\Parameter(
            name: 'auction',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'integer'),
            example: 1
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['amount'],
            properties: [
                new OA\Property(property: 'amount', type: 'number', format: 'float', example: 4700),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Lance realizado com sucesso'),
        new OA\Response(response: 401, description: 'Não autenticado'),
        new OA\Response(response: 422, description: 'Lance inválido, leilão encerrado ou lock ativo'),
    ]
)]
class BidStreamApi
{
}