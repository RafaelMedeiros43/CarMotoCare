# Infraestrutura

Configuração que não é código de serviço. Preenche-se quando se fizer o `docker-compose.yml`.

- `nginx/`: load balancer / reverse proxy à frente da EAPI Web (REST e WebSocket).
- `rabbitmq/`: plugin MQTT ativo, utilizadores internos e permissões por tópico.
- `postgres/`: vazio. A base usa o schema `public` por defeito; as tabelas são criadas pelas migrações de sapi-users e sapi-vehicles.
