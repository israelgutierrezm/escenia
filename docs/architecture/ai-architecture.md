# AI Architecture

## Provider abstraction

Crear contratos para:

- generation;
- structured generation;
- embeddings;
- transcription;
- moderation;
- translation cuando corresponda.

## Prompt Registry

No dispersar prompts en controllers/services.

Guardar:

- prompt_key;
- prompt_version;
- provider;
- model;
- configuration;
- input hash;
- output metadata;
- cost.

## Funciones previstas

- AI Event Architect
- Producer Copilot
- Smart Q&A
- Content Factory
- Semantic Replay
- Event Intelligence
- Viewer AI Assistant

## Seguridad

RAG debe respetar permisos y visibility scope.

Backstage, private chat y datos restringidos no deben entrar automáticamente en contexto.

## Cost control

Antes de ejecutar:

- entitlement;
- tenant budget;
- rate limit;
- usage accounting.
