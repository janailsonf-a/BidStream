<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'BidStream API',
    version: '1.0.0',
    description: 'API de leilão em tempo real com Laravel, Redis Atomic Lock, Laravel Reverb e finalização automática de leilões.'
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000',
    description: 'Servidor local'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Token'
)]
class OpenApi
{
}