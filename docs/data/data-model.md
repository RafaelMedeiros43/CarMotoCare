# Modelo de dados (v3)

Substitui o modelo do MySQL Workbench mostrado na apresentação da fase 2. Alinhado com a SAPI Veículos v8,
a SAPI Utilizadores v7 e o domínio v6.4. Pensado para passar diretamente para migrations do Laravel.

== 1. Decisões ==
D1  Chaves primárias UUID (gen_random_uuid()), como nas APIs. Em Laravel: trait HasUuids nos modelos.
D2  Nomes em inglês e snake_case (vehicles, parts, ...), iguais aos campos das APIs. Assim o Eloquent funciona
    sem configurações extra e os JSON saem quase diretos dos modelos (camelCase na API, snake_case na base).
D3  Dois schemas na mesma base: "users" (sapi-users) e "vehicles" (sapi-vehicles). Cada SAPI só
    acede ao seu. vehicles.user_id é um UUID sem chave estrangeira para users: a ligação entre serviços faz-se
    pela API (é o que justifica serem duas SAPIs). A EAPI garante que o userId vem do JWT.
D4  Enumerados como VARCHAR + CHECK (é o que o $table->enum() do Laravel gera em PostgreSQL).
D5  Despesas não têm tabela: são uma VIEW sobre manutenções, inspeções e prazos pagos (regra R7, sem duplicar
    valores). GET /vehicles/{id}/expenses lê a view.
D6  Valores calculados não se guardam: custo total, nextKm, kmRemaining, nextDate, daysRemaining, nextAlertKm e
    openAlerts calculam-se nas consultas.
D7  Regras de negócio garantidas pela base onde é possível (índices únicos parciais e CHECK), para não
    dependerem só do código: R1, R2, R4, R6, R10 e "pelo menos um limite ativo".
D8  Leituras OBD guardam só o que a API usa (km, temperatura do óleo, GPS). Velocidade, rpm, combustível e
    radiador do modelo antigo saíram; se o simulador os enviar, são ignorados.
D9  A credencial MQTT de cada dispositivo fica no RabbitMQ (um utilizador por dispositivo), não nesta base.

== 2. Alterações face ao modelo do Workbench ==
- IDs INT -> UUID; nomes PT -> EN; MySQL -> PostgreSQL.
- veiculo: ano -> first_registration (AAAA-MM); custo_Total removido (calculado); matrícula única.
- peca: + vehicle_id (cada veículo tem as suas peças), + last_service_km, + interval_source; intervalo em km
  e/ou meses com pelo menos um ativo.
- prazolegal -> deadlines: + status, + paid_at, + description; "validade" passa a due_date.
- Alerta -> alerts: + trigger, + due_date, + target_km, + km_remaining, + closed_at; origem é peça OU prazo
  (a inspeção gera alertas através do prazo de tipo INSPECAO, por isso alerts deixa de ter idInspecao).
- ItemInspecao -> inspection_deficiencies (description, severity), como na API.
- dispositivoOBD: estado removido (calcula-se pela hora da última leitura); + name, + last_reading_at.
- LeituraOBD: só km, oil_temp, latitude, longitude.
- Nova VIEW expenses.

== 3. DDL (PostgreSQL) ==

