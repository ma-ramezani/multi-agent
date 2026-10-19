@extends('layout')
@section('title','مرکز امنیت')
@section('content')
<div class="mb-5">
  <h1 class="flex items-center gap-2 text-2xl font-extrabold">{!! svg_icon('shield','w-7 h-7 text-emerald-600') !!} مرکز امنیت</h1>
  <p class="mt-1 text-sm text-slate-500">سپرها، محدودیت‌ها و ممیزی — همه از یک‌جا، بدون دست‌کاری کد</p>
</div>

<div class="grid grid-cols-2 gap-4 md:grid-cols-4">
  <div class="card p-4 text-center"><div class="text-2xl font-extrabold">{{ fa_num($counts['blocked'] ?? 0) }}</div><div class="text-xs text-red-600">حمله/تلاش بلاک‌شده</div></div>
  <div class="card p-4 text-center"><div class="text-2xl font-extrabold">{{ fa_num($counts['warn'] ?? 0) }}</div><div class="text-xs text-amber-600">هشدار</div></div>
  <div class="card p-4 text-center"><div class="text-2xl font-extrabold">{{ fa_num($health['queue'] ?? 0) }}</div><div class="text-xs text-slate-500">job در صف</div></div>
  <div class="card p-4 text-center"><div class="text-2xl font-extrabold">{!! $health['ollama'] ? '<span class="text-emerald-600">●</span>' : '<span class="text-red-500">●</span>' !!}</div><div class="text-xs text-slate-500">Ollama (امبدینگ لوکال)</div></div>
</div>

<form method="POST" action="{{ route('security.update') }}" class="mt-4">
@csrf
@foreach($groups as $gname => $keys)
<div class="card mt-4 p-5">
  <h2 class="mb-4 font-bold">{{ $gname }}</h2>
  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
  @foreach($keys as $k)
    @php $type = \App\Models\SecuritySetting::TYPES[$k] ?? 'string'; $val = $settings[$k] ?? ''; @endphp
    @if($type === 'bool')
      <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border p-3 has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
        <span class="text-sm font-semibold">{{ \App\Models\SecuritySetting::LABELS[$k] ?? $k }}<br><span class="font-mono text-[11px] text-slate-400" dir="ltr">{{ $k }}</span></span>
        <input type="checkbox" name="{{ $k }}" value="1" {{ $val==='1'?'checked':'' }} class="h-5 w-5 accent-emerald-600">
      </label>
    @elseif($type === 'text')
      <div class="md:col-span-2"><label class="lbl">{{ \App\Models\SecuritySetting::LABELS[$k] ?? $k }} <span class="font-mono text-[11px] text-slate-400" dir="ltr">{{ $k }}</span></label>
      <textarea name="{{ $k }}" rows="2" class="inp font-mono !text-[13px]" dir="auto">{{ $val }}</textarea></div>
    @else
      <div><label class="lbl">{{ \App\Models\SecuritySetting::LABELS[$k] ?? $k }}</label><input name="{{ $k }}" value="{{ $val }}" class="inp font-mono" dir="ltr"></div>
    @endif
  @endforeach
  </div>
</div>
@endforeach
<div class="mt-4 flex gap-2">
  <button class="btn btn-primary">{!! svg_icon('save','w-4 h-4 inline-block -mt-0.5') !!} ذخیره تنظیمات</button>
</div>
</form>

<div class="card mt-6 overflow-hidden">
  <div class="flex items-center justify-between border-b px-5 py-3">
    <h2 class="font-bold">رویدادهای امنیتی (۱۰۰ مورد اخیر)</h2>
    <form method="POST" action="{{ route('security.clear') }}" onsubmit="return confirm('پاک شوند؟')">@csrf @method('DELETE')<button class="btn btn-danger-ghost !py-1 !text-xs">پاک‌سازی</button></form>
  </div>
  @if($events->isEmpty())
    <p class="px-5 py-6 text-sm text-slate-400">رویدادی ثبت نشده است.</p>
  @else
  <table class="tbl w-full">
    <thead><tr><th>زمان</th><th>نوع</th><th>سطح</th><th>شرح</th><th>IP</th></tr></thead>
    <tbody>
    @foreach($events as $e)
      <tr>
        <td class="whitespace-nowrap text-xs text-slate-500">{{ fa_date($e->created_at) }}</td>
        <td><code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px]" dir="ltr">{{ $e->type }}</code></td>
        <td>@if($e->level=='blocked')<span class="pill bg-red-50 text-red-700 ring-1 ring-red-200">blocked</span>@elseif($e->level=='warn')<span class="pill bg-amber-50 text-amber-700 ring-1 ring-amber-200">warn</span>@else<span class="pill bg-slate-100 text-slate-500 ring-1 ring-slate-200">info</span>@endif</td>
        <td class="text-xs">{{ $e->message }}</td>
        <td class="font-mono text-[11px] text-slate-400" dir="ltr">{{ $e->ip }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
  @endif
</div>
@endsection
