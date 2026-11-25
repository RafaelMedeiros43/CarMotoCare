# OBD Simulator (PHP 8.3 + php-mqtt/client)

Simulador de um dispositivo OBD do CarMotoCare. Publica telemetria por MQTT no RabbitMQ.
Implementado **exclusivamente em PHP 8.3** com a biblioteca [`php-mqtt/client`](https://github.com/php-mqtt/client).

## Tópico e payload

- Tópico: `obd/telemetry/{DEVICE_ID}`
- QoS 1, `retain = false`
- Payload (a cada `INTERVAL_S` segundos):

```json
{
  "deviceId": "OBD-7F3A-0001",
  "readAt": "2026-11-23T12:00:00Z",
  "km": 68421,
  "oilTemp": 89.5,
  "gps": { "latitude": 37.741700, "longitude": -25.671000 }
}
```

## Simulação

- `km`: acumulado em `float` (`velocidade * INTERVAL_S / 3600 * SPEED_FACTOR`) e enviado como inteiro. Nunca recua em modo `normal`.
- `oilTemp`: sobe gradualmente de 20.0 °C e estabiliza entre 88.0 e 95.0 °C (1 casa decimal).
- `gps`: começa em Ponta Delgada (37.741700, -25.671000) e avança suavemente (6 casas decimais).
- `readAt`: ISO 8601 em UTC.

## Variáveis de ambiente

| Variável        | Default                | Descrição |
|-----------------|------------------------|-----------|
| `DEVICE_ID`     | — (obrigatória)        | Identificador do dispositivo, ex.: `OBD-7F3A-0001` |
| `MQTT_HOST`     | `rabbitmq`             | Host do broker |
| `MQTT_PORT`     | `1883`                 | Porta do broker |
| `MQTT_USERNAME` | igual a `DEVICE_ID`    | Utilizador MQTT |
| `MQTT_PASSWORD` | vazio                  | Palavra-passe MQTT |
| `MQTT_USE_TLS`  | `false` (`true` se porta 8883) | Ativa TLS |
| `START_KM`      | `68420`                | Quilometragem inicial |
| `INTERVAL_S`    | `5`                    | Segundos entre publicações |
| `SPEED_FACTOR`  | `1`                    | Multiplicador de tempo simulado (acelera km e aquecimento do óleo) |
| `MODE`          | `normal`               | `normal`, `invalid-km` ou `bad-payload` |

## Modos de teste

- `normal`: apenas leituras válidas.
- `invalid-km`: a cada 3 mensagens envia um `km` inferior ao atual (testa a regra R3 / HTTP 409).
- `bad-payload`: a cada 3 mensagens envia, alternadamente, `oilTemp: 999` (fora de intervalo) ou um `deviceId` trocado (testa as regras V1/V3).

Os logs marcam as leituras inválidas com `LEITURA INVÁLIDA`.

## Executar localmente (PHP)

Requer PHP 8.3 e Composer. A partir da pasta `obd-simulator/`:

```bash
composer install
DEVICE_ID=OBD-7F3A-0001 MQTT_HOST=localhost MQTT_USERNAME=guest MQTT_PASSWORD=guest php simulator.php
```

Para a paragem limpa com `SIGINT`/`SIGTERM` é necessária a extensão `pcntl` (existe na CLI de PHP em Linux/macOS e nas imagens Docker habituais com Laravel).

## Executar em Docker

O serviço `obd-simulator` usa `profiles: ["simulator"]`, por isso não arranca com um `docker compose up` normal.
Na raiz do projeto (onde está o `docker-compose.yml`):

```bash
docker compose --profile simulator up obd-simulator
docker compose --profile simulator logs -f obd-simulator
```

Para testar os modos de erro: `MODE=invalid-km docker compose --profile simulator up obd-simulator`.

## Comportamento

- Se o broker não estiver acessível, tenta religar a cada 5 segundos sem terminar o processo.
- Se a ligação cair durante a execução, volta a ligar automaticamente.
- `SIGINT`/`SIGTERM` terminam o ciclo e desligam a ligação MQTT de forma limpa.
- Configuração inválida termina com código de saída `64`.
