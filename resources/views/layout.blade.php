<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@yield('title', 'پلتفرم ایجنت‌ها') - Neuron Laravel</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body{font-family:Vazirmatn,Tahoma,sans-serif;background:#f3f4f6}
.chat-bubble-user{background:#2563eb;color:#fff}
.chat-bubble-ai{background:#fff;border:1px solid #e5e7eb}
pre{white-space:pre-wrap}
</style>
</head>
<body>
<nav class="bg-slate-900 text-white px-6 py-3 flex items-center gap-6">
  <a href="{{ route('dashboard') }}" class="font-bold text-lg">🤖 پلتفرم ایجنت Neuron</a>
  <a href="{{ route('agents.index') }}" class="hover:text-sky-300">ایجنت‌ها</a>
  <a href="{{ route('rags.index') }}" class="hover:text-sky-300">محیط‌های RAG</a>
  <a href="{{ route('chats.index') }}" class="hover:text-sky-300">چت زنده</a>
  <a href="{{ route('tokens.index') }}" class="hover:text-sky-300">توکن‌های API</a>
  <a href="{{ route('api.docs') }}" class="hover:text-sky-300">مستندات API</a>
</nav>
<div class="max-w-6xl mx-auto p-6">
  @if(session('ok'))<div class="bg-green-100 border border-green-300 text-green-800 rounded p-3 mb-4">{{ session('ok') }}</div>@endif
  @if(session('plain_token'))<div class="bg-yellow-100 border border-yellow-400 rounded p-3 mb-4" dir="ltr">New token (copy now): <code class="font-bold">{{ session('plain_token') }}</code></div>@endif
  @if($errors->any())<div class="bg-red-100 border border-red-300 text-red-800 rounded p-3 mb-4"><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  @yield('content')
</div>
</body>
</html>
