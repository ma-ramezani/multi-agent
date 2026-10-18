<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'پلتفرم ایجنت‌ها') · Neuron</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🤖</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: { extend: { fontFamily: { sans: ['Vazirmatn','Tahoma','sans-serif'] },
    colors: { brand: {50:'#eef4ff',100:'#dbe7fe',500:'#3b6fe0',600:'#2f5cd0',700:'#274aa8',900:'#1e2a5a'} } } }
}
</script>
<style>
  html{scroll-behavior:smooth}
  body{font-family:'Vazirmatn',Tahoma,sans-serif;background:#f3f5fa;color:#1e293b;-webkit-font-smoothing:antialiased}
  ::-webkit-scrollbar{width:9px;height:9px}
  ::-webkit-scrollbar-track{background:transparent}
  ::-webkit-scrollbar-thumb{background:#c4cbdb;border-radius:99px;border:2px solid transparent;background-clip:content-box}
  ::-webkit-scrollbar-thumb:hover{background:#9aa5c0;background-clip:content-box;border:2px solid transparent}
  .card{background:#fff;border:1px solid #e6eaf2;border-radius:1rem;box-shadow:0 1px 2px rgba(16,24,40,.04)}
  .card-hover{transition:transform .15s ease,box-shadow .15s ease}
  .card-hover:hover{transform:translateY(-2px);box-shadow:0 8px 24px -8px rgba(30,42,90,.18)}
  .inp{width:100%;border:1px solid #dbe0ec;border-radius:.65rem;padding:.55rem .8rem;font-size:.875rem;background:#fff;transition:border-color .15s,box-shadow .15s}
  .inp:focus{outline:none;border-color:#3b6fe0;box-shadow:0 0 0 3px rgba(59,111,224,.15)}
  .lbl{display:block;font-size:.8rem;font-weight:600;color:#475569;margin-bottom:.35rem}
  .hint{font-size:.72rem;color:#94a3b8;margin-top:.25rem}
  .btn{display:inline-flex;align-items:center;gap:.4rem;font-weight:600;font-size:.85rem;border-radius:.65rem;padding:.55rem 1.1rem;transition:all .15s;cursor:pointer}
  .btn-primary{background:#2f5cd0;color:#fff}.btn-primary:hover{background:#274aa8}
  .btn-green{background:#15803d;color:#fff}.btn-green:hover{background:#166534}
  .btn-ghost{background:#eef1f7;color:#334155}.btn-ghost:hover{background:#e2e8f3}
  .btn-danger-ghost{background:#fef2f2;color:#dc2626}.btn-danger-ghost:hover{background:#fee2e2}
  .navlink{display:flex;align-items:center;gap:.7rem;padding:.6rem .9rem;border-radius:.7rem;font-size:.88rem;font-weight:500;color:#c6d0e8;transition:all .15s}
  .navlink:hover{background:rgba(255,255,255,.07);color:#fff}
  .navlink.active{background:rgba(255,255,255,.12);color:#fff;font-weight:700}
  .pill{display:inline-flex;align-items:center;gap:.3rem;font-size:.72rem;font-weight:600;padding:.15rem .6rem;border-radius:999px;ring-width:1px}
  .tbl th{font-size:.72rem;font-weight:600;color:#8492ad;text-align:right;padding:.7rem .9rem;background:#f7f9fc;border-bottom:1px solid #e6eaf2;white-space:nowrap}
  .tbl td{padding:.8rem .9rem;font-size:.85rem;border-bottom:1px solid #eef1f6;vertical-align:middle}
  .tbl tr:last-child td{border-bottom:none}
  .tbl tbody tr{transition:background .12s}.tbl tbody tr:hover{background:#f8faff}
  .md-code{direction:ltr;text-align:left;background:#0f172a;color:#dbe7fe;border-radius:.6rem;padding:.7rem .9rem;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.78rem;overflow-x:auto;margin:.5rem 0;white-space:pre}
  .typing-dot{width:7px;height:7px;border-radius:99px;background:#94a3b8;display:inline-block;animation:blink 1.2s infinite}
  .typing-dot:nth-child(2){animation-delay:.2s}.typing-dot:nth-child(3){animation-delay:.4s}
  @keyframes blink{0%,80%,100%{opacity:.25}40%{opacity:1}}
  .fade-in{animation:fadeUp .25s ease}
  @keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
  details.chunk summary{cursor:pointer}
</style>
@stack('head')
</head>
<body>
<div class="flex min-h-screen">
  <!-- Sidebar -->
  <aside class="hidden lg:flex w-64 shrink-0 flex-col text-white px-4 py-6 sticky top-0 h-screen" style="background:linear-gradient(180deg,#141c38 0%,#1e2a5a 100%)">
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 px-2 mb-8">
      <span class="text-3xl">🤖</span>
      <span><span class="block font-extrabold text-lg leading-6">پلتفرم ایجنت</span>
      <span class="block text-[11px] text-slate-400 font-normal">Neuron AI · Laravel 12</span></span>
    </a>
    <nav class="space-y-1 flex-1">
      <a href="{{ route('dashboard') }}" class="navlink {{ request()->routeIs('dashboard')?'active':'' }}">🏠 داشبورد</a>
      <a href="{{ route('agents.index') }}" class="navlink {{ request()->routeIs('agents.*')?'active':'' }}">🧠 ایجنت‌ها</a>
      <a href="{{ route('rags.index') }}" class="navlink {{ request()->routeIs('rags.*')?'active':'' }}">📚 محیط‌های RAG</a>
      <a href="{{ route('chats.index') }}" class="navlink {{ request()->routeIs('chats.*')?'active':'' }}">💬 چت زنده</a>
      <a href="{{ route('tokens.index') }}" class="navlink {{ request()->routeIs('tokens.*')?'active':'' }}">🔑 توکن‌های API</a>
      <a href="{{ route('api.docs') }}" class="navlink {{ request()->routeIs('api.docs')?'active':'' }}">📖 مستندات API</a>
    </nav>
    <div class="rounded-xl p-3 text-[11px] leading-5" style="background:rgba(255,255,255,.07)">
      <b class="text-emerald-300">● سیستم فعال</b><br>
      <span class="text-slate-400">Ollama · امبدینگ لوکال<br>OpenCode Go · چت</span>
    </div>
  </aside>

  <!-- Main -->
  <div class="flex-1 min-w-0">
    <!-- Mobile topbar -->
    <div class="lg:hidden sticky top-0 z-20 bg-white/90 backdrop-blur border-b px-4 py-2 flex gap-3 overflow-x-auto text-sm font-medium">
      <a href="{{ route('dashboard') }}">🏠</a><a href="{{ route('agents.index') }}">🧠</a><a href="{{ route('rags.index') }}">📚</a><a href="{{ route('chats.index') }}">💬</a><a href="{{ route('tokens.index') }}">🔑</a><a href="{{ route('api.docs') }}">📖</a>
    </div>
    <main class="max-w-6xl mx-auto p-4 md:p-8">
      @if(session('ok'))
        <div class="fade-in mb-4 flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">✅ <span>{{ session('ok') }}</span></div>
      @endif
      @if(session('plain_token'))
        <div class="fade-in mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm" dir="ltr">
          🔑 New token — <b>copy it now, it won't be shown again:</b><br>
          <code id="plainToken" class="block mt-1 select-all rounded bg-slate-900 px-3 py-2 text-emerald-300 font-mono text-xs break-all">{{ session('plain_token') }}</code>
          <button onclick="navigator.clipboard.writeText(document.getElementById('plainToken').innerText);this.innerText='✓ Copied'" class="mt-2 rounded bg-slate-900 px-3 py-1 text-xs text-white">Copy</button>
        </div>
      @endif
      @if($errors->any())
        <div class="fade-in mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
          <b>⚠️ خطا در فرم:</b>
          <ul class="mt-1 list-disc pr-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
      @endif
      @yield('content')
      <footer class="mt-10 border-t pt-4 text-center text-[11px] text-slate-400">ساخته‌شده با Neuron AI · {{ fa_num(date('Y')) }}</footer>
    </main>
  </div>
</div>
@stack('scripts')
</body>
</html>
