# SAPI Utilizadores

System API interna: contas e verificação de credenciais (registo e login). Usada só pela EAPI Web.

**Contrato:** [`docs/api/sapi-users.yaml`](../../docs/api/sapi-users.yaml) (5 endpoints)

**No relatório:** SAPI Utilizadores

Base de dados: tabela `users` no schema `public`, partilhado com sapi-vehicles ([modelo](../../docs/data/data-model.md)). Migrações registadas em `migrations_users`; esta SAPI tem de migrar antes de sapi-vehicles.
