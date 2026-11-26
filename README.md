# CarMotoCare

Aplicação web de gestão da manutenção de veículos (carros e motos), feita para a cadeira de
Engenharia de Software. Cada utilizador regista os seus veículos e acompanha manutenções, inspeções,
IUC, seguro e custos. Um dispositivo OBD simulado envia a quilometragem por MQTT e o sistema cria
alertas quando uma peça se aproxima do fim do intervalo de manutenção ou quando um prazo legal se
aproxima.

Arquitetura API-led com eventos: React → Nginx → EAPI Web → System APIs, com um broker RabbitMQ (MQTT)
entre o dispositivo OBD e dois microserviços. O desenho completo está em [`docs/`](docs/README.md).

> **Estado:** o projeto está em desenvolvimento. Contas, veículos, prazos, manutenções e despesas já
> funcionam. Os microserviços, os alertas em tempo real e o simulador OBD ainda não.

## Arrancar com Docker

### Requisitos

- Git
- Docker com o Docker Compose v2 (`docker compose`, sem hífen). No Windows e no macOS: Docker Desktop.
  Testado em Linux com Docker 29.1 e Compose 2.40.
- Portas livres: 80 (pode mudar-se, ver abaixo), 5432, 1883, 15672 e 8080 a 8085.

Não é preciso instalar PHP, Composer, Node nem PostgreSQL: correm dentro dos contentores.

### Primeiro arranque

Todos os comandos correm na pasta raiz do projeto.

1. Obter o código:

   ```bash
   git clone <url-do-repositório> carmotocare
   cd carmotocare
   ```

2. Criar o ficheiro de configuração:

   ```bash
   cp .env.example .env
   ```

3. Gerar o segredo dos tokens de sessão:

   ```bash
   openssl rand -hex 32
   ```

   Copiar o resultado para a linha `JWT_SECRET=` do `.env`. **Sem isto o login não funciona.**
   No Windows, o `openssl` está disponível no Git Bash.

4. No mesmo `.env`, escolher as palavras-passe `POSTGRES_PASSWORD` e `RABBITMQ_DEFAULT_PASS`
   **antes** do primeiro arranque. Se a porta 80 estiver ocupada, mudar `HTTP_PORT` (por exemplo `8000`).

5. Construir e arrancar:

   ```bash
   docker compose up -d --build
   ```

   No primeiro arranque cada serviço instala as suas dependências e as SAPIs criam as tabelas. Pode
   demorar vários minutos. Acompanhar com `docker compose logs -f` (sair com `Ctrl+C`).

### Confirmar que funciona

```bash
docker compose ps
```

Todos os serviços devem aparecer como `Up`. Depois:

| Endereço | O que é |
|---|---|
| http://localhost | Site (com `HTTP_PORT=8000`: http://localhost:8000) |
| http://localhost/api/health | EAPI Web através do Nginx: responde `{"status":"UP"}` |
| http://localhost:15672 | Consola do RabbitMQ (utilizador e palavra-passe do `.env`) |

