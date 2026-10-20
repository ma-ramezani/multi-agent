@extends('layout')
@section('title', ($agent->exists ? 'ویرایش ایجنت' : 'ایجنت جدید'))
@section('content')
@php
$modelsByProvider = [
  'opencode_go' => ['longcat-2.5-preview-free','mimo-v2.6-flash','muse-spark-1.3-contributor','glm-5.3-flash','deepseek-v4.1-flash','qwen3.8-flash','kimi-k2.7-code'],
  'openai' => ['gpt-4o-mini','gpt-4o','gpt-5-mini'],
  'openai_responses' => ['gpt-4o-mini','gpt-4o'],
  'anthropic' => ['claude-haiku-4-5','claude-sonnet-4-5','claude-3-5-haiku-latest'],
  'gemini' => ['gemini-2.0-flash','gemini-2.5-flash'],
  'ollama' => ['llama3.1','qwen2.5','mistral'],
  'mistral' => ['mistral-large-latest','mistral-small-latest'],
  'deepseek' => ['deepseek-chat','deepseek-reasoner'],
  'grok' => ['grok-3','grok-3-mini'],
  'openai_like' => [], 'azure' => [], 'cohere' => ['command-a-03-2025'],
];
@endphp
<div class="mb-5">
  <h1 class="text-2xl font-extrabold">{!! svg_icon('edit','w-6 h-6 inline-block -mt-1') !!} {{ $agent->exists ? 'ویرایش ایجنت: ' . $agent->name : '＋ ایجنت جدید' }}</h1>
  <p class="mt-1 text-sm text-slate-500">همه تنظیمات ایجنت از همین‌جا انجام می‌شود و بلافاصله اعمال می‌گردد.</p>
</div>

<form method="POST" action="{{ $agent->exists ? route('agents.update',$agent) : route('agents.store') }}">
@csrf @if($agent->exists)@method('PUT')@endif

{{-- ۱. مشخصات --}}
<div class="card p-5">
  <h2 class="mb-4 flex items-center gap-2 font-bold"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs text-white">۱</span> مشخصات کلی</h2>
  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div><label class="lbl">نام ایجنت *</label><input name="name" value="{{ old('name',$agent->name) }}" class="inp" required placeholder="مثلاً: پشتیبانی فروش"></div>
    <div><label class="lbl">نامک (slug) <span class="font-normal text-slate-400">— یکتا، برای API</span></label><input name="slug" value="{{ old('slug',$agent->slug) }}" class="inp font-mono" dir="ltr" placeholder="auto-generated">
      <p class="hint">خالی بگذارید تا خودکار ساخته شود. در API با همین نام صدا زده می‌شود.</p></div>
    <div class="md:col-span-2"><label class="lbl">توضیح</label><input name="description" value="{{ old('description',$agent->description) }}" class="inp" placeholder="این ایجنت چه کاری انجام می‌دهد؟"></div>
  </div>
</div>

