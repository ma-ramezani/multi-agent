# Neuron Agents Platform — Laravel 12 + Neuron AI 4

> [فارسی](./README.md) | English

Persian-first, **offline-capable** web platform for building and managing AI agents. Built on [Neuron AI](https://docs.neuron-ai.dev/), local Ollama embeddings, Persian OCR and speech-to-text — boots completely with **a single `docker compose up`**.

---

## Table of contents

- [Features](#features)
- [Architecture](#architecture)
- [Quick start — Docker Compose](#quick-start--docker-compose)
- [Manual install (without Docker)](#manual-install-without-docker)
- [Configuration (.env)](#configuration-env)
- [Usage](#usage)
- [API](#api)
- [Security](#security)
- [Services & volumes](#services--volumes)
- [Troubleshooting](#troubleshooting)
- [Project structure](#project-structure)
- [Tech stack](#tech-stack)

---

## Features

### Agents — everything from the web UI

- Name, slug, description, **provider + model**, API key (blank = read from env), Base URL,
  System Prompt, Temperature, Max Tokens, Context Window, tool-call cap.
- Providers: `opencode_go` · `openai` · `openai_responses` · `anthropic` · `gemini` · `ollama` · `mistral` · `deepseek` · `grok` · `zai` · `cohere` · `openai_like` · `azure`.
- **OpenCode Go** as a cheap/free provider (env proxy + `x-opencode-session` headers + tolerant handling of streams without `finish_reason`).

### Tools — per-agent selection

| Key | Label | Notes |
|-----|-------|-------|
| `calculator` | Calculator | Exact math (requires `ext-bcmath`) |
| `calendar` | Calendar | Dates, times, time zones |
| `filesystem` | Sandboxed filesystem | Read-only inside `storage/app/agent-files` — no `bash`/`write`/`delete` |
| `web_fetch` | Web fetch | Public URL text fetch (SSRF-protected) |
| `database_read` | Database read | `SELECT`/`SHOW`/`DESCRIBE` only; sensitive tables/columns blocked |

### Knowledge — RAG environments

- Multiple independent environments, each with its own embedding provider, model, chunk size, overlap and TopK.
- Embedding providers: `openai` · `ollama` · `gemini` · `openai_like`.
- Indexable formats: `txt`/`md`/`csv`/`json`/`html` · `pdf` (text or scanned) · `docx` · `xlsx`/`xls` · `pptx` · images (`png`/`jpg`/`webp`… via Persian+English OCR) · audio (`mp3`/`wav`/`ogg`/`m4a`/`webm`/`flac` via transcription).
- Scanned PDFs are automatically page-by-page OCR'd (`poppler` + `tesseract fas`) and large files go to the **background queue** (page auto-refreshes).
- Vector store: `rag_chunks` table with keyword-search fallback when embeddings are unavailable.

### Live chat

- **SSE** streaming (EventSource).
- Multi-file attachments (drag & drop on the composer), read-status preview, file text injected into the prompt.
- **Voice**: microphone button (Web Speech API, `fa-IR`) + audio files with player and automatic transcription (local `large-v3-turbo` service).
- Jalali dates, self-hosted Vazirmatn font, standard SVG icons.

### Security center — `/security`

14 settings from the web without touching code: injection guard, extra admin rules, output redaction, tool kill-switches, DB deny-list, SSRF strict mode, upload caps/allowlist, chat & API rate limits, audit retention — plus a full event log.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│  browser  ──►  app (Laravel 12)  ──►  Neuron AI 4  ──►  LLM     │
│                  │  ├─ DynamicAgent / DynamicRag                │
│                  │  ├─ ProviderFactory / EmbeddingFactory       │
│                  │  ├─ ToolRegistry (ScopedFileTool, …)         │
│                  │  └─ DocumentReader + VectorStore + Fallback  │
│                  │                    │                          │
│                  │              ┌─────┴─────┐                    │
│                  │              │  ollama   │  nomic-embed-text  │
│                  │              │  (11434)  │  local embeddings  │
│                  │              └───────────┘                    │
│                  │              ┌───────────┐                    │
│                  │              │   stt     │  faster-whisper    │
│                  │              │  (11437)  │  Persian STT       │
│                  │              └───────────┘                    │
│                  │  queue: IngestRagDocument (database)          │
│                  │  memory: FileMessageStore + chat_messages     │
└─────────────────────────────────────────────────────────────────┘
```

---

## Quick start — Docker Compose

> Only requirement: **Docker + Docker Compose v2**. One compose brings everything up (web + queue worker + Ollama + STT).

```bash
# 1) Get the code
git clone <repo-url> && cd <repo>
cp .env.example .env          # fill in keys there (optional — RAG works without any key)

# 2) Bring the whole stack up (first build downloads the STT model ~1.6 GB)
docker compose up --build -d

# 3) Watch the boot log (migrations run automatically)
docker compose logs -f app
# when you see "[entrypoint] starting supervisord (web + queue worker)" it's ready

# 4) Open the app
# http://localhost:8000
```

### Main compose variables (in `.env` or shell env)

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_PORT` | `8000` | Host port for the web UI |
| `APP_URL` | `http://localhost:8000` | Public URL of the app |
| `OLLAMA_PORT` | `11434` | Host port for Ollama (optional) |
| `OLLAMA_VERSION` | `latest` | Ollama image tag |
| `OLLAMA_EMBED_MODEL` | `nomic-embed-text` | Embedding model inside the ollama container |
| `STT_MODEL` | `large-v3-turbo` | faster-whisper model inside the stt container |
| `OPENCODE_GO_API_KEY` | — | Cheap/free provider key |
| `OPENAI_API_KEY` etc. | — | Other providers (optional) |

```bash
# Examples: different port or model
APP_PORT=8080 STT_MODEL=small docker compose up --build -d

# Stop
docker compose down

# Stop + wipe data (database, files, model caches)
docker compose down -v
```

---

## Manual install (without Docker)

```bash
php -v          # >= 8.2
composer --version
# System deps for OCR/PDF:
# Ubuntu/Debian: sudo apt-get install tesseract-ocr tesseract-ocr-fas tesseract-ocr-eng poppler-utils

composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force

# (optional) Ollama — only for local embeddings
# https://ollama.com — then: ollama pull nomic-embed-text && ollama serve

# (optional) STT — only for audio file transcription
# pip install faster-whisper && python docker/stt/server.py  # or the project's systemd service

php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:work database --sleep=3 --tries=1 --timeout=3600  # second terminal
```

Put at least one provider key in `.env` (without it, RAG still works via keyword search):

```ini
OPENCODE_GO_API_KEY=...
# or
OPENAI_API_KEY=sk-...
# or local
OLLAMA_URL=http://localhost:11434/api
```

---

## Configuration (.env)

`.env.example` is commented and works for both modes (compose and manual).

- In compose, `OLLAMA_URL` inside the app container is automatically `http://ollama:11434/api` (overrides `.env`).
- `STT_SERVER_URL` is automatically `http://stt:11437` in compose, `http://127.0.0.1:11437` locally.
- Other keys (`OPENAI_API_KEY` etc.) are ignored when blank.

---

## Usage

1. **Create an agent** — `/agents` → choose provider/model, tune tone & temperature, tick tools, attach one or more RAG environments.
2. **Build knowledge** — `/rags` → new environment (pick embedding provider and chunk size) → upload files or paste text. Scanned PDFs are auto-OCR'd; large files go to the background queue.
3. **Chat** — `/chats` → new conversation with an agent → text, voice via microphone, or multi-file attachments (Word/Excel/PowerPoint/PDF/image/audio).
4. **Manage documents** — in the environment page, edit extracted text or replace the file, re-index, download the original or delete it.
5. **Give out API access** — create a token at `/tokens` (shown only once), then call ` /api/v1/...` (docs at `/api-docs`).
6. **Tune security** — use `/security` to view and change guards, limits and the audit trail.

---

## API

Create a token at `/tokens`. It can optionally be scoped to a few agents.

```bash
# List agents
curl -H "Authorization: Bearer TOKEN" http://localhost:8000/api/v1/agents

# Chat
curl -X POST http://localhost:8000/api/v1/agents/general-assistant/chat \
  -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" \
  -d '{"message":"Hello"}'
# => {"thread_id":"...","answer":"..."}

# Continue the same thread
curl -X POST .../chat -d '{"message":"continue","thread_id":"th_..."}'

# Streaming (SSE)
curl -X POST .../chat -d '{"message":"Hello","stream":true}'
# events: data: {"text":"..."} ... data: {"done":true}

# History
curl -H "Authorization: Bearer TOKEN" \
  "http://localhost:8000/api/v1/agents/general-assistant/history?thread_id=th_..."
```

---

## Security

| Layer | Description |
|-------|-------------|
| Injection policy | Appended to every agent's system prompt; not removable from the UI. |
| Sandboxed filesystem | Raw Neuron toolkit (`bash`/`write`/`delete`) removed and replaced with a read-only `ScopedFileTool` jailed to `storage/app/agent-files`. |
| Database | `SELECT`/`SHOW`/`DESCRIBE` only; deny-list for `users`, `api_tokens`, `jobs` … plus sensitive columns; configurable from `/security`. |
| SSRF | `WebFetchTool` blocks localhost, private networks and cloud metadata (including redirects). |
| Output filter | `SafetyFilter::redact` scrubs `sk-`/`ak_`/`Bearer` patterns and env key values before persisting. |
| Injection detection | `PromptGuard` scans inputs (Persian + English) and logs suspicious ones (non-blocking — the model's policy does the refusing). |
| Rate limits | `throttle:chat` and `throttle:api` with tunable caps in `/security`. |
| Audit trail | All blocks and warnings land in `security_events` with IP and timestamp. |

All of this is visible and tunable on the **Security Center** page.

---

## Services & volumes

```
docker compose ps
  neuron-app     →  http://localhost:8000  (supervisord: web + queue:work)
  neuron-ollama  →  http://localhost:11434 (embedding model: nomic-embed-text)
  neuron-stt     →  http://localhost:11437 (compose-internal, expose)
```

| Volume | Content | Wipe |
|--------|---------|------|
| `app-sqlite` | `database/database.sqlite` | `docker compose down -v` |
| `app-storage` | uploads, chunks, chats | `docker compose down -v` |
| `ollama-data` | Ollama models | `docker compose down -v` |
| `stt-cache` | faster-whisper model cache | `docker compose down -v` |

Health checks: `app` on `/up`, `ollama` on `/api/tags`, `stt` on `/health`. The app waits for ollama and stt to be healthy.

---

## Troubleshooting

| Symptom | Cause / fix |
|---------|-------------|
| `Connection refused` to `opencode.ai` | Host needs a proxy — set `HTTP_PROXY`/`HTTPS_PROXY` in the compose environment; the app forwards it to Guzzle. |
| Chunks stay at `0` with status `processing` | Large file is in the queue; `docker compose logs -f app` shows the worker log. Tesseract takes seconds per page. |
| Agent edit returns 500 | Stale view cache; `php artisan view:clear` or restart the container. |
| Persian font not loading | Fonts are in `public/fonts`; make sure `.dockerignore` doesn't exclude them. |
| STT not responding | `curl http://localhost:11437/health` should return `{"ok":true}`. On first run, ~1.6 GB is downloaded. |

---

## Project structure

```
app/Models            Agent, RagEnvironment, RagDocument, RagChunk, ChatSession, ChatMessage, ApiToken, SecuritySetting, SecurityEvent
app/Neuron            ProviderFactory, EmbeddingFactory, BatchedOllamaEmbeddings, DatabaseVectorStore, FallbackRetrieval,
                      DynamicAgent, DynamicRag, AgentFactory, ToolRegistry, RagService, DocumentReader, SpeechToText,
                      SafetyFilter, SecurityPolicy, PromptGuard, Tools/{Scoped,DatabaseRead,WebFetch}
app/Http/Controllers  Dashboard, Agent, RagEnvironment, Chat, File, ApiToken, Security, Api/AgentApi
resources/views       dashboard, agents/*, rags/*, chats/*, tokens/*, security/*, api-docs, layout
docker/               entrypoint.sh, supervisord.conf, ollama-entrypoint.sh, stt/{Dockerfile,server.py}
```

---

## Tech stack

- **Backend**: PHP 8.3 · Laravel 12 · Neuron AI 4 · SQLite (database queue/session/cache)
- **AI**: OpenAI / Anthropic / Gemini / Ollama / OpenCode Go · Ollama embeddings (`nomic-embed-text`) · faster-whisper `large-v3-turbo` (int8, CPU)
- **OCR**: Tesseract `fas`+`eng` · Poppler (`pdftoppm`/`pdfinfo`)
- **Frontend**: Blade · Tailwind CDN · self-hosted Vazirmatn · SVG icons · SSE (EventSource) · Web Speech API

---

## License

MIT
