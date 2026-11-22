# Contrato MQTT e simulador OBD (v2)

Alinhado com a arquitetura v17 e a SAPI Veículos v8.

== 1. Broker ==
- RabbitMQ com o plugin rabbitmq_mqtt (MQTT 3.1.1; os "/" dos tópicos passam a "." nas routing keys).
- Porta 8883 (MQTT + TLS) exposta só para os dispositivos OBD; porta 1883 só na rede interna (microserviços e
  EAPI Web).
- Autenticação: um utilizador RabbitMQ por dispositivo (username = deviceId, palavra-passe própria), com
  permissão de publicar só no seu tópico. Os microserviços e a EAPI têm cada um o seu utilizador interno.
  (Permissões por tópico com a variável {username} do RabbitMQ: confirmar na configuração.)
- Payloads em JSON UTF-8, nomes em camelCase, datas em ISO 8601 UTC (ex.: "2026-11-22T21:51:00Z").

== 2. Resumo ==
| Tópico                   | Publica                      | Subscreve                          | QoS | Sessão do subscritor |
|--------------------------|------------------------------|------------------------------------|-----|----------------------|
| obd/telemetry/{deviceId} | Dispositivo OBD (simulador)  | Microserviço OBD                   | 1   | persistente          |
| app/vehicle/state        | Microserviço OBD             | Microserviço Manutenção e Alertas  | 1   | persistente          |
| app/alert/created        | Microserviço Manutenção e Al.| EAPI Web (todas as instâncias)     | 1   | temporária           |

- QoS 1 = entregue pelo menos uma vez; repetições são inofensivas (km nunca desce e POST /alerts dá 409).
- Sessão persistente (clean session = false): se o microserviço estiver em baixo, o broker guarda as mensagens.
- A EAPI usa sessão temporária: um alerta perdido com a EAPI em baixo continua visível em GET /alerts.
- Fase 3: uma instância de cada microserviço (duas instâncias leriam a mesma mensagem duas vezes). A EAPI pode
  ter várias instâncias: todas recebem todos os alertas e cada uma envia aos utilizadores ligados a ela.
- Nenhuma mensagem é retida (retain = false).

== 3. obd/telemetry/{deviceId} ==
Leitura do dispositivo a cada ~5 s.

{
  "deviceId": "OBD-7F3A-0001",
  "readAt": "2026-11-22T21:51:00Z",
  "km": 9100,
  "oilTemp": 89.5,
  "gps": { "latitude": 37.741700, "longitude": -25.671000 }
}

Campos: deviceId e readAt obrigatórios; km, oilTemp e gps podem ser null (o dispositivo pode não suportar).
Campos extra são ignorados.

Validação no Microserviço OBD (leitura inválida é descartada e registada em log, sem resposta ao dispositivo):
V1  JSON válido e deviceId do payload igual ao {deviceId} do tópico.
V2  readAt não está mais de 1 min no futuro nem tem mais de 24 h.
V3  km inteiro >= 0; oilTemp entre -40 e 200; latitude -90..90, longitude -180..180.
V4  Dispositivo emparelhado: GET /obd-devices/{deviceId} na SAPI (404 = descartar).
V5  Salto de km plausível: aumento <= 250 km/h × tempo desde a leitura anterior (evita valores absurdos).
Depois de validada:
  1. POST /vehicles/{vehicleId}/readings (guarda a leitura, mesmo que a km não tenha subido).
  2. Se km não for null e for maior que a km do veículo: PUT /vehicles/{vehicleId}/mileage.
     Se a km for igual ou menor (409), não publica estado (R3: leitura inferior é ignorada para a km).
  3. Se a km subiu: publica app/vehicle/state.

== 4. app/vehicle/state ==
Publicado só quando a km do veículo sobe.

{
  "vehicleId": "3f6c1a52-8a7e-4c2b-9d61-0b8f2e7a4c11",
  "userId": "9b2d7e14-1c3a-4f5e-8a90-6d4c2b1e7f03",
  "km": 9100,
  "previousKm": 9095,
  "nextAlertKm": 10000,
  "readAt": "2026-11-22T21:51:00Z"
}

- userId vem de GET /obd-devices/{deviceId}; km, previousKm e nextAlertKm vêm da resposta de PUT /mileage.
- nextAlertKm pode ser null (nenhuma peça com limite por km sem alerta aberto).
Regra no Microserviço Manutenção e Alertas:
  se nextAlertKm != null e nextAlertKm - km <= 1000:
      GET /parts?vehicleId={vehicleId}&kmRemainingLte=1000&openAlert=false
      por cada peça: POST /alerts (type MANUTENCAO, trigger KM, partId, targetKm = nextKm,
                     kmRemaining, title = nome da peça) e publica app/alert/created
  senão: nada.

