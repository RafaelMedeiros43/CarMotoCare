# Estrutura do repositório

```
Decidido: monorepo, nomes de pastas em inglês, React em JavaScript, microserviços em Laravel completo.

== Pastas ==
carmotocare/
├── README.md
├── .gitignore
├── .env.example
├── docker-compose.yml
├── docs/            api/ · architecture/ · data/ · mqtt/   (conteúdo em português)
├── infra/           nginx/ · rabbitmq/ · postgres/
├── backend/
│   ├── eapi-web/          (Laravel: REST + JWT + Reverb)
│   ├── sapi-users/
│   ├── sapi-vehicles/
│   ├── sapi-workshops/
│   ├── ms-obd/            (Laravel: só /health + subscritor MQTT)
│   └── ms-alerts/         (Laravel: subscritor MQTT + tarefa diária)
├── frontend/        (React + Vite, JavaScript, PWA)
└── obd-simulator/   (Python: simulator.py, requirements.txt, Dockerfile)

== Correspondência relatório ↔ código ==
EAPI Web                           -> backend/eapi-web
SAPI Utilizadores                  -> backend/sapi-users       (schema users)
SAPI Veículos                      -> backend/sapi-vehicles    (schema vehicles)
SAPI Oficinas                      -> backend/sapi-workshops
Microserviço OBD                   -> backend/ms-obd
Microserviço Manutenção e Alertas  -> backend/ms-alerts
Dispositivo OBD Simulado           -> obd-simulator

== Dentro de cada serviço Laravel ==
app/Http/Controllers   finos: recebem, chamam o Service, devolvem
app/Http/Requests      validação (uma classe por pedido)
app/Http/Resources     forma do JSON (camelCase, igual ao YAML)
app/Models             Eloquent (só nas SAPIs)
app/Services           lógica de negócio
app/Clients            chamadas HTTP a outros serviços
app/Console/Commands   subscritores MQTT (microserviços e EAPI)
routes/api.php · database/migrations e seeders (só SAPIs) · tests/Feature · Dockerfile

== Dentro do frontend ==
src/pages · src/components · src/api · src/context · src/hooks · src/styles

== Convenções ==
- Código em inglês, textos da interface em português; nomes iguais aos YAML.
- Fluxo: Controller -> Request -> Service -> Model/Client -> Resource. Sem lógica nos controllers.
- Erros em RFC 7807 (Problem), tratados num único sítio por serviço.
- Git: main protegida, branch por tarefa (feature/sapi-vehicles-crud), merge por pull request,
  commits feat: / fix: / docs:.
- Configuração só em .env; nunca fazer commit de palavras-passe.

== Como iniciar ==
1. git clone; criar docs/, infra/, backend/, frontend/, obd-simulator/.
2. Em backend/: composer create-project laravel/laravel <serviço> (x6).
3. Em frontend/: npm create vite@latest . -- --template react
4. Copiar os documentos do projeto para docs/.
5. docker-compose.yml, .gitignore, README; primeiro commit.

Checklist da base antes de distribuir trabalho: Sheet "CarMotoCare — Base antes de distribuir trabalho"
(https://claude.ai/artifact/2qtDd7nkAvBG4F5FgCBKGn).
```
