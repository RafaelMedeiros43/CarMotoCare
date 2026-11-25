# Modelo de dados (v3)

Substitui o modelo do MySQL Workbench mostrado na apresentação da fase 2. Alinhado com a SAPI Veículos v8,
a SAPI Utilizadores v7 e o domínio v6.4. Pensado para passar diretamente para migrations do Laravel.

== 1. Decisões ==
D1  Chaves primárias UUID (gen_random_uuid()), como nas APIs. Em Laravel: trait HasUuids nos modelos.
D2  Nomes em inglês e snake_case (vehicles, parts, ...), iguais aos campos das APIs. Assim o Eloquent funciona
    sem configurações extra e os JSON saem quase diretos dos modelos (camelCase na API, snake_case na base).
D3  Uma só base e um só schema (public), partilhado pelas duas SAPIs. vehicles.user_id é chave estrangeira
    para users(id) com ON DELETE CASCADE: apagar um utilizador apaga os seus veículos (e, por R10, tudo o que
    deles depende). Cada SAPI só escreve nas suas tabelas: sapi-users em users, sapi-vehicles nas restantes; a
    FK é garantida pela base, não por relações Eloquent (sapi-vehicles não tem modelo User). Consequências:
    - cada SAPI regista as suas migrações numa tabela própria (migrations_users, migrations_vehicles);
    - sapi-users migra primeiro (no docker-compose, sapi-vehicles espera que sapi-users esteja healthy);
    - não se usa migrate:fresh (numa SAPI apagaria também as tabelas da outra); ver "Reset da base de dados"
      no README da raiz.
    A EAPI garante que o userId vem do JWT.
D4  Enumerados como VARCHAR + CHECK (é o que o $table->enum() do Laravel gera em PostgreSQL).
D5  Despesas não têm tabela: são uma VIEW sobre manutenções, inspeções e prazos pagos (regra R7, sem duplicar
    valores). GET /vehicles/{id}/expenses lê a view.
D6  Valores calculados não se guardam: custo total, nextKm, kmRemaining, nextDate, daysRemaining, nextAlertKm e
    openAlerts calculam-se nas consultas.
D7  Regras de negócio garantidas pela base onde é possível (índices únicos parciais e CHECK), para não
    dependerem só do código: R1, R2, R4, R6, R10 e "pelo menos um limite ativo".
D10 Coerência entre tabelas com FKs compostas: a peça de um alerta ou de uma manutenção é do mesmo veículo, e
    o prazo de um alerta é do mesmo veículo e do mesmo tipo. Para isso parts, maintenances e deadlines têm um
    UNIQUE extra (id, vehicle_id[, type]) e maintenance_parts guarda também vehicle_id.
D11 A VIEW expenses impede alterar o tipo das colunas que usa; uma migration que o faça tem de apagar a view,
    alterar e voltar a criá-la.
D8  Leituras OBD guardam só o que a API usa (km, temperatura do óleo, GPS). Velocidade, rpm, combustível e
    radiador do modelo antigo saíram; se o simulador os enviar, são ignorados.
D9  A credencial MQTT de cada dispositivo fica no RabbitMQ (um utilizador por dispositivo), não nesta base.

== 2. Alterações face ao modelo do Workbench ==
- IDs INT -> UUID; nomes PT -> EN; MySQL -> PostgreSQL.
- veiculo: ano -> first_registration (AAAA-MM); custo_Total removido (calculado); matrícula única.
- peca: + vehicle_id (cada veículo tem as suas peças), + last_service_km, + interval_source; intervalo em km
  e/ou meses com pelo menos um ativo.
- prazolegal -> deadlines: + status, + paid_at, + description; "validade" passa a due_date.
- Alerta -> alerts: + trigger, + due_date, + target_km, + closed_at; origem é peça OU prazo
  (km_remaining não se guarda: calcula-se, D6)
  (a inspeção gera alertas através do prazo de tipo INSPECAO, por isso alerts deixa de ter idInspecao).
- ItemInspecao -> inspection_deficiencies (description, severity), como na API.
- dispositivoOBD: estado removido (calcula-se pela hora da última leitura); + name, + last_reading_at.
- LeituraOBD: só km, oil_temp, latitude, longitude.
- Nova VIEW expenses.

