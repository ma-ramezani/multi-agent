@extends('layout')
@section('title', $server->exists ? 'ویرایش سرور MCP' : 'سرور MCP جدید')
@section('content')
<div class="mb-5">
  <h1 class="text-2xl font-extrabold">@if($server->exists) ویرایش {{ $server->name }} @else ＋ سرور MCP جدید @endif</h1>
  <p class="mt-1 text-sm text-slate-500">فقط http(s) MCP پشتیبانی می‌شود — آدرس باید با https:// شروع شود.</p>
</div>
<form method="POST" action="{{ $server->exists ? route('mcps.update', $server) : route('mcps.store') }}">
@csrf @if($server->exists)@method('PUT')@endif
<div class="card p-5">
  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div><label class="lbl">نام *</label><input name="name" value="{{ old('name', $server->name) }}" class="inp" required placeholder="مثلاً: جستجوی وب"></div>
    <div><label class="lbl">نامک (slug)</label><input name="slug" value="{{ old('slug', $server->slug) }}" class="inp font-mono" dir="ltr" placeholder="auto-generated"></div>
    <div class="md:col-span-2"><label class="lbl">توضیح</label><input name="description" value="{{ old('description', $server->description) }}" class="inp" placeholder="این سرور چه ابزارهایی می‌دهد؟"></div>
    <div class="md:col-span-2"><label class="lbl">آدرس MCP (http) *</label><input name="url" value="{{ old('url', $server->url) }}" class="inp font-mono" dir="ltr" required placeholder="https://mcp.example.com/mcp"></div>
    <div class="md:col-span-2"><label class="lbl">هدرهای اضافی <span class="font-normal text-slate-400">— JSON یا هر خط Key: Value</span></label>
      <textarea name="headers_raw" rows="3" class="inp font-mono !text-[13px]" dir="ltr" placeholder='{"Authorization":"Bearer TOKEN"}&#10;یا&#10;Authorization: Bearer TOKEN'>{{ old('headers_raw', $server->headers ? json_encode($server->headers, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) : '') }}</textarea>
      <p class="hint">برای احراز هویت توکن را اینجا بگذارید — در دیتابیس ذخیره می‌شود (قابل ویرایش).</p>
    </div>
    <div class="flex items-end"><label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $server->is_active ?? true) ? 'checked' : '' }} class="h-4 w-4 accent-emerald-600"> فعال</label></div>
  </div>
</div>
<div class="mt-4 flex gap-2">
  <button class="btn btn-primary">{!! svg_icon('save','w-4 h-4 inline-block -mt-0.5') !!} ذخیره</button>
  <a href="{{ route('mcps.index') }}" class="btn btn-ghost">انصراف</a>
</div>
</form>
@endsection