```sql
CREATE SCHEMA users;
CREATE SCHEMA vehicles;

-- ---------- sapi-users ----------
CREATE TABLE users.users (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name          VARCHAR(100) NOT NULL CHECK (length(name) > 0),
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,          -- bcrypt (Hash::make do Laravel)
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------- sapi-vehicles ----------
CREATE TABLE vehicles.vehicles (
    id                 UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id            UUID NOT NULL,              -- sem FK (D3)
    type               VARCHAR(5)  NOT NULL CHECK (type IN ('CARRO','MOTO')),
    brand              VARCHAR(50) NOT NULL,
    model              VARCHAR(50) NOT NULL,
    first_registration CHAR(7)     NOT NULL CHECK (first_registration ~ '^\d{4}-(0[1-9]|1[0-2])$'),
    plate              VARCHAR(8)  NOT NULL UNIQUE CHECK (plate ~ '^[A-Z0-9]{2}-[A-Z0-9]{2}-[A-Z0-9]{2}$'),
    km                 INTEGER     NOT NULL CHECK (km >= 0),     -- R3 garantida na SAPI (409 se descer)
    created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ON vehicles.vehicles (user_id);

CREATE TABLE vehicles.parts (
    id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id        UUID NOT NULL REFERENCES vehicles.vehicles(id) ON DELETE CASCADE,
    name              VARCHAR(60) NOT NULL,
    interval_km       INTEGER CHECK (interval_km >= 1),
    interval_months   INTEGER CHECK (interval_months >= 1),
    interval_source   VARCHAR(10) NOT NULL DEFAULT 'CATALOGO' CHECK (interval_source IN ('CATALOGO','UTILIZADOR')),
    last_service_km   INTEGER NOT NULL CHECK (last_service_km >= 0),
    last_service_date DATE    NOT NULL,
    created_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (vehicle_id, name),
    CHECK (interval_km IS NOT NULL OR interval_months IS NOT NULL)   -- pelo menos um limite ativo
);

CREATE TABLE vehicles.deadlines (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id  UUID NOT NULL REFERENCES vehicles.vehicles(id) ON DELETE CASCADE,
    type        VARCHAR(8)  NOT NULL CHECK (type IN ('IUC','SEGURO','INSPECAO')),
    status      VARCHAR(9)  NOT NULL DEFAULT 'PENDENTE' CHECK (status IN ('PENDENTE','CONCLUIDO')),
    due_date    DATE        NOT NULL,
    paid_at     DATE,
    amount      NUMERIC(10,2) CHECK (amount > 0),
    description VARCHAR(255),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (status = 'PENDENTE' OR paid_at IS NOT NULL)
);
-- R2: no máximo um prazo pendente por tipo e veículo
CREATE UNIQUE INDEX deadlines_one_pending ON vehicles.deadlines (vehicle_id, type) WHERE status = 'PENDENTE';
CREATE INDEX ON vehicles.deadlines (status, due_date);

CREATE TABLE vehicles.maintenances (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id UUID NOT NULL REFERENCES vehicles.vehicles(id) ON DELETE CASCADE,
    type       VARCHAR(20) NOT NULL CHECK (type IN ('REVISAO_PERIODICA','MUDANCA_OLEO','SUBSTITUICAO_PNEUS',
                                                    'REPARACAO','AVARIA','OUTRO')),
    date       DATE    NOT NULL,
    km         INTEGER NOT NULL CHECK (km >= 0),
    cost       NUMERIC(10,2) NOT NULL CHECK (cost >= 0),   -- custo total (R7)
    notes      TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE vehicles.maintenance_parts (
    maintenance_id UUID NOT NULL REFERENCES vehicles.maintenances(id) ON DELETE CASCADE,
    part_id        UUID NOT NULL REFERENCES vehicles.parts(id) ON DELETE CASCADE,
    cost           NUMERIC(10,2) CHECK (cost >= 0),          -- informativo (R7)
    PRIMARY KEY (maintenance_id, part_id)
);

CREATE TABLE vehicles.inspections (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id  UUID NOT NULL REFERENCES vehicles.vehicles(id) ON DELETE CASCADE,
    date        DATE    NOT NULL,
    result      VARCHAR(9) NOT NULL CHECK (result IN ('APROVADA','REPROVADA')),
    amount      NUMERIC(10,2) NOT NULL CHECK (amount >= 0),
    km          INTEGER NOT NULL CHECK (km >= 0),            -- copiada do veículo no registo
    valid_until DATE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK ((result = 'APROVADA') = (valid_until IS NOT NULL))   -- R6
);

CREATE TABLE vehicles.inspection_deficiencies (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    inspection_id UUID NOT NULL REFERENCES vehicles.inspections(id) ON DELETE CASCADE,
    description   VARCHAR(255) NOT NULL,
    severity      VARCHAR(7) NOT NULL CHECK (severity IN ('LIGEIRA','GRAVE'))
);

CREATE TABLE vehicles.alerts (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id   UUID NOT NULL REFERENCES vehicles.vehicles(id) ON DELETE CASCADE,
    type         VARCHAR(10) NOT NULL CHECK (type IN ('IUC','SEGURO','INSPECAO','MANUTENCAO')),
    trigger      VARCHAR(4)  NOT NULL CHECK (trigger IN ('KM','DATA')),
    status       VARCHAR(7)  NOT NULL DEFAULT 'ABERTO' CHECK (status IN ('ABERTO','FECHADO')),
    title        VARCHAR(100) NOT NULL,
    part_id      UUID REFERENCES vehicles.parts(id) ON DELETE CASCADE,
    deadline_id  UUID REFERENCES vehicles.deadlines(id) ON DELETE CASCADE,
    due_date     DATE,
    target_km    INTEGER CHECK (target_km >= 0),
    km_remaining INTEGER,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    closed_at    TIMESTAMPTZ,
    CHECK (num_nonnulls(part_id, deadline_id) = 1),                    -- R4: uma só origem
    CHECK ((type = 'MANUTENCAO') = (part_id IS NOT NULL)),
    CHECK ((status = 'FECHADO') = (closed_at IS NOT NULL))
);
-- R1: no máximo um alerta aberto por peça (e por prazo); é isto que faz o POST /alerts devolver 409
CREATE UNIQUE INDEX alerts_one_open_part     ON vehicles.alerts (part_id)     WHERE status = 'ABERTO';
CREATE UNIQUE INDEX alerts_one_open_deadline ON vehicles.alerts (deadline_id) WHERE status = 'ABERTO';
CREATE INDEX ON vehicles.alerts (vehicle_id, status);

CREATE TABLE vehicles.obd_devices (
    device_id       VARCHAR(50) PRIMARY KEY,                -- etiqueta do dispositivo (= utilizador MQTT)
    vehicle_id      UUID NOT NULL UNIQUE REFERENCES vehicles.vehicles(id) ON DELETE CASCADE,  -- 1:1
    name            VARCHAR(60),
    paired_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    last_reading_at TIMESTAMPTZ
);

CREATE TABLE vehicles.obd_readings (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vehicle_id UUID NOT NULL REFERENCES vehicles.vehicles(id) ON DELETE CASCADE,
    device_id  VARCHAR(50) NOT NULL,          -- sem FK: a leitura fica mesmo que o dispositivo seja trocado
    read_at    TIMESTAMPTZ NOT NULL,
    km         INTEGER CHECK (km >= 0),
    oil_temp   NUMERIC(5,1),
    latitude   NUMERIC(9,6) CHECK (latitude BETWEEN -90 AND 90),
    longitude  NUMERIC(9,6) CHECK (longitude BETWEEN -180 AND 180),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK ((latitude IS NULL) = (longitude IS NULL))
);
CREATE INDEX ON vehicles.obd_readings (vehicle_id, read_at DESC);   -- GET /readings/latest

-- D5: despesas (GET /vehicles/{id}/expenses)
CREATE VIEW vehicles.expenses AS
    SELECT id, vehicle_id, 'MANUTENCAO'::varchar AS category, date, cost AS amount, notes AS description, km
      FROM vehicles.maintenances
    UNION ALL
    SELECT id, vehicle_id, 'INSPECAO', date, amount, NULL, NULL
      FROM vehicles.inspections
    UNION ALL
    SELECT id, vehicle_id, type, paid_at, amount, description, NULL
      FROM vehicles.deadlines
     WHERE status = 'CONCLUIDO' AND type IN ('IUC','SEGURO');
```

== 4. Cálculos usados pela SAPI (não guardados) ==
next_km        = last_service_km + interval_km                                  (se interval_km não for nulo)
km_remaining   = next_km - vehicles.km
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
O utilizador ajusta depois em PATCH /parts/{id} (passa a origem UTILIZADOR).

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

No código fica numa constante/config da SAPI Veículos (os valores por defeito ficam no código, não no domínio).

== 6. Pontos a confirmar ==
- As regras do domínio foram alinhadas com as APIs em docs/data/domain-rules.md (R2 inclui INSPECAO; R9: os
  alertas não se fecham à mão). O documento original do domínio v6.4 (relatório) ainda tem a versão antiga.
