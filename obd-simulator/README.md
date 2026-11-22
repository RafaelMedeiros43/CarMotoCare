# Simulador OBD

Simula o dispositivo OBD de um veículo: publica uma leitura (km, temperatura do óleo e GPS) a cada
~5 segundos no tópico `obd/telemetry/{deviceId}`. Representa o dispositivo no carro, por isso fica
fora do `backend/`: só fala com o broker.

**Contrato:** [`docs/mqtt/mqtt-contract.md`](../docs/mqtt/mqtt-contract.md) (secção 7 tem o código de
referência e as variáveis de ambiente). Python 3 com `paho-mqtt`.
