# Regras de negócio (domínio v6.4)

> Nota: onde este texto e as APIs não coincidem, valem as APIs (`docs/api`). Diferenças conhecidas:
> os alertas de peças **não** se marcam à mão (fecham ao registar a manutenção) e também há prazo
> pendente de INSPECAO. A tabela de prazos e o 409 de `POST /alerts` seguem as APIs.

- **R1** Uma peça tem no máximo um alerta ABERTO.
- **R2** Um veículo tem no máximo um prazo PENDENTE por tipo (IUC, SEGURO e INSPECAO).
- **R3** A quilometragem do veículo nunca diminui. Leitura OBD inferior é ignorada (o `PUT /mileage` devolve 409).
- **R4** Um alerta tem exatamente uma origem: peça ou prazo legal.
- **R5** Ao pagar um prazo: passa a CONCLUIDO, o alerta fecha e cria-se um novo prazo PENDENTE com a nova
  validade indicada no pagamento.
- **R6** Inspeção: a validade só existe se o resultado for APROVADA. O alerta de inspeção fecha quando se
  regista uma nova inspeção aprovada.
- **R7** O custo da manutenção é o total (peças e mão de obra); o custo por peça é informativo. O custo total
  do veículo soma manutenções, inspeções e prazos pagos.
- **R8** Registar uma manutenção com uma peça atualiza a km e a data do último serviço dessa peça e fecha o
  seu alerta. A manutenção não altera a quilometragem do veículo.
- **R9** O utilizador não cria nem fecha alertas: só o sistema os cria, e fecham quando o utilizador regista a
  manutenção da peça, o pagamento de IUC/seguro ou a inspeção.
- **R10** Apagar um veículo apaga em cascata tudo o que lhe pertence (peças, manutenções, inspeções, prazos,
  alertas, dispositivo OBD e leituras).