Para experimentar, criar uma conta no site em «Criar conta», ou carregar os
[dados de demonstração](#dados-de-demonstração).

Cada serviço também responde em `/health` só no próprio computador:

| Serviço | Porta |
|---|---|
| `eapi-web` | 8080 |
| `sapi-vehicles` | 8081 |
| `sapi-users` | 8082 |
| `sapi-workshops` | 8083 |
| `ms-obd` | 8084 |
| `ms-alerts` | 8085 |

A base de dados está em `localhost:5432`.

## Dia a dia

| Quero... | Comando |
|---|---|
| Ligar | `docker compose up -d` |
| Desligar (mantém os dados) | `docker compose stop` |
| Ver o estado | `docker compose ps` |
| Ver os registos de um serviço | `docker compose logs -f eapi-web` |
| Reiniciar um serviço | `docker compose restart eapi-web` |

As alterações ao código PHP têm efeito imediato. Nos outros casos:

| O que mudou | O que fazer |
|---|---|
| Frontend (`frontend/`) | `docker compose up -d --build nginx` |
| `backend/Dockerfile` ou `docker-entrypoint.sh` | `docker compose up -d --build` |
| `.env` da raiz | `docker compose up -d` |
| `.env` de um serviço | `docker compose restart <serviço>` |
| `composer.json` ou `composer.lock` | `docker compose exec <serviço> composer install` |
| Migrações novas | `docker compose restart sapi-users sapi-vehicles` |

### Depois de um `git pull`

```bash
docker compose up -d --build
```

Depois, conforme o que veio no `pull`:

- dependências novas: `composer install` no serviço (tabela acima). O arranque só instala dependências
  quando a pasta `vendor/` está vazia;
- migrações novas: reiniciar as SAPIs;
- variáveis novas num `.env.example`: acrescentá-las à mão ao `.env` correspondente, que não é
  substituído.

### Desenvolver o frontend com atualização automática

Opcional. Precisa de Node 22 no computador e do Docker ligado (o `/api` é reencaminhado para a porta 8080).

```bash
cd frontend
npm install
npm run dev
```

Neste modo o ecrã de login tem o botão «Seguinte», que entra com **dados falsos**. Para testar a
aplicação a sério, iniciar sessão com uma conta.

## Testes

Com os contentores ligados:

```bash
docker compose exec eapi-web php artisan test
docker compose exec sapi-users php artisan test
docker compose exec sapi-vehicles php artisan test
docker compose exec sapi-workshops php artisan test
docker compose exec ms-obd php artisan test
docker compose exec ms-alerts php artisan test
```

Cada SAPI com base de dados tem uma base só para testes (`carmotocare_test_users` e
`carmotocare_test_vehicles`), apagada e recriada a cada execução. A base de desenvolvimento nunca é usada:
o `phpunit.xml` aponta para a base de testes e o `tests/TestCase.php` recusa correr noutra base. Usa-se
PostgreSQL e não SQLite porque as migrações usam funcionalidades do PostgreSQL.

As bases de testes são criadas quando o volume do PostgreSQL é criado. Num volume anterior a essa
funcionalidade, criar uma vez à mão (trocar `carmotocare` se o `POSTGRES_USER` for outro):

```bash
docker compose exec postgres createdb -U carmotocare carmotocare_test_users
docker compose exec postgres createdb -U carmotocare carmotocare_test_vehicles
```

Notas para quem escreve testes:

- usar `use RefreshDatabase;` em cada classe de testes que mexa na base (ver os exemplos em
  `tests/Feature/UserModelTest.php` e `tests/Feature/DatabaseRulesTest.php`);
- nos testes da sapi-vehicles a tabela `users` existe (criada por `tests/database/migrations`), mas não há
  modelo `User`: inserir com `DB::table('users')->insert(...)`;
- cada teste corre numa transação que nunca é confirmada, por isso as FKs `DEFERRED` só são verificadas se o
  teste fizer `DB::statement('SET CONSTRAINTS ALL IMMEDIATE')`.

## Base de dados

### Dados de demonstração

Correr **uma vez**, numa base ainda sem estes dados e por esta ordem (os veículos pertencem ao utilizador
de demonstração):

```bash
docker compose exec sapi-users php artisan db:seed
docker compose exec sapi-vehicles php artisan db:seed
```

Conta criada: `demo@carmotocare.pt` com a palavra-passe `demo1234`. Só para desenvolvimento: não carregar
numa instalação exposta na Internet.

### Reset

A sapi-users e a sapi-vehicles partilham a mesma base e o mesmo schema (`public`), e `vehicles.user_id` tem
chave estrangeira para `users` ([modelo de dados](docs/data/data-model.md), D3). Por isso **não se usa
`php artisan migrate:fresh`**: corrido numa SAPI, apagava também as tabelas da outra.

> **Atenção:** os comandos seguintes apagam todos os dados da base de desenvolvimento.

Correr um de cada vez (trocar `carmotocare` se o `POSTGRES_USER` ou o `POSTGRES_DB` forem outros):

```bash
docker compose exec postgres psql -U carmotocare -d carmotocare -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
```

Recriar a tabela `users` e esperar que a SAPI esteja pronta:

```bash
docker compose restart sapi-users
docker compose up -d --wait sapi-users
```

Recriar as tabelas dos veículos:

```bash
docker compose restart sapi-vehicles
```

`migrate:rollback` segue a ordem inversa: primeiro na sapi-vehicles, depois na sapi-users. Ao contrário, o
rollback da sapi-users para com um erro, porque `users` não pode ser apagada enquanto `vehicles` existir.

Para apagar **tudo** (base de dados, RabbitMQ e dependências instaladas) e voltar ao primeiro arranque:

```bash
docker compose down -v
docker compose up -d --build
```

## Partilhar a aplicação (Cloudflare Tunnel)

Opcional. Cria um endereço público temporário para a aplicação que está no teu computador, útil para a
mostrar a alguém ou abrir no telemóvel.

1. Instalar o `cloudflared`
   ([instruções](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/)).
2. Com a aplicação ligada, correr (usar a porta definida em `HTTP_PORT`):

   ```bash
   cloudflared tunnel --url http://localhost:80
   ```

3. Abrir o endereço `https://....trycloudflare.com` que aparece no terminal. Desligar com `Ctrl+C`.

> **Atenção:** quem tiver o endereço consegue aceder, e a aplicação está em modo de desenvolvimento.
> Ligar o túnel só quando for preciso. O endereço muda sempre que o `cloudflared` é reiniciado. O
> instalador descarregado não deve ir para o repositório.

## Problemas frequentes

| Sintoma | Causa provável | Solução |
|---|---|---|
| O login dá erro | `JWT_SECRET` vazio ou com menos de 32 caracteres | Preencher no `.env` da raiz e correr `docker compose up -d` |
| O PostgreSQL não arranca | Não existe `.env` na raiz ou a palavra-passe está vazia | Fazer os passos 2 a 4 do primeiro arranque |
| `port is already allocated` | Outra aplicação usa a porta | Para o site, mudar `HTTP_PORT`; para as outras, fechar essa aplicação |
| `sapi-users is unhealthy` no primeiro arranque | A instalação das dependências demorou mais do que o tempo de espera | Esperar um pouco e repetir `docker compose up -d` |
| O site abre mas o `/api` dá 502 | A EAPI ainda está a arrancar, ou o Nginx ficou com o endereço antigo dela | Ver `docker compose logs eapi-web`; depois `docker compose restart nginx` |
| `Class ... not found` depois de um `pull` | Dependências novas por instalar | `docker compose exec <serviço> composer install` |
| Mudei a palavra-passe da base e deixou de ligar | O volume foi criado com a antiga | Repor a antiga, ou `docker compose down -v` (apaga os dados) |
| Os testes dizem que a base de testes não existe | Volume antigo | Criar as bases (ver [Testes](#testes)) |
| No Linux não consigo editar o `.env` de um serviço | Os contentores correm como `root` | `sudo chown -R "$(id -u):$(id -g)" backend` |
| O simulador não arranca | Os ficheiros não estão em `obd-simulator/` | Problema conhecido, por corrigir |

## Estrutura

```
carmotocare/
├── docs/            desenho da fase 2 (contratos OpenAPI, arquitetura, modelo de dados, MQTT)
├── infra/           configuração do Nginx, RabbitMQ e PostgreSQL
├── backend/
│   ├── eapi-web/        Experience API: REST + JWT + WebSocket (a única exposta)
│   ├── sapi-users/      contas e autenticação
│   ├── sapi-vehicles/   veículos, peças, prazos, manutenções, inspeções, alertas, OBD
│   ├── sapi-workshops/  pesquisa de oficinas (Overpass / OpenStreetMap)
│   ├── ms-obd/          valida leituras do OBD e atualiza a quilometragem
│   └── ms-alerts/       cria alertas por km e por data
├── frontend/        React + Vite
└── obd-simulator/   simulador do dispositivo OBD
```

## Correspondência relatório ↔ código

| No relatório | No código |
|---|---|
| EAPI Web | `backend/eapi-web` |
| SAPI Utilizadores | `backend/sapi-users` |
| SAPI Veículos | `backend/sapi-vehicles` |
| SAPI Oficinas | `backend/sapi-workshops` |
| Microserviço OBD | `backend/ms-obd` |
| Microserviço Manutenção e Alertas | `backend/ms-alerts` |
| Dispositivo OBD Simulado | `obd-simulator` |

## Tecnologias

Laravel (PHP 8.3+) · React com Vite (JavaScript) · PostgreSQL · RabbitMQ com MQTT ·
Laravel Reverb (WebSocket) · Nginx · OpenAPI 3.0.3

## Convenções

- Código em inglês; textos da interface em português. Os nomes seguem os contratos em `docs/api/`.
- Em cada serviço Laravel: Controller → Request (validação) → Service (regras) → Model ou Client →
  Resource (JSON). Nada de lógica nos controllers.
- Erros no formato RFC 7807 (`Problem`), como nos contratos.
- Configuração só em `.env`; nunca fazer commit de palavras-passe.
- Se uma alteração mudar um contrato, o modelo de dados ou um tópico, atualiza o ficheiro em `docs/`
  no mesmo pull request.

## Regras de Git

- A `main` está protegida: tudo entra por pull request, revisto por outro membro.
- Uma branch por tarefa: `feature/<serviço>-<descrição>` (ex.: `feature/sapi-vehicles-crud`),
  `fix/<descrição>`, `docs/<descrição>`.
- Commits curtos com prefixo: `feat:`, `fix:`, `docs:`, `chore:`, `test:`.

## Grupo

Grupo 2 · Engenharia de Software 2026/2027
