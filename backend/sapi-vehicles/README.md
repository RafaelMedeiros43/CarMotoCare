# SAPI Veículos

System API interna: veículos, quilometragem, peças e intervalos, prazos legais, manutenções, inspeções, custos, alertas, emparelhamento OBD e leituras. Usada pela EAPI Web e pelos dois microserviços.

**Contrato:** [`docs/api/sapi-vehicles.yaml`](../../docs/api/sapi-vehicles.yaml) (32 endpoints)

**No relatório:** SAPI Veículos

Base de dados: schema `vehicles` ([modelo](../../docs/data/data-model.md)). Calcula o `nextAlertKm` devolvido em `PUT /vehicles/{id}/mileage`.
