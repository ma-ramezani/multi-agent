@extends('layout')
@section('title','تست MCP')
@section('content')
<div class="mb-5">
  <h1 class="text-2xl font-extrabold">تست {{ $mcp->name }}</h1>
  <p class="font-mono text-xs text-slate-400" dir="ltr">{{ $mcp->url }}</p>
</div>

<div class="card p-5">
  <h2 class="font-bold mb-3">پیش‌نیازهای سیستم</h2>
  <div class="grid grid-cols-2 gap-2 text-xs">
    <div class="rounded-lg p-2 {{ $result['php_curl'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">ext-curl: {{ $result['php_curl'] ? 'فعال' : 'غیرفعال' }}</div>
    <div class="rounded-lg p-2 {{ $result['php_openssl'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">ext-openssl: {{ $result['php_openssl'] ? 'فعال' : 'غیرفعال' }}</div>
    <div class="rounded-lg p-2 {{ $result['allow_url_fopen'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">allow_url_fopen: {{ $result['allow_url_fopen'] ? 'فعال' : 'غیرفعال' }}</div>
    <div class="rounded-lg p-2 {{ $result['outgoing_http'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">خروجی HTTP: {{ $result['outgoing_http'] ? 'OK ('.fa_num($result['latency_ms']).'ms)' : 'مسدود' }}</div>
  </div>
  @if(!$result['outgoing_http'])
    <p class="mt-2 text-xs text-red-600">{{ $result['outgoing_error'] }}</p>
  @endif
</div>

<div class="card p-5 mt-4">
  <h2 class="font-bold mb-3">اتصال به سرور MCP</h2>
  @if($result['target_ok'])
    <div class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">در دسترس است — سرور پاسخ داد.
      @if($result['target_error'])<br><span class="text-amber-700">{{ $result['target_error'] }}</span>@endif
    </div>
  @else
    <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">اتصال ناموفق
      @if($result['target_error'])<br>{{ $result['target_error'] }}@endif
    </div>
  @endif

  @if($tools !== null)
    <h3 class="font-bold mt-4 mb-2">ابزارهای کشف‌شده ({{ count($tools) }})</h3>
    @if(empty($tools))
      <p class="text-xs text-slate-500">سرور ابزار معرفی نکرد یا لیست خالی برگرداند.</p>
    @else
      <div class="space-y-2">
        @foreach($tools as $t)
          <div class="rounded-lg border p-2 text-xs"><b>{{ $t['name'] }}</b> — {{ $t['description'] ?? '—' }}</div>
        @endforeach
      </div>
    @endif
  @endif
  @if($toolsError)
    <p class="mt-2 text-xs text-red-600">خطا در دریافت ابزارها: {{ $toolsError }}</p>
  @endif
</div>

<div class="mt-4"><a href="{{ route('mcps.index') }}" class="btn btn-ghost">بازگشت</a></div>
@endsection
