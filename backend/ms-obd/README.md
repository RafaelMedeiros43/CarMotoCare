# Microserviço OBD

Subscreve as leituras do dispositivo OBD, valida-as (regras V1–V5), guarda a leitura e atualiza a quilometragem na SAPI Veículos, e publica o novo estado do veículo.

**Contrato:** [`docs/mqtt/mqtt-contract.md`](../../docs/mqtt/mqtt-contract.md) (sem endpoints próprios, só `/health`)

**No relatório:** Microserviço OBD

Subscreve: `obd/telemetry/{deviceId}`. Publica: `app/vehicle/state`. Chama a SAPI Veículos.
