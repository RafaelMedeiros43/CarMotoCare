# Infraestrutura

Configuração que não é código de serviço. Preenche-se quando se fizer o `docker-compose.yml`.

- `nginx/`: load balancer / reverse proxy à frente da EAPI Web (REST e WebSocket).
- `rabbitmq/`: plugin MQTT ativo, utilizadores internos e permissões por tópico.
- `postgres/`: `init.sql` que cria os schemas `users` e `vehicles` (desenvolvimento local).
