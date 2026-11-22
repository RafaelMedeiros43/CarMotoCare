# Documentação do CarMotoCare

Desenho da fase 2, usado como referência na implementação (fase 3).
Se algo no código tiver de mudar o desenho, atualiza também o ficheiro correspondente aqui.

| Pasta / ficheiro | O que tem |
|---|---|
| `api/eapi-web.yaml` | Contrato OpenAPI da EAPI Web (a única API exposta) |
| `api/sapi-vehicles.yaml` | Contrato da SAPI Veículos (v8, com `nextAlertKm`) |
| `api/sapi-users.yaml` | Contrato da SAPI Utilizadores |
| `api/sapi-workshops.yaml` | Contrato da SAPI Oficinas |
| `api/endpoints.txt` | Lista dos 68 endpoints por API |
| `architecture/architecture.md` | Diagrama de arquitetura (v17) |
| `architecture/sequence-alerts.md` | SSD3: criação, consulta e conclusão de alertas |
| `architecture/app-flow.md` | Funcionamento da app, passo a passo |
| `data/data-model.md` | Modelo de dados: decisões, DDL PostgreSQL e plano de peças por defeito |
| `data/er-diagram.md` | Diagrama ER |
| `data/domain-rules.md` | Regras de negócio do domínio (v6.4) |
| `mqtt/mqtt-contract.md` | Tópicos MQTT, payloads, validação e simulador OBD |
| `repository-structure.md` | Estrutura de pastas e convenções do código |

Os diagramas estão em Mermaid dentro dos `.md`: o GitHub mostra-os desenhados.
Os `.yaml` abrem-se no Swagger Editor (https://editor.swagger.io).
