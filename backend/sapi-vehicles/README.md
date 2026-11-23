# SAPI Veículos

System API interna: veículos, quilometragem, peças e intervalos, prazos legais, manutenções, inspeções, custos, alertas, emparelhamento OBD e leituras. Usada pela EAPI Web e pelos dois microserviços.

**Contrato:** [`docs/api/sapi-vehicles.yaml`](../../docs/api/sapi-vehicles.yaml) (32 endpoints)

**No relatório:** SAPI Veículos

Base de dados: tabelas de veículos no schema `public`, partilhado com sapi-users; `vehicles.user_id` tem FK para `users` ([modelo](../../docs/data/data-model.md)). Migrações registadas em `migrations_vehicles`. Calcula o `nextAlertKm` devolvido em `PUT /vehicles/{id}/mileage`.
