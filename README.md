# پلتفرم ایجنت‌های Neuron روی لاراول ۱۲

پلتفرم وب فارسی برای ساخت و مدیریت ایجنت‌های هوشمند با کتابخانه **[Neuron AI](https://docs.neuron-ai.dev/)**.

## امکانات

- ✅ مدیریت ایجنت‌ها از وب (نام، پرووایدر، مدل، API Key، System Prompt، Temperature، Max Tokens،...)
- ✅ پرووایدرها: **OpenCode Go (ارزان/رایگان)**، OpenAI، OpenAI Responses، Anthropic، Gemini، Ollama، Mistral، DeepSeek، Grok، ZAI، Cohere، OpenAI-Like / Azure
- ✅ ابزارها (Tools) قابل انتخاب برای هر ایجنت: ماشین‌حساب، تقویم، فایل‌سیستم، WebFetch، خواندن دیتابیس (فقط SELECT)
- ✅ محیط‌های RAG: ساخت چند محیط دانش، آپلود فایل (txt/md/csv/json) یا متن دستی، چانکینگ + امبدینگ خودکار
- ✅ اتصال هر ایجنت به چند محیط RAG (بازیابی با FallbackRetrieval: اگر امبدینگ fail شود جستجوی کلیدواژه‌ای)
- ✅ چت زنده (SSE Streaming) + آپلود فایل داخل چت (متن فایل به پرامپت اضافه می‌شود)
- ✅ API ساده برای نرم‌افزارهای دیگر (توکن Bearer)

## نصب

```bash
composer install
cp .env.example .env   # یا همین .env موجود
php artisan key:generate
php artisan migrate
php artisan serve
```

کلید حداقل یک پرووایدر را در `.env` بگذارید:

```
OPENAI_API_KEY=sk-...
# یا
OLLAMA_URL=http://localhost:11434/api
# یا OpenCode Go (ارزان/رایگان — https://opencode.ai/v2/docs/console/go/)
OPENCODE_GO_API_KEY=...
```

## OpenCode Go

پرووایدر `opencode_go` در فرم ایجنت انتخاب می‌شود. مدل پیش‌فرض: `longcat-2.5-preview-free` (رایگان).
مدل‌های ارزان دیگر: `mimo-v2.6-flash`، `muse-spark-1.3-contributor`، `glm-5.3-flash`، `deepseek-v4.1-flash`، `qwen3.8-flash`.

تفاوت‌های پیاده‌سازی (`app/Neuron/Providers/OpenCodeGo.php`):
- Base URL برابر `https://opencode.ai/inference/go/openai/v1` است (سازگار با OpenAI chat/completions)
- هدرهای اضافی طبق مستندات ارسال می‌شود: `User-Agent: neuron-laravel-agent/1.0` و `x-opencode-session: <thread_id>` (شناسه پایدار هر گفتگو برای routing و prompt caching)
- تحمل استریم‌های بدون `finish_reason`: اگر متن دریافت شده باشد، پاسخ کامل برگردانده می‌شود (رفع خطای `The stream ended before the answer was complete.`)

> بدون کلید هم RAG با جستجوی کلیدواژه‌ای کار می‌کند، ولی چت با LLM نیاز به کلید/مدل در دسترس دارد.

## ساختار

- `app/Models`: `Agent`, `RagEnvironment`, `RagDocument`, `RagChunk`, `ChatSession`, `ChatMessage`, `ApiToken`
- `app/Neuron`: `ProviderFactory`, `EmbeddingFactory`, `DatabaseVectorStore`, `FallbackRetrieval`, `DynamicAgent`, `DynamicRag`, `AgentFactory`, `ToolRegistry`, `RagService`, `Tools/WebFetchTool`, `Tools/DatabaseReadTool`
- `app/Http/Controllers`: `Dashboard`, `Agent`, `RagEnvironment`, `Chat` (web + SSE + upload), `ApiToken`, `Api/AgentApi`
- `resources/views`: `dashboard`, `agents/*`, `rags/*`, `chats/*` (چت زنده با EventSource), `tokens/*`, `api-docs`

## API

توکن را از بخش «توکن‌های API» بسازید.

```bash
# لیست ایجنت‌ها
curl -H "Authorization: Bearer TOKEN" http://localhost:8000/api/v1/agents

# چت
curl -X POST http://localhost:8000/api/v1/agents/general-assistant/chat \
  -H "Authorization: Bearer TOKEN" -H "Content-Type: application/json" \
  -d '{"message":"سلام"}'
# => {"thread_id":"...","answer":"..."}

# ادامه گفتگو
curl -X POST .../chat -d '{"message":"ادامه","thread_id":"th_..."}'

# استریم
curl -X POST .../chat -d '{"message":"سلام","stream":true}'  # SSE
```

## نکات

- حافظه گفتگو: `storage/app/neuron-chats/{thread_id}.json` (FileMessageStore) + جدول `chat_messages` برای نمایش وب.
- وکتوراستور: جدول `rag_chunks` (ستون `embedding` اختیاری؛ بدون امبدینگ، retrieval کلیدواژه‌ای است).
- برای production حتماً احراز هویت وب + محدودسازی ابزار `database_read` را بازبینی کنید.