{{-- ۲. مدل --}}
<div class="card mt-4 p-5">
  <h2 class="mb-4 flex items-center gap-2 font-bold"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs text-white">۲</span> موتور زبانی (پرووایدر و مدل)</h2>
  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
      <label class="lbl">پرووایدر *</label>
      <select name="provider" id="providerSel" class="inp">
        @foreach(\App\Models\Agent::providers() as $k=>$v)
          <option value="{{ $k }}" {{ old('provider',$agent->provider)==$k?'selected':'' }}>{{ $v }}</option>
        @endforeach
      </select>
      <p class="hint" id="providerHint"></p>
    </div>
    <div>
      <label class="lbl">مدل *</label>
      <input name="model" id="modelInp" list="modelList" value="{{ old('model',$agent->model) }}" class="inp font-mono" dir="ltr" required>
      <datalist id="modelList"></datalist>
      <p class="hint">از لیست پیشنهاد انتخاب کنید یا نام دقیق مدل را تایپ کنید.</p>
    </div>
    <div>
      <label class="lbl">کلید API <span class="font-normal text-slate-400">— خالی = خواندن از env</span></label>
      <input name="api_key" type="password" value="" class="inp font-mono" dir="ltr" autocomplete="new-password"
        placeholder="{{ $agent->exists && $agent->api_key ? '••• ذخیره شده — برای تغییر، جدید وارد کنید •••' : 'sk-... (اختیاری)' }}">
    </div>
    <div>
      <label class="lbl">Base URL <span class="font-normal text-slate-400">— فقط Ollama / OpenAI-Like</span></label>
      <input name="base_url" value="{{ old('base_url',$agent->base_url) }}" class="inp font-mono" dir="ltr" placeholder="http://127.0.0.1:11434/api">
    </div>
  </div>
  <div id="goBox" class="mt-3 hidden rounded-xl border border-violet-200 bg-violet-50 p-3 text-xs leading-6 text-violet-900">
    <b>{!! svg_icon('info','w-4 h-4 inline-block -mt-0.5') !!}  OpenCode Go:</b> کلید را در <code dir="ltr">OPENCODE_GO_API_KEY</code> بگذارید. مدل‌های پیشنهادی:
    <span dir="ltr">longcat-2.5-preview-free (رایگان)</span> ·
    <span dir="ltr">mimo-v2.6-flash</span> ·
    <span dir="ltr">muse-spark-1.3-contributor</span> ·
    <span dir="ltr">glm-5.3-flash</span> ·
    <span dir="ltr">deepseek-v4.1-flash</span>
  </div>
</div>

{{-- ۳. رفتار --}}
<div class="card mt-4 p-5">
  <h2 class="mb-4 flex items-center gap-2 font-bold"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs text-white">۳</span> رفتار و لحن</h2>
  <div><label class="lbl">دستورالعمل سیستم (System Prompt)</label>
    <textarea name="system_prompt" id="sysPrompt" rows="4" class="inp" placeholder="شما یک دستیار فارسی‌زبان دقیق و مودب هستید...">{{ old('system_prompt',$agent->system_prompt) }}</textarea>
    <p class="hint"><span id="sysCount">۰</span> کاراکتر · هرچه دقیق‌تر بنویسید، جواب‌ها بهتر می‌شوند.</p>
  </div>
  <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
    <div>
      <label class="lbl">خلاقیت (Temperature): <b id="tempVal" class="text-blue-700">{{ old('temperature',$agent->temperature ?? 0.7) }}</b></label>
      <input type="range" name="temperature" id="tempRange" min="0" max="2" step="0.1" value="{{ old('temperature',$agent->temperature ?? 0.7) }}" class="w-full accent-blue-600">
      <p class="hint">۰ = دقیق · ۲ = خلاق</p>
    </div>
    <div><label class="lbl">سقف توکن پاسخ</label><input name="max_tokens" type="number" min="1" max="200000" value="{{ old('max_tokens',$agent->max_tokens) }}" class="inp font-mono" dir="ltr" placeholder="خالی = پیش‌فرض"></div>
    <div><label class="lbl">پنجره حافظه (توکن)</label><input name="context_window" type="number" min="1000" max="1000000" value="{{ old('context_window',$agent->context_window ?? 128000) }}" class="inp font-mono" dir="ltr"></div>
    <div><label class="lbl">سقف اجرای ابزار</label><input name="max_tool_runs" type="number" min="1" max="50" value="{{ old('max_tool_runs',$agent->max_tool_runs ?? 10) }}" class="inp font-mono" dir="ltr"></div>
  </div>
</div>