== 3. DDL (PostgreSQL) ==

```sql
-- Schema public (D3). Ordem de criação: sapi-users primeiro, depois sapi-vehicles.

-- ---------- sapi-users ----------
CREATE TABLE users (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name          VARCHAR(100) NOT NULL CHECK (length(name) > 0),
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,          -- bcrypt (Hash::make do Laravel)
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------- sapi-vehicles ----------
CREATE TABLE vehicles (
    id                 UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id            UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,   -- D3
    type               VARCHAR(5)  NOT NULL CHECK (type IN ('CARRO','MOTO')),
    brand              VARCHAR(50) NOT NULL,
    model              VARCHAR(50) NOT NULL,
    first_registration CHAR(7)     NOT NULL CHECK (first_registration ~ '^\d{4}-(0[1-9]|1[0-2])$'),
    plate              VARCHAR(8)  NOT NULL UNIQUE CHECK (plate ~ '^[A-Z0-9]{2}-[A-Z0-9]{2}-[A-Z0-9]{2}$'),
    km                 INTEGER     NOT NULL CHECK (km >= 0),     -- R3 garantida na SAPI (409 se descer)
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ON vehicles (user_id);

CREATE TABLE parts (
    id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id        UUID NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
    name              VARCHAR(60) NOT NULL,
    interval_km       INTEGER CHECK (interval_km >= 1),
    interval_months   INTEGER CHECK (interval_months >= 1),
    interval_source   VARCHAR(10) NOT NULL DEFAULT 'CATALOGO' CHECK (interval_source IN ('CATALOGO','UTILIZADOR')),
    last_service_km   INTEGER NOT NULL CHECK (last_service_km >= 0),
    last_service_date DATE    NOT NULL,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (vehicle_id, name),
    UNIQUE (id, vehicle_id),                                         -- alvo de FKs compostas (D10)
    CHECK (interval_km IS NOT NULL OR interval_months IS NOT NULL)   -- pelo menos um limite ativo
);

CREATE TABLE deadlines (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id  UUID NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
    type        VARCHAR(8)  NOT NULL CHECK (type IN ('IUC','SEGURO','INSPECAO')),
    status      VARCHAR(9)  NOT NULL DEFAULT 'PENDENTE' CHECK (status IN ('PENDENTE','CONCLUIDO')),
    due_date    DATE        NOT NULL,
    paid_at     DATE,
    amount      NUMERIC(10,2) CHECK (amount > 0),
    description VARCHAR(255),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (id, vehicle_id, type),                                   -- alvo da FK composta de alerts (D10)
    CHECK (status = 'PENDENTE' OR paid_at IS NOT NULL),
    CHECK (status = 'PENDENTE' OR type = 'INSPECAO' OR amount IS NOT NULL)   -- IUC/SEGURO pagos têm valor
);
-- R2: no máximo um prazo pendente por tipo e veículo
CREATE UNIQUE INDEX deadlines_one_pending ON deadlines (vehicle_id, type) WHERE status = 'PENDENTE';
CREATE INDEX ON deadlines (status, due_date);

CREATE TABLE maintenances (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id UUID NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
    type       VARCHAR(20) NOT NULL CHECK (type IN ('REVISAO_PERIODICA','MUDANCA_OLEO','SUBSTITUICAO_PNEUS',
                                                    'REPARACAO','AVARIA','OUTRO')),
    date       DATE    NOT NULL,
    km         INTEGER NOT NULL CHECK (km >= 0),
    cost       NUMERIC(10,2) NOT NULL CHECK (cost >= 0),   -- custo total (R7)
    notes      TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (id, vehicle_id)                                  -- alvo da FK composta de maintenance_parts (D10)
);

CREATE TABLE maintenance_parts (
    maintenance_id UUID NOT NULL,
    part_id        UUID NOT NULL,
    vehicle_id     UUID NOT NULL,                            -- D10: manutenção e peça do mesmo veículo
    cost           NUMERIC(10,2) CHECK (cost >= 0),          -- informativo (R7)
    PRIMARY KEY (maintenance_id, part_id),
    FOREIGN KEY (maintenance_id, vehicle_id) REFERENCES maintenances(id, vehicle_id) ON DELETE CASCADE,
    -- NO ACTION: apagar uma peça não apaga o histórico. DEFERRED: só se verifica no fim da transação; sem isso
    -- apagar o veículo falhava (a cascata apaga as peças antes de apagar estas linhas pelas manutenções)
    FOREIGN KEY (part_id, vehicle_id) REFERENCES parts(id, vehicle_id) ON DELETE NO ACTION
        DEFERRABLE INITIALLY DEFERRED
);
CREATE INDEX ON maintenance_parts (part_id);

CREATE TABLE inspections (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id  UUID NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
    date        DATE    NOT NULL,
    result      VARCHAR(9) NOT NULL CHECK (result IN ('APROVADA','REPROVADA')),
    amount      NUMERIC(10,2) NOT NULL CHECK (amount >= 0),
    km          INTEGER NOT NULL CHECK (km >= 0),            -- copiada do veículo no registo
    valid_until DATE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK ((result = 'APROVADA') = (valid_until IS NOT NULL))   -- R6
);

CREATE TABLE inspection_deficiencies (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    inspection_id UUID NOT NULL REFERENCES inspections(id) ON DELETE CASCADE,
    description   VARCHAR(255) NOT NULL,
    severity      VARCHAR(7) NOT NULL CHECK (severity IN ('LIGEIRA','GRAVE'))
);

CREATE TABLE alerts (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id   UUID NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
    type         VARCHAR(10) NOT NULL CHECK (type IN ('IUC','SEGURO','INSPECAO','MANUTENCAO')),
    trigger      VARCHAR(4)  NOT NULL CHECK (trigger IN ('KM','DATA')),
    status       VARCHAR(7)  NOT NULL DEFAULT 'ABERTO' CHECK (status IN ('ABERTO','FECHADO')),
    title        VARCHAR(100) NOT NULL,
    part_id      UUID,
    deadline_id  UUID,
    due_date     DATE,
    target_km    INTEGER CHECK (target_km >= 0),               -- kmRemaining = target_km - vehicles.km (D6)
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    closed_at    TIMESTAMPTZ,
    -- D10 (MATCH SIMPLE: só verificadas quando part_id / deadline_id não são nulos)
    FOREIGN KEY (part_id, vehicle_id) REFERENCES parts(id, vehicle_id) ON DELETE CASCADE,
    FOREIGN KEY (deadline_id, vehicle_id, type) REFERENCES deadlines(id, vehicle_id, type) ON DELETE CASCADE,
    CHECK (num_nonnulls(part_id, deadline_id) = 1),                    -- R4: uma só origem
    CHECK ((type = 'MANUTENCAO') = (part_id IS NOT NULL)),
    CHECK (type = 'MANUTENCAO' OR trigger = 'DATA'),                   -- prazos só disparam por data
    CHECK (trigger <> 'KM' OR target_km IS NOT NULL),
    CHECK (trigger <> 'DATA' OR due_date IS NOT NULL),
    CHECK ((status = 'FECHADO') = (closed_at IS NOT NULL))
);
-- R1: no máximo um alerta aberto por peça (e por prazo); é isto que faz o POST /alerts devolver 409
CREATE UNIQUE INDEX alerts_one_open_part     ON alerts (part_id)     WHERE status = 'ABERTO';
CREATE UNIQUE INDEX alerts_one_open_deadline ON alerts (deadline_id) WHERE status = 'ABERTO';
CREATE INDEX ON alerts (vehicle_id, status);

CREATE TABLE obd_devices (
    device_id       VARCHAR(50) PRIMARY KEY,                -- etiqueta do dispositivo (= utilizador MQTT)
    vehicle_id      UUID NOT NULL UNIQUE REFERENCES vehicles(id) ON DELETE CASCADE,  -- 1:1
    name            VARCHAR(60),
    paired_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    last_reading_at TIMESTAMPTZ
);

CREATE TABLE obd_readings (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id UUID NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
    device_id  VARCHAR(50) NOT NULL,          -- sem FK: a leitura fica mesmo que o dispositivo seja trocado
    read_at    TIMESTAMPTZ NOT NULL,
    km         INTEGER CHECK (km >= 0),
    oil_temp   NUMERIC(5,1),
    latitude   NUMERIC(9,6) CHECK (latitude BETWEEN -90 AND 90),
    longitude  NUMERIC(9,6) CHECK (longitude BETWEEN -180 AND 180),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK ((latitude IS NULL) = (longitude IS NULL))
);
CREATE INDEX ON obd_readings (vehicle_id, read_at DESC);   -- GET /readings/latest

-- D5: despesas (GET /vehicles/{id}/expenses). Ver D11 antes de alterar colunas destas tabelas.
CREATE VIEW expenses AS
    SELECT id, vehicle_id, 'MANUTENCAO'::varchar AS category, date, cost AS amount, notes AS description, km
      FROM maintenances
    UNION ALL
    SELECT id, vehicle_id, 'INSPECAO', date, amount, NULL, NULL
      FROM inspections
    UNION ALL
    SELECT id, vehicle_id, type, paid_at, amount, description, NULL
      FROM deadlines
     WHERE status = 'CONCLUIDO' AND type IN ('IUC','SEGURO');
```