== 5. app/alert/created ==
Publicado depois de cada POST /alerts com sucesso (201), tanto por km como pela tarefa diária.

{
  "alertId": "c1e8b3f0-5d2a-4e7b-9a14-2f6d8c0b7e95",
  "userId": "9b2d7e14-1c3a-4f5e-8a90-6d4c2b1e7f03",
  "vehicleId": "3f6c1a52-8a7e-4c2b-9d61-0b8f2e7a4c11",
  "type": "MANUTENCAO",
  "trigger": "KM",
  "title": "Óleo e filtro de óleo",
  "partId": "5a0d9c3e-7b21-4f68-8e4a-1c9b6d2f0a77",
  "deadlineId": null,
  "dueDate": null,
  "targetKm": 10000,
  "kmRemaining": 900,
  "createdAt": "2026-11-22T21:51:01Z"
}

- userId é obrigatório: a EAPI usa-o para escolher o canal do WebSocket (canal privado users.{userId} no
  Laravel Reverb). Por isso a SAPI Veículos v8 passou a devolver userId no Alert (o microserviço não precisa
  de outro pedido para os alertas por data).
- A EAPI não guarda nada: reencaminha o payload para o browser, que atualiza a lista e o contador.

== 6. Tarefa diária (Microserviço Manutenção e Alertas, sem tópico de entrada) ==
Laravel scheduler, uma vez por dia (ex.: 08:00):
  - GET /deadlines?dueBefore=hoje+30d (IUC, seguro, inspeção) -> POST /alerts (trigger DATA, deadlineId, dueDate)
  - GET /parts?dueWithinDays=30&openAlert=false              -> POST /alerts (trigger DATA, partId)
  - GET /parts?kmRemainingLte=1000&openAlert=false           -> POST /alerts (trigger KM)  [verificação de segurança]
  Cada 201 publica app/alert/created; cada 409 é ignorado.
  (Os 30 dias de antecedência são um valor proposto: confirmar.)

== 7. Simulador OBD (Python) ==
Biblioteca: paho-mqtt (pip install paho-mqtt). Configuração por variáveis de ambiente, um processo por dispositivo.

  DEVICE_ID      etiqueta do dispositivo (tem de estar emparelhado na app)
  MQTT_HOST      endereço do broker            MQTT_PORT      8883 (TLS) ou 1883 (local)
  MQTT_PASSWORD  palavra-passe do dispositivo  START_KM       km inicial (igual à do veículo na app)
  INTERVAL_S     segundos entre leituras (5)   SPEED_FACTOR   multiplica a distância (1 = real; 200 para a demo
                                                              chegar aos 1000 km do alerta em poucos minutos)

simulator.py
------------------------------------------------------------------------------------------
import json, os, random, ssl, time
from datetime import datetime, timezone
import paho.mqtt.client as mqtt

DEVICE_ID = os.environ["DEVICE_ID"]
HOST = os.getenv("MQTT_HOST", "localhost")
PORT = int(os.getenv("MQTT_PORT", "1883"))
INTERVAL = float(os.getenv("INTERVAL_S", "5"))
FACTOR = float(os.getenv("SPEED_FACTOR", "1"))

km = float(os.getenv("START_KM", "0"))
oil = 20.0                               # motor frio
lat, lon = 37.7417, -25.6710             # Ponta Delgada
topic = f"obd/telemetry/{DEVICE_ID}"

client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, client_id=DEVICE_ID)
client.username_pw_set(DEVICE_ID, os.getenv("MQTT_PASSWORD", ""))
if PORT == 8883:
    client.tls_set(cert_reqs=ssl.CERT_REQUIRED)
client.connect(HOST, PORT)
client.loop_start()

while True:
    speed = random.uniform(0, 120)                       # km/h
    km += speed * INTERVAL / 3600 * FACTOR
    oil = min(oil + random.uniform(0.5, 2), random.uniform(88, 95))
    lat += random.uniform(-0.0005, 0.0005)
    lon += random.uniform(-0.0005, 0.0005)
    reading = {
        "deviceId": DEVICE_ID,
        "readAt": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "km": int(km),
        "oilTemp": round(oil, 1),
        "gps": {"latitude": round(lat, 6), "longitude": round(lon, 6)},
    }
    client.publish(topic, json.dumps(reading), qos=1)
    print(reading)
    time.sleep(INTERVAL)
------------------------------------------------------------------------------------------
Nota: com SPEED_FACTOR alto a validação V5 (250 km/h) rejeita as leituras. Para a demo, o limite da V5 deve
vir de uma variável de ambiente do Microserviço OBD (ex.: MAX_SPEED_KMH), ou desliga-se em desenvolvimento.
