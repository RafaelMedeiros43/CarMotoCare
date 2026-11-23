-- Corre uma única vez, quando o volume do PostgreSQL é criado (docker-entrypoint-initdb.d).
-- Bases só para os testes automáticos (php artisan test). Uma por SAPI, para as duas suites poderem correr
-- ao mesmo tempo sem apagarem as tabelas uma da outra. A base de desenvolvimento (POSTGRES_DB) não é tocada.
-- Num volume que já exista, criar à mão: ver "Testes" no README da raiz.
CREATE DATABASE carmotocare_test_users;
CREATE DATABASE carmotocare_test_vehicles;
