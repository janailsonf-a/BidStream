# BidStream — Real-Time Auction API

BidStream é uma API de leilão em tempo real desenvolvida com Laravel, Redis e Laravel Reverb. O projeto foi criado para demonstrar conceitos avançados de backend, como controle de concorrência, WebSockets, regras de negócio sensíveis a tempo, autenticação via API, testes automatizados e agendamento de tarefas.

## Visão Geral

O sistema permite que usuários criem leilões, enviem lances em tempo real e acompanhem atualizações instantâneas via WebSocket. Para evitar inconsistências em lances simultâneos, o BidStream utiliza Redis Atomic Lock combinado com transações no banco de dados.

Além disso, o projeto implementa a regra de "lance de último segundo": se um lance for feito quando faltam 10 segundos ou menos para o encerramento, o leilão é automaticamente estendido em mais 30 segundos.

## Principais Funcionalidades

- Registro e login de usuários com Laravel Sanctum
- CRUD inicial de leilões
- Envio de lances autenticados
- Bloqueio de lance do dono do próprio leilão
- Validação para impedir lances menores ou iguais ao valor atual
- Redis Atomic Lock para proteger lances simultâneos
- Transações com `lockForUpdate()` para consistência no banco
- Atualização em tempo real com Laravel Reverb e Laravel Echo
- Extensão automática em lances de último segundo
- Finalização automática de leilões expirados
- Definição automática do vencedor pelo maior lance
- Scheduler para executar finalização a cada minuto
- Testes automatizados para regras críticas de negócio

## Tecnologias Utilizadas

- PHP 8.3
- Laravel
- Laravel Sanctum
- Laravel Reverb
- Laravel Echo
- Redis
- MySQL
- Vite
- PHPUnit/Pest via `php artisan test`

## Arquitetura Principal

```txt
app/
├── Console/
│   └── Commands/
│       └── FinishExpiredAuctionsCommand.php
├── Events/
│   └── BidPlaced.php
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── AuthController.php
│   │       ├── AuctionController.php
│   │       └── BidController.php
│   └── Requests/
│       ├── StoreAuctionRequest.php
│       └── PlaceBidRequest.php
├── Models/
│   ├── Auction.php
│   ├── Bid.php
│   └── User.php
└── Services/
    └── AuctionService.php
```

## Fluxo de Lance

```txt
1. Usuário autenticado envia um lance
2. BidController recebe a requisição
3. AuctionService tenta obter um Redis Atomic Lock
4. Banco inicia transação
5. Auction é bloqueado com lockForUpdate()
6. Sistema valida:
   - se o leilão está ativo
   - se o leilão já começou
   - se o leilão ainda não encerrou
   - se o usuário não é o dono
   - se o valor é maior que o lance atual
7. Lance é registrado
8. current_price é atualizado
9. Se faltar 10 segundos ou menos, o ends_at recebe +30 segundos
10. Evento BidPlaced é transmitido via WebSocket
11. Redis Lock é liberado
```

## Diferenciais Técnicos

### Redis Atomic Lock

O projeto utiliza Redis para impedir que dois lances sejam processados simultaneamente no mesmo leilão.

Exemplo conceitual:

```php
$lock = Cache::lock("auction:{$auction->id}:bid", 5);
```

Isso garante que apenas uma operação de lance seja processada por vez para cada leilão.

### Transação com lockForUpdate

Além do Redis Lock, o sistema também usa bloqueio no nível do banco:

```php
Auction::query()
    ->whereKey($auction->id)
    ->lockForUpdate()
    ->firstOrFail();
```

Essa combinação aumenta a segurança contra condições de corrida.

### Lance de Último Segundo

Se um lance for feito quando faltam 10 segundos ou menos para o encerramento, o leilão é estendido:

```php
$secondsRemaining = now()->diffInSeconds($auction->ends_at, false);

if ($secondsRemaining > 0 && $secondsRemaining <= 10) {
    $auction->ends_at = $auction->ends_at->addSeconds(30);
}
```

### Atualização em Tempo Real

Quando um lance é aceito, o evento `BidPlaced` é transmitido no canal:

```txt
auction.{auctionId}
```

Exemplo:

```js
Echo.channel('auction.1')
    .listen('.bid.placed', (event) => {
        console.log(event);
    });
```

Payload do evento:

```json
{
  "auction_id": 1,
  "current_price": "4700.00",
  "ends_at": "2026-05-14 11:36:02",
  "last_bid": {
    "id": 11,
    "amount": "4700.00",
    "created_at": "2026-05-14 11:35:24",
    "user": {
      "id": 1,
      "name": "Comprador Teste",
      "email": "comprador@example.com"
    }
  }
}
```

## Finalização de Leilões

O comando abaixo finaliza leilões expirados e define o vencedor com base no maior lance:

```bash
php artisan auctions:finish-expired
```

