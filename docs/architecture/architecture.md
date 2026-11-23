# Arquitetura (v17)

CarMotoCare — Diagrama de arquitetura v17 (22 nov. 2026)
Muda em relação à v16:
- A SAPI Veículos devolve nextAlertKm na resposta de PUT /mileage (menor nextKm das peças sem alerta aberto).
- O Microserviço OBD põe esse valor no tópico app/vehicle/state.
- O Microserviço Manutenção e Alertas só consulta a SAPI quando nextAlertKm - km <= 1000, e tem uma tarefa diária
  para os alertas por data (IUC, seguro, inspeção, peças com intervalo em meses) e verificação de segurança das peças.
- Sem cache e sem componentes novos; mudam só rótulos.

Payload de app/vehicle/state: { vehicleId, userId, km, previousKm, nextAlertKm, readAt } (ver contrato MQTT)

```mermaid
---
config:
  layout: elk
---
flowchart TB
 subgraph DEVICE["Dispositivo"]
        OBD["Dispositivo OBD Simulado<br>envia leituras a cada ~5 s<br>credencial própria"]
  end
 subgraph CLIENT["Cliente Web"]
        WEB["Web App<br>(React, PWA)"]
  end
 subgraph EAPI["Experience APIs"]
        E_WEB["EAPI Web<br>REST + WebSocket<br>autenticação (JWT)<br>reencaminha os alertas do broker<br>para o browser"]
  end
 subgraph BROKER["Message Broker (RabbitMQ, MQTT)<br><br>"]
        T_ALERT[["app/alert/created<br>aviso de alerta novo"]]
        T_STATE[["app/vehicle/state<br>novo estado (km, previousKm<br>e nextAlertKm)"]]
        T_TEL[["obd/telemetry/{deviceId}<br>leituras do OBD"]]
  end
 subgraph MS["Microserviços"]
        M_OBD["Microserviço OBD<br>valida a leitura<br>e associa-a ao veículo"]
        M_MA["Microserviço Manutenção e Alertas<br>alertas por km (nextAlertKm - km ≤ 1000)<br>tarefa diária: alertas por data"]
  end
 subgraph SAPI["System APIs (rede interna)"]
        S_OFI["SAPI Oficinas"]
        S_USER["SAPI Utilizadores<br>contas e passwords"]
        S_VEH["SAPI Veículos<br>veículos, km, prazos, peças e intervalos,<br>manutenções, inspeções, custos e alertas<br>emparelhamento OBD, leituras e último estado"]
  end
 subgraph DADOS["Base de dados"]
        DB[("PostgreSQL<br>")]
  end
 subgraph EXTERNOS["Serviços externos"]
        OVERPASS["Overpass API<br>oficinas do OpenStreetMap"]
        OSM["OpenStreetMap<br>mapa"]
  end
    WEB -- HTTPS --> GW["Load Balancer<br>(Nginx)"]
    WEB -- mapa --> OSM
    GW -- HTTP + WS --> E_WEB
    OBD -. ↓ publica<br>MQTT + TLS (8883) .- T_TEL
    T_TEL -. ↓ entrega<br>MQTT .- M_OBD
    M_OBD -. ↑ publica<br>MQTT .- T_STATE
    T_STATE -. ↓ entrega<br>MQTT .- M_MA
    M_MA -. ↑ publica<br>MQTT .- T_ALERT
    E_WEB -. ↑ entrega<br>MQTT .- T_ALERT
    E_WEB -- criar e remover veículo, manutenções, inspeções,<br>intervalos, prazos, emparelhamento OBD<br>GET do veículo ao entrar na app (km e estado)<br>histórico, custos e alertas (leitura) --> S_VEH
    E_WEB -- pesquisa de oficinas --> S_OFI
    E_WEB -- registo e login --> S_USER
    M_OBD -- atualiza o km e guarda a leitura<br>recebe nextAlertKm --> S_VEH
    M_MA -- perto do limite: peças a alertar<br>diário: prazos e inspeções<br>cria alertas --> S_VEH
    S_VEH --> DB
    S_USER --> DB
    S_OFI -- HTTPS --> OVERPASS
```
