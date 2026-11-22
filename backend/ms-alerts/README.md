# Microserviço Manutenção e Alertas

Cria os alertas: por km, quando `nextAlertKm - km <= 1000` no estado recebido, e por data, numa tarefa diária (IUC, seguro, inspeção e peças com intervalo em meses).

**Contrato:** [`docs/mqtt/mqtt-contract.md`](../../docs/mqtt/mqtt-contract.md) e [`docs/architecture/sequence-alerts.md`](../../docs/architecture/sequence-alerts.md) (sem endpoints próprios, só `/health`)

**No relatório:** Microserviço Manutenção e Alertas

Subscreve: `app/vehicle/state`. Publica: `app/alert/created`. Chama a SAPI Veículos.