O Scheduler executa esse comando automaticamente a cada minuto:

```php
Schedule::command('auctions:finish-expired')->everyMinute();
```

Para produção, configure o cron:

```bash
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

## Endpoints da API

### Autenticação

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/register` | Registra um usuário |
| POST | `/api/login` | Realiza login |
| GET | `/api/me` | Retorna o usuário autenticado |
| POST | `/api/logout` | Encerra o token atual |

### Leilões

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/auctions` | Lista leilões |
| POST | `/api/auctions` | Cria um leilão |
| GET | `/api/auctions/{auction}` | Exibe detalhes do leilão |
| PUT/PATCH | `/api/auctions/{auction}` | Atualiza um leilão |
| DELETE | `/api/auctions/{auction}` | Remove um leilão |

### Lances

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/auctions/{auction}/bids` | Lista lances do leilão |
| POST | `/api/auctions/{auction}/bids` | Envia um lance |

## Exemplos de Requisição

### Registro

```bash
curl -X POST http://127.0.0.1:8000/api/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Comprador Teste",
    "email": "comprador@example.com",
    "password": "12345678",
    "password_confirmation": "12345678"
  }'
```

### Login

```bash
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "comprador@example.com",
    "password": "12345678"
  }'
```

### Criar Leilão

```bash
curl -X POST http://127.0.0.1:8000/api/auctions \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Notebook Dell Inspiron",
    "description": "Notebook usado em bom estado.",
    "starting_price": 1500.00,
    "starts_at": "2026-05-14 10:00:00",
    "ends_at": "2026-05-14 12:00:00",
    "status": "active"
  }'
```

### Enviar Lance

```bash
curl -X POST http://127.0.0.1:8000/api/auctions/1/bids \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 4700
  }'
```

## Instalação Local

Clone o repositório:

```bash
git clone https://github.com/janailsonf-a/BidStream.git
cd BidStream
```

Instale as dependências PHP:

```bash
composer install
```

Instale as dependências JavaScript:

```bash
npm install
```

Copie o arquivo de ambiente:

```bash
cp .env.example .env
```

Gere a chave da aplicação:

```bash
php artisan key:generate
```

Configure o banco de dados no `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bidstream
DB_USERNAME=root
DB_PASSWORD=
```

Configure Redis e Reverb:

```env
CACHE_STORE=redis

BROADCAST_CONNECTION=reverb

REVERB_HOST=127.0.0.1
REVERB_PORT=8081
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Execute as migrations:

```bash
php artisan migrate
```

Suba o servidor Laravel:

```bash
php artisan serve
```

Suba o Vite:

```bash
npm run dev
```

Suba o Reverb:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8081 --debug
```

## Testes

Rodar todos os testes:

```bash
php artisan test
```

Rodar apenas os testes de lances:

```bash
php artisan test --filter=AuctionBidTest
```

Rodar apenas os testes de finalização:

```bash
php artisan test --filter=FinishAuctionTest
```

Cobertura atual das principais regras:

```txt
- Usuário consegue dar lance válido
- Lance precisa ser maior que o valor atual
- Dono não pode dar lance no próprio leilão
- Leilão encerrado não aceita lance
- Lance nos últimos 10 segundos estende o leilão
- Leilão expirado é finalizado
- Vencedor é definido pelo maior lance
- Leilão sem lances é finalizado sem vencedor
- Leilão ainda ativo não é finalizado
```

## Comandos Úteis

Limpar cache:

```bash
php artisan optimize:clear
```

Listar rotas:

```bash
php artisan route:list
```

Verificar Scheduler:

```bash
php artisan schedule:list
```

Finalizar leilões expirados manualmente:

```bash
php artisan auctions:finish-expired
```

Testar Redis no Tinker:

```bash
php artisan tinker
```

```php
Cache::put('bidstream:test', 'redis funcionando', 60);
Cache::get('bidstream:test');
```

## Status do Projeto

O BidStream já possui as principais regras de um sistema de leilão em tempo real:

```txt
Criar leilão
Receber lances
Proteger lances simultâneos
Atualizar em tempo real
Estender leilão nos últimos segundos
Finalizar automaticamente
Definir vencedor
Testar regras críticas
```

## Próximas Melhorias

- Criar frontend completo em Vue.js ou React
- Criar tela de listagem de leilões
- Criar tela de detalhes com contador regressivo
- Exibir lances em tempo real na interface
- Criar painel do vendedor
- Criar histórico do usuário
- Adicionar Docker Compose
- Adicionar GitHub Actions para rodar testes automaticamente
- Criar documentação OpenAPI/Swagger
- Melhorar autorização com Policies
- Criar eventos para leilão finalizado

## Autor

Desenvolvido por Janailson Firmino de Almeida.

Backend Developer focado em Laravel, APIs, Redis, WebSockets, arquitetura backend e sistemas em tempo real.