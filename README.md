# 💸 Digital Wallet API

![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-10+-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Sanctum](https://img.shields.io/badge/Laravel_Sanctum-Auth-orange?style=flat-square)
![Swagger](https://img.shields.io/badge/Swagger-OpenAPI_3.0-85EA2D?style=flat-square&logo=swagger&logoColor=black)
![Tests](https://img.shields.io/badge/Tests-PHPUnit-blue?style=flat-square)
![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)

> API RESTful para carteira digital pessoal — autenticação, depósitos, saques e histórico financeiro. Construída com Laravel 10+, Sanctum, e documentação Swagger completa.

---

## 📑 Índice

- [Descrição](#-descrição)
- [Objetivos do Sistema](#-objetivos-do-sistema)
- [Arquitetura](#-arquitetura)
- [Stack Utilizada](#-stack-utilizada)
- [Decisões Técnicas](#-decisões-técnicas)
- [Estrutura de Pastas](#-estrutura-de-pastas)
- [Fluxo Financeiro](#-fluxo-financeiro)
- [Regras de Negócio](#-regras-de-negócio)
- [Endpoints](#-endpoints)
- [Requisitos Mínimos](#-requisitos-mínimos)
- [Instalação](#-instalação)
- [Configuração do .env](#-configuração-do-env)
- [Banco de Dados](#-banco-de-dados)
- [Como Rodar Localmente](#-como-rodar-localmente)
- [Swagger / OpenAPI](#-swagger--openapi)
- [Autenticação Sanctum](#-autenticação-sanctum)
- [Exemplos de Requests](#-exemplos-de-requests)
- [Exemplos de Erros](#-exemplos-de-erros)
- [Testes Automatizados](#-testes-automatizados)
- [Credenciais Seed](#-credenciais-seed)
- [Deploy Railway / Render](#-deploy-railway--render)
- [Checklist de Produção](#-checklist-de-produção)
- [Melhorias Futuras](#-melhorias-futuras)
- [Troubleshooting](#-troubleshooting)

---

## 📋 Descrição

MVP de **carteira digital pessoal** onde cada usuário possui uma carteira individual. O sistema permite gerenciar saldo com operações de depósito e saque, mantendo histórico auditável de todas as transações financeiras.

Cada operação é **atômica** (via `DB::transaction`) e registra um snapshot do saldo pós-operação, garantindo rastreabilidade completa mesmo em cenários de falha.

---

## 🎯 Objetivos do Sistema

| Funcionalidade | Status |
|---|---|
| Registro e login com Sanctum | ✅ |
| Criação automática de carteira no registro | ✅ |
| Depósito com validação e registro de transação | ✅ |
| Saque com validação de saldo suficiente | ✅ |
| Histórico paginado com filtros | ✅ |
| Dashboard com totais mensais | ✅ |
| Documentação Swagger/OpenAPI | ✅ |
| Testes automatizados (≥ 5) | ✅ |
| Deploy Railway/Render ready | ✅ |

---

## 🏛 Arquitetura

```
Request → Controller (thin) → Service (business logic) → Model (Eloquent)
                ↓                        ↓
         Form Request             DB::transaction
         (validation)             (atomic ops)
                ↓
          API Resource
          (response shape)
```

Padrões utilizados:
- **Service Layer** — toda lógica financeira em `WalletService`
- **Form Requests** — validação desacoplada dos controllers
- **API Resources** — shape de resposta consistente
- **Custom Exceptions** — erros semânticos com status HTTP correto
- **Repository-free** — Eloquent direto nos Services (pragmático para MVP)

---

## 🛠 Stack Utilizada

| Camada | Tecnologia |
|---|---|
| Linguagem | PHP 8.2+ |
| Framework | Laravel 10+ |
| Autenticação | Laravel Sanctum |
| ORM | Eloquent |
| Banco de Dados | MySQL 8+ / PostgreSQL 14+ |
| Documentação | L5-Swagger (OpenAPI 3.0) |
| Testes | PHPUnit (integrado ao Laravel) |
| Lint | Laravel Pint |
| Deploy | Railway ou Render |

---

## 💡 Decisões Técnicas

| Decisão | Justificativa |
|---|---|
| **Service Layer (sem Repository)** | MVP pragmático — Eloquent já é uma abstração suficiente. Repository adicionaria boilerplate sem benefício real neste escopo. |
| **`bcadd` / `bcsub` para saldo** | Precisão decimal exata. Ponto flutuante nativo pode gerar erros em centavos (ex: `0.1 + 0.2 ≠ 0.3`). |
| **`lockForUpdate()` no saque** | Previne race conditions em saques concorrentes — lock pessimista na linha da carteira. |
| **`balance_after` na transação** | Snapshot do saldo pós-operação. Permite auditoria sem precisar recalcular histórico. |
| **Sanctum (token)** | Adequado para APIs mobile/SPA. Simples, seguro e sem overhead de OAuth2. |
| **Sem CQRS / Event Sourcing** | Fora do escopo do MVP. Adicionaria complexidade desnecessária. |

---

## 📁 Estrutura de Pastas

```
app/
├── Exceptions/
│   ├── Handler.php                  # Handler global de exceptions
│   ├── InsufficientBalanceException.php
│   └── InvalidTransactionException.php
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php           # Base + anotações Swagger globais
│   │   ├── AuthController.php       # register, login, logout
│   │   ├── WalletController.php     # show, deposit, withdraw, dashboard
│   │   └── TransactionController.php# index (listagem paginada)
│   ├── Requests/
│   │   ├── RegisterRequest.php
│   │   ├── LoginRequest.php
│   │   ├── TransactionRequest.php   # depósito e saque
│   │   └── TransactionFilterRequest.php
│   └── Resources/
│       ├── UserResource.php
│       ├── WalletResource.php
│       └── TransactionResource.php
├── Models/
│   ├── User.php
│   ├── Wallet.php
│   └── Transaction.php
└── Services/
    └── WalletService.php            # toda a lógica financeira

database/
├── factories/
│   ├── UserFactory.php
│   ├── WalletFactory.php
│   └── TransactionFactory.php
├── migrations/
│   ├── ..._create_users_table.php
│   ├── ..._create_wallets_table.php
│   └── ..._create_transactions_table.php
└── seeders/
    ├── DatabaseSeeder.php
    └── UserSeeder.php

routes/
└── api.php

tests/Feature/
├── AuthTest.php
├── WalletTest.php
├── TransactionTest.php
└── WalletServiceTest.php
```

---

## 💰 Fluxo Financeiro

```
DEPÓSITO:
  1. Validar amount (> 0, ≤ 2 decimais)
  2. Iniciar DB::transaction
  3. SELECT wallet WHERE user_id = ? FOR UPDATE  ← lock pessimista
  4. balance = balance + amount  (bcadd)
  5. UPDATE wallets SET balance = ?
  6. INSERT transactions (type=credit, amount, balance_after)
  7. COMMIT
  8. Retornar TransactionResource

SAQUE:
  1. Validar amount (> 0, ≤ 2 decimais)
  2. Iniciar DB::transaction
  3. SELECT wallet WHERE user_id = ? FOR UPDATE  ← lock pessimista
  4. Verificar balance >= amount → InsufficientBalanceException
  5. balance = balance - amount  (bcsub)
  6. UPDATE wallets SET balance = ?
  7. INSERT transactions (type=debit, amount, balance_after)
  8. COMMIT
  9. Retornar TransactionResource

ROLLBACK automático em qualquer exceção dentro da transação.
```

---

## 📏 Regras de Negócio

### Autenticação
- Senha mínima de 8 caracteres, armazenada com `bcrypt`
- Token Sanctum invalidado no logout (não apenas expirado)
- Carteira criada automaticamente com `balance = 0.00` no registro

### Operações Financeiras
- Valor mínimo: **R$ 0,01**
- Valores negativos ou zero são rejeitados (422)
- Saque exige saldo suficiente — verificado com lock para evitar race condition
- Toda operação gera um registro em `transactions` com `balance_after`
- Falha em qualquer etapa faz rollback completo

### Histórico
- Usuário acessa **apenas suas próprias** transações
- Filtros disponíveis: `type`, `date_from`, `date_to`, `per_page`
- `per_page` máximo: 100 | padrão: 15

---

## 🔌 Endpoints

| Método | Endpoint | Auth | Descrição |
|---|---|---|---|
| POST | `/api/auth/register` | ❌ | Registro de usuário |
| POST | `/api/auth/login` | ❌ | Login |
| POST | `/api/auth/logout` | ✅ | Logout (invalida token) |
| GET | `/api/wallet` | ✅ | Consultar saldo |
| GET | `/api/wallet/dashboard` | ✅ | Dashboard financeiro |
| POST | `/api/wallet/deposit` | ✅ | Depósito |
| POST | `/api/wallet/withdraw` | ✅ | Saque |
| GET | `/api/transactions` | ✅ | Histórico paginado |

---

## ⚙️ Requisitos Mínimos

- PHP **8.2+**
- Composer **2+**
- MySQL **8+** ou PostgreSQL **14+**
- Node.js (apenas se usar Vite/assets — opcional para API pura)

---

## 🚀 Instalação

```bash
# 1. Clonar o repositório
git clone https://github.com/seu-usuario/digital-wallet-api.git
cd digital-wallet-api

# 2. Instalar dependências
composer install

# 3. Copiar e configurar .env
cp .env.example .env
php artisan key:generate

# 4. Instalar pacote Swagger
composer require darkaonline/l5-swagger

# 5. Publicar config do Swagger
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

---

## 🔧 Configuração do .env

```env
APP_NAME="Digital Wallet"
APP_ENV=local
APP_KEY=           # gerado pelo artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=digital_wallet
DB_USERNAME=root
DB_PASSWORD=sua_senha

SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1

L5_SWAGGER_GENERATE_ALWAYS=true
L5_SWAGGER_CONST_HOST=http://localhost:8000
```

---

## 🗄 Banco de Dados

```bash
# Criar banco (MySQL)
mysql -u root -p -e "CREATE DATABASE digital_wallet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Rodar migrations
php artisan migrate

# Rodar seeders (cria usuários demo + transações)
php artisan db:seed

# Reset completo (⚠️ apaga dados)
php artisan migrate:fresh --seed
```

### Schema

```
users
  id, name, email, password, created_at, updated_at

wallets
  id, user_id (FK), balance (decimal 15,2), created_at, updated_at
  UNIQUE(user_id)

transactions
  id, wallet_id (FK), type (credit|debit), amount (decimal 15,2),
  balance_after (decimal 15,2), description, created_at, updated_at
  INDEX(wallet_id, type), INDEX(wallet_id, created_at)
```

---

## ▶️ Como Rodar Localmente

```bash
# Servidor de desenvolvimento
php artisan serve

# Gerar documentação Swagger
php artisan l5-swagger:generate

# Rodar testes
php artisan test

# Rodar testes com cobertura
php artisan test --coverage

# Lint (Laravel Pint)
./vendor/bin/pint
```

API disponível em: `http://localhost:8000`

---

## 📖 Swagger / OpenAPI

Após `php artisan serve`, acesse:

```
http://localhost:8000/api/documentation
```

Para regenerar os docs:
```bash
php artisan l5-swagger:generate
```

---

## 🔐 Autenticação Sanctum

Todas as rotas protegidas exigem o header:

```
Authorization: Bearer {seu_token}
```

O token é retornado nas respostas de `register` e `login`.

```bash
# Exemplo: login e uso do token
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@wallet.com","password":"password"}' \
  | jq -r '.data.token')

curl -H "Authorization: Bearer $TOKEN" \
     -H "Accept: application/json" \
     http://localhost:8000/api/wallet
```

---

## 📡 Exemplos de Requests

### Registro
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "João Silva",
    "email": "joao@email.com",
    "password": "senha123",
    "password_confirmation": "senha123"
  }'
```
```json
{
  "success": true,
  "message": "Usuário registrado com sucesso.",
  "data": {
    "user": { "id": 1, "name": "João Silva", "email": "joao@email.com", "created_at": "2024-01-15T10:00:00+00:00" },
    "token": "1|abc123..."
  }
}
```

### Depósito
```bash
curl -X POST http://localhost:8000/api/wallet/deposit \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"amount": 500.00}'
```
```json
{
  "success": true,
  "message": "Depósito realizado com sucesso.",
  "data": {
    "id": 1,
    "type": "credit",
    "amount": 500.00,
    "balance_after": 500.00,
    "description": "Depósito",
    "created_at": "2024-01-15T10:05:00+00:00"
  }
}
```

### Histórico com filtros
```bash
curl "http://localhost:8000/api/transactions?type=credit&date_from=2024-01-01&date_to=2024-01-31&per_page=10" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```
```json
{
  "success": true,
  "message": "Transações listadas com sucesso.",
  "data": [ { "id": 1, "type": "credit", "amount": 500.00, "balance_after": 500.00, "created_at": "..." } ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 10, "total": 1 }
}
```

---

## ❌ Exemplos de Erros

### Saldo insuficiente (422)
```json
{
  "success": false,
  "message": "Saldo insuficiente. Saldo disponível: R$ 50,00"
}
```

### Validação (422)
```json
{
  "success": false,
  "message": "Dados inválidos.",
  "errors": {
    "amount": ["O valor mínimo permitido é R$ 0,01."],
    "email":  ["Este e-mail já está em uso."]
  }
}
```

### Não autenticado (401)
```json
{
  "success": false,
  "message": "Não autenticado. Por favor, faça login."
}
```

---

## 🧪 Testes Automatizados

```bash
# Rodar todos os testes
php artisan test

# Verbose
php artisan test --verbose

# Apenas uma suite
php artisan test tests/Feature/WalletTest.php
```

### Cobertura dos Testes

| Suite | Cenários cobertos |
|---|---|
| `AuthTest` | Registro, login, logout, email duplicado, senha curta |
| `WalletTest` | Depósito, saque, saldo insuficiente, dashboard, precisão decimal |
| `TransactionTest` | Isolamento entre usuários, filtros, paginação |
| `WalletServiceTest` | Atomicidade, rollback, precisão, exceções |

**Total: 20+ asserções cobrindo os edge cases críticos.**

Cenários obrigatórios cobertos:
- ✅ Depósito com sucesso
- ✅ Saque com saldo insuficiente
- ✅ Rollback em falha
- ✅ Usuário não acessa dados de outro usuário
- ✅ Atualização correta do saldo

---

## 🌱 Credenciais Seed

Após `php artisan db:seed`:

| E-mail | Senha | Perfil |
|---|---|---|
| `admin@wallet.com` | `password` | Saldo R$ 1.500,00 + histórico |
| `user@wallet.com` | `password` | Saldo R$ 250,75 + histórico |

---

## 🚂 Deploy Railway / Render

### Railway

```bash
# Instalar Railway CLI
npm install -g @railway/cli
railway login
railway init
railway up
```

Variáveis de ambiente no painel Railway:

```env
APP_NAME=Digital Wallet
APP_ENV=production
APP_DEBUG=false
APP_KEY=           # php artisan key:generate --show
APP_URL=https://seu-app.up.railway.app

DB_CONNECTION=mysql
DB_HOST=${{MYSQL.MYSQL_HOST}}
DB_PORT=${{MYSQL.MYSQL_PORT}}
DB_DATABASE=${{MYSQL.MYSQL_DATABASE}}
DB_USERNAME=${{MYSQL.MYSQL_USER}}
DB_PASSWORD=${{MYSQL.MYSQL_PASSWORD}}

L5_SWAGGER_CONST_HOST=https://seu-app.up.railway.app
L5_SWAGGER_GENERATE_ALWAYS=false
```

**Comandos de build (Railway → Settings → Deploy):**
```bash
# Build Command
composer install --no-dev --optimize-autoloader && php artisan config:cache && php artisan route:cache && php artisan migrate --force && php artisan db:seed --force

# Start Command
php artisan serve --host=0.0.0.0 --port=$PORT
```

### Render

Criar `render.yaml` na raiz:
```yaml
services:
  - type: web
    name: digital-wallet-api
    env: php
    buildCommand: composer install --no-dev --optimize-autoloader
    startCommand: php artisan serve --host=0.0.0.0 --port=$PORT
    envVars:
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: false
      - key: APP_KEY
        generateValue: true
```

---

## ✅ Checklist de Produção

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] `APP_KEY` gerado (`php artisan key:generate`)
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] `php artisan migrate --force`
- [ ] `L5_SWAGGER_GENERATE_ALWAYS=false`
- [ ] Banco de dados com backups automáticos
- [ ] HTTPS habilitado
- [ ] Variáveis de ambiente via painel (não commitar `.env`)

---

## 🔮 Melhorias Futuras

| Feature | Descrição |
|---|---|
| Transferências entre usuários | P2P com validação de destinatário |
| Notificações | E-mail / push em cada transação |
| 2FA | Autenticação de dois fatores |
| Rate limiting | Throttle por IP e por usuário |
| Soft deletes | Preservar histórico ao desativar conta |
| Relatório PDF | Export do extrato mensal |
| Webhooks | Notificar sistemas externos por evento |
| Multi-moeda | Suporte a USD, EUR, etc. |

---

## 🔧 Troubleshooting

**`SQLSTATE[42S02]: Table 'wallets' doesn't exist`**
```bash
php artisan migrate
```

**`Unauthenticated` em rotas protegidas**
→ Certifique-se de enviar `Authorization: Bearer {token}` e `Accept: application/json`.

**Swagger não atualiza**
```bash
php artisan l5-swagger:generate
# ou
L5_SWAGGER_GENERATE_ALWAYS=true  # no .env (dev only)
```

**Erro de precisão decimal**
→ Confirme que `DB_CONNECTION` não está usando SQLite em produção. Use MySQL/PostgreSQL.

**`php artisan test` falha com banco**
→ Configure `DB_CONNECTION=sqlite` e `DB_DATABASE=:memory:` no `phpunit.xml` para testes isolados.

---

## 📄 Licença

MIT © Digital Wallet API
