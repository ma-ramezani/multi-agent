# پلتفرم ایجنت‌های هوشمند — Laravel 12 + Neuron AI 4

> [English](./README.en.md) | فارسی

پلتفرم وب **فارسی** و **آفلاین‌محور** برای ساخت و مدیریت ایجنت‌های هوشمند. با کتابخانه‌ی [Neuron AI](https://docs.neuron-ai.dev/)، امبدینگ لوکال Ollama، OCR فارسی و رونویسی صوت ساخته شده و با **یک دستور Docker Compose** کامل بالا می‌آید.

---

## فهرست

- [ویژگی‌ها](#ویژگیها)
- [معماری](#معماری)
- [شروع سریع — Docker Compose](#شروع-سریع--docker-compose)
- [نصب دستی (بدون داکر)](#نصب-دستی-بدون-داکر)
- [پیکربندی (.env)](#پیکربندی-env)
- [راهنمای استفاده](#راهنمای-استفاده)
- [API](#api)
- [امنیت](#امنیت)
- [سرویس‌ها و دیسک‌ها](#سرویسها-و-دیسکها)
- [عیب‌یابی](#عیبیابی)
- [ساختار پروژه](#ساختار-پروژه)
- [پشته‌ی فنی](#پشتهی-فنی)

---

## ویژگی‌ها

### ایجنت‌ها — همه‌چیز از وب

- نام، نامک (slug)، توضیح، **پرووایدر + مدل**، کلید API (خالی = خواندن از env)، Base URL،
  System Prompt، Temperature، Max Tokens، Context Window، سقف اجرای ابزار.
- لیست پرووایدرها: `opencode_go` · `openai` · `openai_responses` · `anthropic` · `gemini` · `ollama` · `mistral` · `deepseek` · `grok` · `zai` · `cohere` · `openai_like` · `azure`.
- **OpenCode Go** به‌عنوان پرووایدر ارزان/رایگان (پروکسی env + هدرهای `x-opencode-session` + تحمل استریم‌های بدون `finish_reason`).

### ابزارها — انتخابی برای هر ایجنت

| کلید | عنوان در فرم | توضیح |
|------|-------------|-------|
| `calculator` | ماشین‌حساب | محاسبات دقیق (نیاز به `ext-bcmath`) |
| `calendar` | تقویم و تاریخ | تاریخ/زمان و تایم‌زون |
| `filesystem` | فایل‌سیستم سندباکس | فقط خواندن داخل `storage/app/agent-files` — بدون `bash`/`write`/`delete` |
| `web_fetch` | دریافت صفحه وب | خواندن متن یک URL عمومی (محافظت SSRF + لاگ ریدایرکت) |
| `database_read` | خواندن دیتابیس | فقط `SELECT`/`SHOW`/`DESCRIBE`؛ جداول و ستون‌های حساس مسدود |

### دانش — محیط‌های RAG

- چند محیط مستقل، هرکدام با سرویس امبدینگ، مدل، اندازه‌ی چانک، هم‌پوشانی و TopK خودش.
- سرویس‌های امبدینگ: `openai` · `ollama` · `gemini` · `openai_like`.
- فرمت‌های قابل ایندکس: `txt`/`md`/`csv`/`json`/`html` · `pdf` (متنی یا اسکن‌شده) · `docx` · `xlsx`/`xls` · `pptx` · عکس‌ها (`png`/`jpg`/`webp`… با OCR فارسی+انگلیسی) · فایل‌های صوتی (`mp3`/`wav`/`ogg`/`m4a`/`webm`/`flac` با رونویسی).
- PDF اسکن‌شده به‌صورت خودکار صفحه‌به‌صفحه OCR می‌شود (`poppler` + `tesseract fas`) و در صورت حجیم بودن در **صف پس‌زمینه** پردازش می‌شود (صفحه خودکار رفرش می‌شود).
- وکتوراستور: جدول `rag_chunks` با fallback جستجوی کلیدواژه‌ای اگر امبدینگ در دسترس نباشد.

### چت زنده

- استریم **SSE** (EventSource) — پاسخ تکه‌تکه می‌آید.
- پیوست چندفایل همزمان (درگ‌دراپ روی کادر چت)، پیش‌نمایش وضعیت خوانش، و تزریق متن فایل‌ها به پرامپت.
- **صدا**: دکمه‌ی میکروفون (Web Speech API، `fa-IR`) + فایل صوتی با پخش‌کننده و رونویسی خودکار (سرویس `large-v3-turbo`).
- تاریخ شمسی، فونت وزیرمتن لوکال، آیکن‌های SVG استاندارد.

### مرکز امنیت — `/security`

۱۴ تنظیم از وب بدون دست‌کاری کد: سپر ضداینجکشن، قوانین اضافه‌ی مدیر، حذف خودکار اسرار، kill-switch ابزارها، لیست سیاه جداول دیتابیس، ضد SSRF، سقف حجم/پسوند آپلود، نرخ‌محدودیت چت و API، نگهداری لاگ، به‌همراه ممیزی کامل رویدادها.

---

## معماری

```
┌─────────────────────────────────────────────────────────────────┐
│  مرورگر  ──►  app (Laravel 12)  ──►  Neuron AI 4  ──►  LLM       │
│                  │  ├─ DynamicAgent / DynamicRag                │
│                  │  ├─ ProviderFactory / EmbeddingFactory       │
│                  │  ├─ ToolRegistry (ScopedFileTool, …)         │
│                  │  └─ DocumentReader + VectorStore + Fallback  │
│                  │                    │                          │
│                  │              ┌─────┴─────┐                    │
│                  │              │  ollama   │  nomic-embed-text  │
│                  │              │  (11434)  │  امبدینگ لوکال     │
│                  │              └───────────┘                    │
│                  │              ┌───────────┐                    │
│                  │              │   stt     │  faster-whisper    │
│                  │              │  (11437)  │  رونویسی فارسی     │
│                  │              └───────────┘                    │
│                  │  صف: IngestRagDocument (database)             │
│                  │  حافظه: FileMessageStore + chat_messages      │
└─────────────────────────────────────────────────────────────────┘
```

---

## شروع سریع — Docker Compose

> تنها پیش‌نیاز: **Docker + Docker Compose v2**. تک‌کامپوز همه‌چیز (وب + ورکر صف + Ollama + STT) را بالا می‌آورد.

```bash
# 1) دریافت کد
git clone <repo-url> && cd <repo>
cp .env.example .env          # کلیدها را در همین فایل پر کنید (اختیاری — بدون کلید هم RAG کار می‌کند)

# 2) اجرای کل استک (اولین بار بیلد شامل دانلود مدل STT ~1.6GB است)
docker compose up --build -d

# 3) لاگ بوت (مهاجرت‌ها به‌صورت خودکار اجرا می‌شوند)
docker compose logs -f app
# وقتی دیدید: "[entrypoint] starting supervisord (web + queue worker)" آماده است

# 4) باز کردن اپ
# http://localhost:8000
```

### متغیرهای اصلی compose (در `.env` یا محیط شل)

| متغیر | پیش‌فرض | توضیح |
|-------|---------|-------|
| `APP_PORT` | `8000` | پورت هاست برای وب |
| `APP_URL` | `http://localhost:8000` | آدرس عمومی اپ |
| `OLLAMA_PORT` | `11434` | پورت هاست برای Ollama (اختیاری) |
| `OLLAMA_VERSION` | `latest` | تگ ایمیج Ollama |
| `OLLAMA_EMBED_MODEL` | `nomic-embed-text` | مدل امبدینگ داخل کانتینر ollama |
| `STT_MODEL` | `large-v3-turbo` | مدل faster-whisper داخل کانتینر stt |
| `OPENCODE_GO_API_KEY` | — | کلید پرووایدر ارزان/رایگان |
| `OPENAI_API_KEY` و ... | — | سایر پرووایدرها (اختیاری) |

```bash
# نمونه: تغییر پورت یا مدل
APP_PORT=8080 STT_MODEL=small docker compose up --build -d

# توقف
docker compose down

# توقف + پاک‌سازی دیتا (دیتابیس، فایل‌ها، کش مدل‌ها)
docker compose down -v
```

---

## نصب دستی (بدون داکر)

```bash
php -v          # >= 8.2
composer --version
# پیش‌نیازهای سیستمی برای OCR/PDF:
# Ubuntu/Debian: sudo apt-get install tesseract-ocr tesseract-ocr-fas tesseract-ocr-eng poppler-utils

composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force

# (اختیاری) Ollama — فقط اگر امبدینگ لوکال می‌خواهید
# https://ollama.com — سپس: ollama pull nomic-embed-text && ollama serve

# (اختیاری) STT — فقط اگر رونویسی فایل صوتی می‌خواهید
# pip install faster-whisper && python docker/stt/server.py  # یا سرویس systemd پروژه

php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:work database --sleep=3 --tries=1 --timeout=3600  # ترمینال دوم
```

کلید حداقل یک پرووایدر را در `.env` بگذارید (بدون آن، RAG فقط با جستجوی کلیدواژه‌ای کار می‌کند):

```ini
OPENCODE_GO_API_KEY=...
# یا
OPENAI_API_KEY=sk-...
# یا لوکال
OLLAMA_URL=http://localhost:11434/api
```

---

## پیکربندی (.env)

فایل `.env.example` کامنت‌دار است و برای هر دو حالت (compose و دستی) کار می‌کند. نکات مهم:

- در compose، `OLLAMA_URL` داخل کانتینر app به‌صورت خودکار `http://ollama:11434/api` می‌شود؛ مقدار `.env` را بازنویسی می‌کند.
- `STT_SERVER_URL` در compose خودکار `http://stt:11437` می‌شود؛ روی هاست لوکال `http://127.0.0.1:11437` است.
- بقیه‌ی کلیدها (`OPENAI_API_KEY` و ...) اگر خالی باشند نادیده گرفته می‌شوند.

---

## راهنمای استفاده

1. **ایجنت بسازید** — `/agents` → انتخاب پرووایدر/مدل، تنظیم لحن و دما، تیک ابزارها، اتصال به یک یا چند محیط RAG.
2. **دانش بسازید** — `/rags` → محیط جدید (سرویس امبدینگ و اندازه‌ی چانک را انتخاب کنید) → آپلود فایل یا چسباندن متن. PDF اسکن‌شده خودکار OCR می‌شود؛ فایل‌های حجیم در صف پس‌زمینه می‌روند.
3. **چت کنید** — `/chats` → گفتگوی جدید با یک ایجنت → پیام متنی، ویس با میکروفون، یا پیوست چندفایل (ورد/اکسل/پاورپوینت/PDF/عکس/صوت).
4. **مدیریت اسناد** — در صفحه‌ی محیط، هر سند را ویرایش کنید (متن استخراج‌شده را اصلاح کنید یا فایل را جایگزین کنید)، دوباره ایندکس کنید، دانلود کنید یا حذف کنید.
5. **API بدهید** — `/tokens` توکن بسازید (کل توکن فقط یک‌بار نمایش داده می‌شود)، سپس به ` /api/v1/...` وصل شوید (مستندات در `/api-docs`).
6. **امنیت را تنظیم کنید** — `/security` سپرها، محدودیت‌ها و لاگ‌ها را ببینید و تغییر دهید.

---

## API

توکن را در `/tokens` بسازید. اختیاری می‌توان آن را به چند ایجنت محدود کرد.

```bash
# لیست ایجنت‌ها
curl -H "Authorization: Bearer TOKEN" http://localhost:8000/api/v1/agents

# چت
curl -X POST http://localhost:8000/api/v1/agents/general-assistant/chat \
  -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" \
  -d '{"message":"سلام"}'
# => {"thread_id":"...","answer":"..."}

# ادامه‌ی همان گفتگو
curl -X POST .../chat -d '{"message":"ادامه","thread_id":"th_..."}'

# استریم (SSE)
curl -X POST .../chat -d '{"message":"سلام","stream":true}'
# events: data: {"text":"..."} ... data: {"done":true}

# تاریخچه
curl -H "Authorization: Bearer TOKEN" \
  "http://localhost:8000/api/v1/agents/general-assistant/history?thread_id=th_..."
```

---

## امنیت

| لایه | توضیح |
|-----|-------|
| سیاست ضداینجکشن | بلوک متنی به انتهای system prompt همه‌ی ایجنت‌ها الحاق می‌شود؛ از فرم قابل حذف نیست. |
| فایل‌سیستم سندباکس | تولکیت خام Neuron (شامل `bash`/`write`/`delete`) حذف و با `ScopedFileTool` فقط-خواندنی محدود به `storage/app/agent-files` جایگزین شده. |
| دیتابیس | فقط `SELECT`/`SHOW`/`DESCRIBE`؛ لیست سیاه جداول (`users`, `api_tokens`, `jobs` …) + ستون‌های حساس + ممنوعیت چنددستوری؛ قابل تنظیم از `/security`. |
| SSRF | `WebFetchTool` آدرس‌های داخلی، شبکه خصوصی و متادیتای ابری را (حتی در ریدایرکت‌ها) می‌بندد. |
| فیلتر خروجی | `SafetyFilter::redact` قبل از ذخیره، الگوهای `sk-`/`ak_`/`Bearer` و مقادیر کلیدهای env را پاک می‌کند. |
| تشخیص اینجکشن | `PromptGuard` ورودی‌ها را فارسی/انگلیسی اسکن و در صورت مشکوک، در `security_events` لاگ می‌کند (بدون بلاکِ UX). |
| نرخ‌محدودیت | `throttle:chat` و `throttle:api` با سقف قابل تنظیم در `/security`. |
| ممیزی | همه‌ی بلاک‌ها و هشدارها در جدول `security_events` با IP و زمان ثبت می‌شوند. |

همه‌ی این موارد در صفحه‌ی **مرکز امنیت** قابل مشاهده و تنظیم هستند.

---

## سرویس‌ها و دیسک‌ها

```
docker compose ps
  neuron-app     →  http://localhost:8000  (supervisord: web + queue:work)
  neuron-ollama  →  http://localhost:11434 (مدل امبدینگ: nomic-embed-text)
  neuron-stt     →  http://localhost:11437 (فقط داخل شبکه‌ی compose، expose)
```

| ولوم | محتوا | پاک‌سازی |
|------|-------|----------|
| `app-sqlite` | `database/database.sqlite` | `docker compose down -v` |
| `app-storage` | آپلودها، چانک‌ها، چت‌ها | `docker compose down -v` |
| `ollama-data` | مدل‌های Ollama | `docker compose down -v` |
| `stt-cache` | کش مدل faster-whisper | `docker compose down -v` |

هلث‌چک‌ها: `app` روی `/up`، `ollama` روی `/api/tags`، `stt` روی `/health`. اپ منتظر سالم‌شدن ollama و stt می‌ماند.

---

## عیب‌یابی

| نشانه | علت/راه‌حل |
|------|-----------|
| `Connection refused` به `opencode.ai` | شبکه‌ی هاست پروکسی می‌خواهد — `HTTP_PROXY`/`HTTPS_PROXY` را در محیط compose ست کنید؛ کانتینر app آن را به Guzzle پاس می‌دهد. |
| چانک‌ها `0` و وضعیت `processing` گیر کرده | فایل حجیم در صف است؛ `docker compose logs -f app` لاگ ورکر را نشان می‌دهد. تسرکت هر صفحه چند ثانیه طول می‌کشد. |
| ادیت ایجنت خطای ۵۰۰ | کش ویو قدیمی است؛ `php artisan view:clear` یا ری‌استارت کانتینر. |
| فونت فارسی لود نمی‌شود | فونت‌ها در `public/fonts` هستند؛ مطمئن شوید `.dockerignore` آن‌ها را حذف نکرده. |
| STT جواب نمی‌دهد | `curl http://localhost:11437/health` — باید `{"ok":true}` بدهد. مدل اولین بار ~۱.۶GB دانلود می‌کند. |

---

## ساختار پروژه

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

## پشته‌ی فنی

- **Backend**: PHP 8.3 · Laravel 12 · Neuron AI 4 · SQLite (database queue/session/cache)
- **AI**: OpenAI / Anthropic / Gemini / Ollama / OpenCode Go · Ollama embeddings (`nomic-embed-text`) · faster-whisper `large-v3-turbo` (int8, CPU)
- **OCR**: Tesseract `fas`+`eng` · Poppler (`pdftoppm`/`pdfinfo`)
- **Frontend**: Blade · Tailwind CDN · Vazirmatn لوکال · SVG آیکن‌ها · SSE (EventSource) · Web Speech API

---

## مجوز

MIT