== 4. Cálculos usados pela SAPI (não guardados) ==
next_km        = last_service_km + interval_km                                  (se interval_km não for nulo)
km_remaining   = next_km - vehicles.km
Alert.kmRemaining = alerts.target_km - vehicles.km                              (acessor no modelo Alert)
next_date      = last_service_date + interval_months meses                      (se interval_months não for nulo)
days_remaining = next_date - hoje
nextAlertKm    = MIN(next_km) das peças do veículo com interval_km e sem alerta ABERTO   (PUT /mileage)
nextService    = peça com menor km_remaining                                     (GET /vehicles)
openAlerts     = COUNT(alerts) com status ABERTO                                 (GET /vehicles)
Alert.userId   = vehicles.user_id do veículo do alerta (junção; não se guarda em alerts)

== 5. Plano de peças por defeito (seeder / POST /vehicles) ==
Regra: todas as peças com intervalo de 10 000 km e sem intervalo em meses (interval_months = NULL),
origem CATALOGO. Ao criar o veículo, last_service_km = km indicada e last_service_date = data de criação
(sem isto, um carro criado com 68 000 km teria todas as peças vencidas e alertas logo no primeiro minuto).
O utilizador ajusta depois em PATCH /vehicles/{vehicleId}/parts/{partId} (se um intervalo ficar diferente,
a origem passa a UTILIZADOR).

