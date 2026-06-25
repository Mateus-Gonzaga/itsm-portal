# Portal ITSM — FOURLINE Connect

📚 **Documentação técnica em `docs/`** — manter sempre atualizada, editando só o arquivo afetado (não recriar tudo, não duplicar):
- `docs/PROJECT_CONTEXT.md` — objetivo, stack, Docker, como rodar, env, fluxo geral.
- `docs/ARCHITECTURE.md` — arquitetura, padrões, auth/authz, decisões técnicas.
- `docs/API.md` — endpoints (rotas web; API do GLPI = Fase 2).
- `docs/CHANGELOG.md` — histórico de alterações (nunca apagar).
- `docs/TODO.md` — próximas tarefas, melhorias, débitos, bugs.

**Resumo rápido:** Laravel 12 + PostgreSQL via Sail/WSL2. Rodar: `cd ~/itsm-portal && ./vendor/bin/sail up -d` → http://localhost. Contas demo (senha `password`): `cliente@`/`tecnico@`/`gestor@itsm.test`. Integração GLPI trocável por `GLPI_DRIVER` (`fake`|`api`).

> Ao concluir uma funcionalidade relevante, atualizar os arquivos de `docs/` necessários antes de finalizar.