{{-- ۴. ابزارها --}}
<div class="card mt-4 p-5">
  <h2 class="mb-1 flex items-center gap-2 font-bold"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs text-white">۴</span> ابزارها (Tools)</h2>
  <p class="mb-4 text-xs text-slate-500">مشخص کنید ایجنت اجازه استفاده از کدام ابزارها را دارد. فقط موارد تیک‌خورده به مدل معرفی می‌شوند.</p>
  <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
    @foreach(\App\Neuron\ToolRegistry::AVAILABLE as $k=>$info)
      <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition hover:border-blue-300 hover:bg-blue-50/50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
        <input type="checkbox" name="tools[]" value="{{ $k }}" {{ in_array($k, old('tools',$agent->resolvedTools()))?'checked':'' }} class="mt-1 h-4 w-4 accent-blue-600">
        <span><b class="text-sm">{{ $info['label'] }}</b><br><span class="text-xs text-slate-500">{{ $info['desc'] }}</span></span>
      </label>
    @endforeach
  </div>
</div>

{{-- ۵. RAG --}}
<div class="card mt-4 p-5">
  <h2 class="mb-1 flex items-center gap-2 font-bold"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs text-white">۵</span> دسترسی به دانش (RAG)</h2>
  <p class="mb-4 text-xs text-slate-500">کدام محیط‌های دانش در اختیار این ایجنت باشند؟ موقع پاسخ، از همه محیط‌های انتخاب‌شده بازیابی می‌شود.</p>
  @if($envs->isEmpty())
    <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">هنوز محیطی ساخته نشده. <a class="font-semibold text-blue-600" href="{{ route('rags.create') }}">＋ ساخت محیط RAG</a></p>
  @else
    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
      @foreach($envs as $e)
        <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition hover:border-emerald-300 hover:bg-emerald-50/50 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
          <input type="checkbox" name="rag_environment_ids[]" value="{{ $e->id }}" {{ in_array($e->id, old('rag_environment_ids',$agent->resolvedRagIds()))?'checked':'' }} class="mt-1 h-4 w-4 accent-emerald-600">
          <span><b class="text-sm">{!! svg_icon('book','w-4 h-4 inline-block -mt-0.5') !!}  {{ $e->name }}</b> <span class="font-mono text-[11px] text-slate-400" dir="ltr">{{ $e->slug }}</span><br>
          <span class="text-xs text-slate-500">{{ fa_num($e->chunks_count ?? $e->chunks()->count()) }} چانک · امبدینگ: {{ $e->embedding_model }}</span></span>
        </label>
      @endforeach
    </div>
    <div class="mt-3 max-w-xs"><label class="lbl">تعداد چانک بازیابی (TopK)</label><input name="rag_top_k" type="number" min="1" max="20" value="{{ old('rag_top_k',$agent->rag_top_k ?? 4) }}" class="inp font-mono" dir="ltr"></div>
  @endif
</div>

{{-- ۶. MCP --}}
<div class="card mt-4 p-5">
  <h2 class="mb-1 flex items-center gap-2 font-bold"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs text-white">۶</span> اتصال MCP (ابزارهای خارجی — http)</h2>
  <p class="mb-4 text-xs text-slate-500">سرورهای MCP از نوع <code dir="ltr">http</code> را انتخاب کنید تا ابزارهایشان به این ایجنت اضافه شود. مدیریت سرورها در <a class="font-semibold text-blue-600" href="{{ route('mcps.index') }}">مرکز MCP</a>.</p>
  @if($mcps->isEmpty())
    <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">هنوز سرور MCP ثبت نشده. <a class="font-semibold text-blue-600" href="{{ route('mcps.create') }}">＋ افزودن سرور</a></p>
  @else
    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
      @foreach($mcps as $m)
        <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition hover:border-violet-300 hover:bg-violet-50/50 has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50">
          <input type="checkbox" name="mcp_server_ids[]" value="{{ $m->id }}" {{ in_array($m->id, old('mcp_server_ids', $agent->mcpServers->pluck('id')->all() ?? []))?'checked':'' }} class="mt-1 h-4 w-4 accent-violet-600">
          <span><b class="text-sm">{!! svg_icon('settings','w-4 h-4 inline-block -mt-0.5') !!} {{ $m->name }}</b> <span class="font-mono text-[11px] text-slate-400" dir="ltr">{{ $m->url }}</span><br>
          <span class="text-xs text-slate-500">{{ $m->description ?: 'بدون توضیح' }}</span></span>
        </label>
      @endforeach
    </div>
  @endif
