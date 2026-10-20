@extends('layout')
@section('title','سرورهای MCP')
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-extrabold">{!! svg_icon('settings','w-6 h-6 inline-block -mt-0.5') !!} سرورهای MCP</h1>
    <p class="mt-1 text-sm text-slate-500">اتصال ایجنت‌ها به ابزارهای خارجی از طریق Model Context Protocol — فعلاً <b>HTTP (Streamable)</b> پشتیبانی می‌شود</p>
  </div>
  <div class="flex gap-2">
    <a href="{{ route('mcps.index', ['check' => 1]) }}" class="btn btn-ghost !text-xs">بررسی پیش‌نیازها</a>
    <a href="{{ route('mcps.create') }}" class="btn btn-primary">＋ سرور جدید</a>
  </div>
</div>

@if(request('check') === '1' && isset($prereq))
<div class="card p-5 mb-4">
  <h2 class="font-bold mb-3">بررسی پیش‌نیازها</h2>
  <div class="grid grid-cols-2 gap-2 text-xs">
    <div class="rounded-lg p-2 {{ $prereq['php_curl'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">ext-curl: {{ $prereq['php_curl'] ? 'فعال' : 'غیرفعال' }}</div>
    <div class="rounded-lg p-2 {{ $prereq['php_openssl'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">ext-openssl: {{ $prereq['php_openssl'] ? 'فعال' : 'غیرفعال' }}</div>
    <div class="rounded-lg p-2 {{ $prereq['allow_url_fopen'] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">allow_url_fopen: {{ $prereq['allow_url_fopen'] ? 'فعال' : 'غیرفعال (curl کافی است)' }}</div>
    <div class="rounded-lg p-2 {{ $prereq['outgoing_http'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">خروجی HTTP: {{ $prereq['outgoing_http'] ? 'در دسترس ('.fa_num($prereq['latency_ms']).'ms)' : 'مسدود — هاست اشتراکی ممکن است اتصال را بسته باشد' }}</div>
  </div>
  @if(!$prereq['outgoing_http'])
    <p class="mt-2 text-xs text-red-600">هاست شما خروجی HTTP را مسدود کرده — MCPهای http کار نخواهند کرد. با پشتیبانی هاست صحبت کنید یا از هاست با curl فعال استفاده کنید.</p>
  @endif
</div>
@endif

@if($servers->isEmpty())
  <div class="card p-10 text-center">
    <div class="text-5xl">{!! svg_icon('settings','w-6 h-6 inline-block -mt-0.5') !!}</div>
    <h2 class="mt-3 font-extrabold">هنوز سرور MCP ثبت نشده</h2>
    <p class="mt-1 text-sm text-slate-500">سرورهای http MCP را اینجا اضافه کنید؛ سپس در فرم ایجنت، آن‌ها را به ایجنت وصل کنید.</p>
    <a href="{{ route('mcps.create') }}" class="btn btn-primary mt-4">افزودن اولین سرور</a>
  </div>
@else
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
@foreach($servers as $s)
  <div class="card p-5">
    <div class="flex items-start justify-between gap-2">
      <div>
        <h2 class="font-extrabold">{{ $s->name }}</h2>
        <div class="font-mono text-[11px] text-slate-400" dir="ltr">{{ $s->url }}</div>
      </div>
      @if($s->is_active)<span class="pill bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">فعال</span>@else<span class="pill bg-slate-100 text-slate-500">غیرفعال</span>@endif
    </div>
    @if($s->description)<p class="mt-2 text-xs text-slate-500">{{ $s->description }}</p>@endif
    <div class="mt-3 flex flex-wrap gap-2">
      <a href="{{ route('mcps.test', $s) }}" class="btn btn-ghost !py-1.5 !text-xs">{!! svg_icon('search','w-4 h-4 inline-block -mt-0.5') !!} تست اتصال</a>
      <a href="{{ route('mcps.edit', $s) }}" class="btn btn-ghost !py-1.5 !text-xs">{!! svg_icon('edit','w-4 h-4 inline-block -mt-0.5') !!} ویرایش</a>
      <form method="POST" action="{{ route('mcps.destroy', $s) }}" class="mr-auto" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE')<button class="btn btn-danger-ghost !py-1.5 !text-xs">{!! svg_icon('trash','w-4 h-4 inline-block -mt-0.5') !!} حذف</button></form>
    </div>
  </div>
@endforeach
</div>
@endif

<div class="card mt-6 p-5 bg-slate-50/60">
  <h2 class="font-bold text-sm">نکات</h2>
  <ul class="mt-2 space-y-1 text-xs leading-6 text-slate-600">
    <li>• فقط <b>http(s) MCP</b> پشتیبانی می‌شود (Streamable HTTP). اجرای محلی با stdio در هاست اشتراکی قابل اعتماد نیست.</li>
    <li>• قبل از افزودن، «تست اتصال» بزنید — اگر هاست خروجی HTTP را بسته باشد، خطا خواهید دید.</li>
    <li>• پس از ثبت سرور، در <b>فرم ایجنت</b> آن را به ایجنت‌های موردنظر وصل کنید.</li>
  </ul>
</div>
@endsection
