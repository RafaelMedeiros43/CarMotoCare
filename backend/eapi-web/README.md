# EAPI Web

Experience API: a única API exposta (atrás do Nginx). Autentica com JWT, chama as System APIs e reencaminha os alertas do broker (`app/alert/created`) para o browser por WebSocket (Laravel Reverb).

**Contrato:** [`docs/api/eapi-web.yaml`](../../docs/api/eapi-web.yaml) (29 endpoints)

**No relatório:** EAPI Web

Subscreve: `app/alert/created`. Não tem base de dados própria.