CARRO (7 peças)
  - Óleo e filtro de óleo
  - Filtro de ar
  - Filtro de habitáculo
  - Filtro de combustível
  - Pastilhas de travão
  - Pneus
  - Velas de ignição

MOTO (6 peças)
  - Óleo e filtro de óleo
  - Filtro de ar
  - Pastilhas de travão
  - Corrente de transmissão
  - Pneus
  - Velas de ignição

No código fica numa constante/config da SAPI Veículos (os valores por defeito ficam no código, não no domínio):
backend/sapi-vehicles/config/default_parts.php.

== 5.1 Dados de desenvolvimento (seeders) ==
Utilizador de demonstração: demo@carmotocare.pt / demo1234, com UUID fixo
  00000000-0000-4000-8000-000000000001
Está na constante DatabaseSeeder::DEMO_USER_ID dos seeders de sapi-users e de sapi-vehicles; as duas têm de
ser iguais. O seeder de sapi-vehicles cria para esse utilizador um carro e uma mota, cada um com as peças por
defeito e um prazo pendente de IUC, SEGURO e INSPECAO.
Carregar (sapi-users primeiro: por causa da FK, os veículos de demonstração precisam do utilizador):
  docker compose exec sapi-users php artisan db:seed
  docker compose exec sapi-vehicles php artisan db:seed
Não usar migrate:fresh (D3). Para recomeçar do zero, ver "Reset da base de dados" no README da raiz.

== 6. Pontos a confirmar ==
- As regras do domínio foram alinhadas com as APIs em docs/data/domain-rules.md (R2 inclui INSPECAO; R9: os
  alertas não se fecham à mão). O documento original do domínio v6.4 (relatório) ainda tem a versão antiga.
