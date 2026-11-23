-- Corre uma única vez, quando o volume do PostgreSQL é criado.
-- Uma base de dados, um schema por SAPI (ver docs/data/data-model.md).
CREATE SCHEMA IF NOT EXISTS users;
CREATE SCHEMA IF NOT EXISTS vehicles;