</div>

{{-- ۷. سپر امنیتی (غیرقابل ویرایش، همیشه فعال) --}}
<div class="card mt-4 border-emerald-200 bg-emerald-50/60 p-5">
  <h2 class="mb-1 flex items-center gap-2 font-bold text-emerald-900"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-xs text-white">۷</span> {!! svg_icon('shield','w-4 h-4 inline-block -mt-0.5') !!}  سپر امنیتی (خودکار و اجباری)</h2>
  <ul class="mt-2 space-y-1 text-xs leading-6 text-emerald-900">
    <li>{!! svg_icon('check','w-4 h-4 inline-block -mt-0.5') !!}  دستورالعمل امنیتی به انتهای system prompt همه ایجنت‌ها <b>الحاق می‌شود</b> و از فرم قابل حذف نیست.</li>
    <li>{!! svg_icon('check','w-4 h-4 inline-block -mt-0.5') !!}  ابزار فایل‌سیستم فقط به پوشه <code dir="ltr">storage/app/agent-files</code> دسترسی دارد (بدون bash/نوشتن/حذف).</li>
    <li>{!! svg_icon('check','w-4 h-4 inline-block -mt-0.5') !!}  دیتابیس فقط SELECT است؛ جداول <code dir="ltr">users, api_tokens, jobs, sessions</code> و ستون‌های رمزی مسدودند.</li>
    <li>{!! svg_icon('check','w-4 h-4 inline-block -mt-0.5') !!}  دریافت وب فقط برای آدرس‌های عمومی است (localhost و شبکه داخلی و متادیتای ابری مسدود).</li>
    <li>{!! svg_icon('check','w-4 h-4 inline-block -mt-0.5') !!}  خروجی مدل قبل از ذخیره، از فیلتر افشای کلید عبور می‌کند.</li>
  </ul>
</div>

<div class="sticky bottom-4 mt-6 flex items-center gap-3 rounded-2xl border bg-white/95 p-3 shadow-lg backdrop-blur">  <label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" {{ old('is_active',$agent->is_active ?? true)?'checked':'' }} class="h-4 w-4 accent-emerald-600"> فعال</label>
  <button class="btn btn-primary flex-1 justify-center !py-2.5">{!! svg_icon('save','w-4 h-4 inline-block -mt-0.5') !!}  ذخیره ایجنت</button>
  <a href="{{ route('agents.index') }}" class="btn btn-ghost">انصراف</a>
</div>
</form>

@push('scripts')
<script>
const MODELS = @json($modelsByProvider);
const HINTS = {
  opencode_go: 'کلید از OPENCODE_GO_API_KEY خوانده می‌شود · هدرهای x-opencode-session خودکار ارسال می‌شود',
  ollama: 'نیاز به Ollama روشن روی سرور دارد · Base URL پیش‌فرض کافی است',
  openai_like: 'حتماً Base URL سرویس سازگار را وارد کنید',
  azure: 'Base URL دیپلوی Azure را وارد کنید',
};
const sel = document.getElementById('providerSel');
const list = document.getElementById('modelList');
const goBox = document.getElementById('goBox');
function refreshModels(){
  const p = sel.value;
  list.innerHTML = (MODELS[p]||[]).map(m=>`<option value="${m}">`).join('');
  document.getElementById('providerHint').textContent = HINTS[p] || '';
  goBox.classList.toggle('hidden', p !== 'opencode_go');
}
sel.addEventListener('change', refreshModels); refreshModels();
const r = document.getElementById('tempRange'), v = document.getElementById('tempVal');
r.addEventListener('input', ()=> v.textContent = r.value);
const ta = document.getElementById('sysPrompt'), c = document.getElementById('sysCount');
const upd = ()=> c.textContent = Number(ta.value.length).toLocaleString('fa-IR');
ta.addEventListener('input', upd); upd();
</script>
@endpush
@endsection
