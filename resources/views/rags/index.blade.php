@extends('layout')
@section('title','محیط‌های RAG')
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-extrabold">📚 محیط‌های دانش (RAG)</h1>
    <p class="mt-1 text-sm text-slate-500">هر محیط یک پایگاه دانش مستقل است: امبدینگ، چانکینگ و بازیابی خودش را دارد</p>
  </div>
  <a href="{{ route('rags.create') }}" class="btn btn-primary">＋ محیط جدید</a>
</div>

@if($envs->isEmpty())
  <div class="card p-10 text-center">
    <div class="text-5xl">📚</div>
    <h2 class="mt-3 font-extrabold">هنوز محیط دانشی نساخته‌اید</h2>
    <p class="mt-1 text-sm text-slate-500">فایل آپلود کنید یا متن بچسبانید؛ سیستم خودش چانک و وکتور می‌سازد.</p>
    <a href="{{ route('rags.create') }}" class="btn btn-primary mt-4">ساخت اولین محیط</a>
  </div>
@else
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
@foreach($envs as $e)
  @php $pct = $e->chunks_count > 0 ? round(100 * ($e->chunks()->whereNotNull('embedding')->count() / max(1,$e->chunks_count))) : 0; @endphp
  <div class="card card-hover flex flex-col p-5">
    <div class="flex items-start justify-between gap-2">
      <h2 class="font-extrabold">📚 {{ $e->name }}</h2>
      @if($e->is_active)<span class="pill bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">● فعال</span>
      @else<span class="pill bg-slate-100 text-slate-500 ring-1 ring-slate-200">○ غیرفعال</span>@endif
    </div>
    <div class="font-mono text-[11px] text-slate-400" dir="ltr">{{ $e->slug }}</div>
    @if($e->description)<p class="mt-1 text-xs text-slate-500">{{ $e->description }}</p>@endif
    <div class="mt-3 grid grid-cols-3 gap-2 text-center">
      <div class="rounded-lg bg-slate-50 p-2"><div class="text-lg font-extrabold">{{ fa_num($e->documents_count) }}</div><div class="text-[11px] text-slate-500">سند</div></div>
      <div class="rounded-lg bg-slate-50 p-2"><div class="text-lg font-extrabold">{{ fa_num($e->chunks_count) }}</div><div class="text-[11px] text-slate-500">چانک</div></div>
      <div class="rounded-lg bg-slate-50 p-2"><div class="text-lg font-extrabold">{{ fa_num($pct) }}٪</div><div class="text-[11px] text-slate-500">وکتوردار</div></div>
    </div>
    <div class="mt-2 font-mono text-[11px] text-slate-500" dir="ltr">{{ $e->embedding_provider }} / {{ $e->embedding_model }}</div>
    <div class="mt-4 flex gap-2 border-t pt-3">
      <a href="{{ route('rags.show',$e) }}" class="btn btn-green flex-1 justify-center !py-1.5 !text-xs">📄 مدیریت اسناد</a>
      <a href="{{ route('rags.edit',$e) }}" class="btn btn-ghost !py-1.5 !text-xs">⚙️ تنظیمات</a>
      <form method="POST" action="{{ route('rags.destroy',$e) }}" onsubmit="return confirm('محیط و همه اسنادش حذف شود؟')">@csrf @method('DELETE')<button class="btn btn-danger-ghost !py-1.5 !text-xs">🗑</button></form>
    </div>
  </div>
@endforeach
</div>
@endif
@endsection
