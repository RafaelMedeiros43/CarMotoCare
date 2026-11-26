# Microserviço OBD

Subscreve as leituras do dispositivo OBD, valida-as (regras V1–V5), guarda a leitura e atualiza a quilometragem na SAPI Veículos, e publica o novo estado do veículo.

**Contrato:** [`docs/mqtt/mqtt-contract.md`](../../docs/mqtt/mqtt-contract.md) (sem endpoints próprios, só `/health`)

**No relatório:** Microserviço OBD

Subscreve: `obd/telemetry/{deviceId}`. Publica: `app/vehicle/state`. Chama a SAPI Veículos.

## Como corre

| Contentor (docker-compose) | Comando | Função |
|---|---|---|
| `ms-obd` | `php artisan serve` | só `GET /health` |
| `ms-obd-consumer` | `php artisan obd:consume` | daemon MQTT |

`obd:consume` liga ao RabbitMQ com sessão persistente (`MQTT_CLIENT_ID`), QoS 1, subscreve `obd/telemetry/+` e, se o broker cair, volta a ligar de 5 em 5 s. Termina de forma limpa com `SIGINT`/`SIGTERM`.

## Estrutura

- `app/Console/Commands/ConsumeObdTelemetry.php` — o daemon
- `app/Services/ObdTelemetryService.php` — validação V1–V5 e ações pós-validação
- `app/Clients/VehiclesApiClient.php` — chamadas HTTP à SAPI Veículos
- `app/Clients/PhpMqttPublisher.php` (+ `Contracts/MqttPublisherInterface.php`) — publicação em `app/vehicle/state`
- `config/mqtt.php` e `config/services.php` (`sapi_vehicles`) — configuração por variáveis de ambiente

## Testes

`php artisan test` (não precisa de broker nem de SAPI: usa `Http::fake()` e um fake do publicador).
