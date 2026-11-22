# Funcionamento da app

1. Login: o utilizador acede ao site (interage com a EAPI Web) e faz POST /auth/tokens. A EAPI chama POST /authentications na SAPI Utilizadores, que compara o e-mail e a palavra-passe com a base de dados. Se estiverem certos, a EAPI emite o JWT e o browser abre o WebSocket com esse token. O utilizador fica ligado até o token expirar.

2. Adicionar veículo: a EAPI faz POST /vehicles com o JSON; chega à SAPI Veículos (POST /vehicles), que cria o veículo com o plano de manutenção das peças por defeito (carro ou moto). Para cada data indicada (IUC, seguro, inspeção) a EAPI cria o prazo pendente (POST /vehicles/{id}/deadlines).

3. Emparelhar OBD: PUT /vehicles/{id}/obd-device na EAPI, que chama o mesmo endpoint na SAPI Veículos.

4. Telemetria: o OBD publica em obd/telemetry/{deviceId}. O Microserviço OBD subscreve, valida a leitura, descobre o veículo (GET /obd-devices/{deviceId}), guarda a leitura (POST /vehicles/{id}/readings) e atualiza a km (PUT /vehicles/{id}/mileage). A SAPI responde com nextAlertKm (menor km previsto de serviço entre as peças sem alerta aberto) e o microserviço publica app/vehicle/state (km, previousKm e nextAlertKm).

5. Alertas por km: o Microserviço Manutenção e Alertas subscreve app/vehicle/state e compara nextAlertKm com a km, sem consultar a SAPI. Só quando faltam 1000 km ou menos pede as peças a alertar (GET /parts?vehicleId&kmRemainingLte=1000&openAlert=false), cria um alerta por peça (POST /alerts) e publica app/alert/created. A EAPI Web subscreve esse tópico e envia o alerta ao browser por WebSocket (tempo real).
   Alertas por data: uma tarefa diária do mesmo microserviço consulta prazos pendentes (GET /deadlines), inspeções a vencer (GET /inspections) e peças com data próxima, e repete a verificação por km (veículos parados).
   Porquê assim: resposta à pergunta do professor na apresentação da fase 2 (como é que o microserviço tem os intervalos mais recentes). Rejeitados: GETs a cada leitura (carga) e cache no microserviço (invalidação e alertas abertos em cache).

6. Fechar alertas: não há endpoint para fechar alertas. Fecham-se ao registar o que lhes corresponde:
   - peça → POST /vehicles/{id}/maintenances (a SAPI atualiza a peça e fecha o alerta na mesma transação);
   - IUC/seguro → POST /vehicles/{id}/expenses (na SAPI: PATCH do prazo, cria o seguinte, fecha o alerta);
   - inspeção → POST /vehicles/{id}/inspections (se aprovada, cria o prazo seguinte e fecha o alerta).
