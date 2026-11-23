# CarMotoCare

Aplicação web de gestão da manutenção de veículos (carros e motos), feita para a cadeira de
Engenharia de Software. Cada utilizador regista os seus veículos e acompanha manutenções, inspeções,
IUC, seguro e custos. Um dispositivo OBD simulado envia a quilometragem por MQTT e o sistema cria
alertas quando uma peça se aproxima do fim do intervalo de manutenção ou quando um prazo legal se
aproxima.

Arquitetura API-led com eventos: React → Nginx → EAPI Web → System APIs, com um broker RabbitMQ (MQTT)
entre o dispositivo OBD e dois microserviços. O desenho completo está em [`docs/`](docs/README.md).

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
├── frontend/        React + Vite (PWA)
└── obd-simulator/   simulador do dispositivo OBD (Python)
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

Laravel (PHP 8.3+) · React com Vite (JavaScript) · PostgreSQL (Supabase) · RabbitMQ com MQTT ·
Laravel Reverb (WebSocket) · Nginx · Python (simulador) · OpenAPI 3.0.3

## Como preparar o ambiente

Requisitos: PHP 8.3+, Composer, Node (LTS), Python 3 e, mais tarde, Docker.

```bash
git clone <url-do-repositório>
cd carmotocare

# cada serviço Laravel
cd backend/eapi-web && composer install && cp .env.example .env && php artisan key:generate
# (repetir para os outros 5 serviços em backend/)

# frontend
cd frontend && npm install && npm run dev
```

O `docker-compose.yml` para arrancar tudo de uma vez vem numa fase seguinte.

## Reset da base de dados

A sapi-users e a sapi-vehicles partilham a mesma base e o mesmo schema (`public`), e `vehicles.user_id` tem
chave estrangeira para `users` ([modelo de dados](docs/data/data-model.md), D3). Por isso **não se usa
`php artisan migrate:fresh`**: corrido numa SAPI, apagava também as tabelas da outra.

Para recomeçar do zero em desenvolvimento:

```bash
docker compose exec postgres psql -U carmotocare -d carmotocare -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
docker compose restart sapi-users      # recria users (migrações correm no arranque)
docker compose restart sapi-vehicles   # recria as tabelas de veículos (precisa de users)
docker compose exec sapi-users php artisan db:seed
docker compose exec sapi-vehicles php artisan db:seed
```

Os seeders correm por esta ordem (primeiro sapi-users) porque os veículos de demonstração pertencem ao
utilizador de demonstração.

`migrate:rollback` segue a ordem inversa: primeiro na sapi-vehicles, depois na sapi-users. Ao contrário, o
rollback da sapi-users para com um erro, porque `users` não pode ser apagada enquanto `vehicles` existir.

## Testes

Os testes automáticos correm dentro dos contentores, com o stack a correr (`docker compose up`):

```bash
docker compose exec sapi-users php artisan test
docker compose exec sapi-vehicles php artisan test
```

Cada SAPI tem uma base só para testes (`carmotocare_test_users` e `carmotocare_test_vehicles`), apagada e
recriada a cada execução. A base de desenvolvimento (`carmotocare`) nunca é usada pelos testes: o `phpunit.xml`
aponta para a base de testes e o `tests/TestCase.php` recusa correr noutra base. Usa-se PostgreSQL e não SQLite
porque as migrações usam funcionalidades do PostgreSQL.

As bases de testes são criadas por `infra/postgres/init/` quando o volume do Postgres é criado. Num volume que
já existia antes disto, criar uma vez à mão:

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
