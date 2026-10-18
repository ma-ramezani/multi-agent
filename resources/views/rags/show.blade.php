@extends('layout')
@section('title','محیط '.$env->name)
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-extrabold">📚 {{ $env->name }}</h1>
    <p class="mt-1 font-mono text-xs text-slate-400" dir="ltr">{{ $env->slug }} · {{ $env->embedding_provider }} / {{ $env->embedding_model }}</p>
  </div>
  <div class="flex gap-2">
    <a href="{{ route('rags.edit',$env) }}" class="btn btn-ghost !text-xs">⚙️ تنظیمات</a>
    <a href="{{ route('rags.index') }}" class="btn btn-ghost !text-xs">← همه محیط‌ها</a>
  </div>
</div>

<div class="grid grid-cols-2 gap-4 md:grid-cols-4">
  <div class="card p-4 text-center"><div class="text-2xl font-extrabold">{{ fa_num($stats['docs']) }}</div><div class="text-xs text-slate-500">📄 سند</div></div>
  <div class="card p-4 text-center"><div class="text-2xl font-extrabold">{{ fa_num($stats['chunks']) }}</div><div class="text-xs text-slate-500">🧩 چانک</div></div>
  <div class="card p-4 text-center"><div class="text-2xl font-extrabold text-emerald-600">{{ fa_num($stats['embedded']) }}</div><div class="text-xs text-slate-500">🔢 وکتوردار</div></div>
  <div class="card p-4 text-center"><div class="text-2xl font-extrabold">{{ $stats['chunks']>0 ? fa_num(round(100*$stats['embedded']/max(1,$stats['chunks']))) : fa_num(0) }}٪</div><div class="text-xs text-slate-500">پوشش امبدینگ</div></div>
</div>

{{-- Upload --}}
<div class="card mt-4 p-5">
  <h2 class="mb-3 font-bold">⬆️ افزودن دانش</h2>
  <form method="POST" action="{{ route('rags.upload',$env) }}" enctype="multipart/form-data" id="upForm">
    @csrf
    <label id="drop" class="block cursor-pointer rounded-xl border-2 border-dashed border-slate-300 bg-slate-50/60 p-6 text-center transition hover:border-blue-400 hover:bg-blue-50/50">
      <div class="text-3xl">📁</div>
      <div class="mt-1 text-sm font-semibold">فایل‌ها را اینجا رها کنید یا کلیک کنید</div>
      <div class="mt-0.5 text-xs text-slate-500">Word (docx) · Excel (xlsx/xls) · PowerPoint (pptx) · PDF · عکس (OCR فارسی) · txt/md/csv/html — تا {{ fa_num('۵۰') }} مگابایت</div>
      <input type="file" name="files[]" id="fileInp" multiple accept=".txt,.md,.markdown,.csv,.json,.log,.xml,.html,.htm,.pdf,.docx,.xlsx,.xls,.pptx,.png,.jpg,.jpeg,.webp,.gif,.bmp" class="hidden">
      <div id="fileList" class="mt-2 space-y-1 text-xs text-slate-600"></div>
    </label>
    <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
      <input name="raw_name" placeholder="عنوان متن دستی…" class="inp md:col-span-1">
      <textarea name="raw_text" rows="2" placeholder="…یا متن دانش را اینجا بچسبانید" class="inp md:col-span-2"></textarea>
    </div>
    <button class="btn btn-green mt-3">✅ افزودن و ایندکس خودکار</button>
  </form>
  <p class="hint">بدون کلید امبدینگ، جستجوی کلیدواژه‌ای فعال است · با Ollama لوکال، وکتور معنایی ساخته می‌شود.</p>
</div>

{{-- Docs --}}
<div class="card mt-4 overflow-hidden">
  <div class="border-b px-5 py-3 font-bold">📄 اسناد ({{ fa_num($stats['docs']) }})</div>
  @if($env->documents->isEmpty())
    <p class="px-5 py-6 text-sm text-slate-400">سندی ثبت نشده است.</p>
  @else
  <table class="tbl w-full">
    <thead><tr><th>سند</th><th>وضعیت</th><th>چانک</th><th>تاریخ</th><th></th></tr></thead>
    <tbody>
    @foreach($env->documents as $d)
      <tr>
        <td><div class="font-semibold">{{ $d->original_name }}</div><div class="text-[11px] text-slate-400">{{ $d->mime }} · {{ fa_bytes($d->size) }}</div>
          @if($d->error)<div class="mt-1 max-w-md truncate text-[11px] text-red-500" title="{{ $d->error }}">⚠️ {{ $d->error }}</div>@endif</td>
        <td>@if($d->status=='ready')<span class="pill bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">✅ آماده</span>
          @elseif($d->status=='processing')<span class="pill bg-amber-50 text-amber-700 ring-1 ring-amber-200">⏳ در حال پردازش</span>
          @else<span class="pill bg-red-50 text-red-700 ring-1 ring-red-200">❌ {{ $d->status }}</span>@endif</td>
        <td class="font-mono">{{ fa_num($d->chunks_count) }}</td>
        <td class="whitespace-nowrap text-xs text-slate-500">{{ fa_date($d->created_at) }}</td>
        <td class="whitespace-nowrap text-left">
          <a href="{{ route('rags.docs.edit',[$env,$d]) }}" class="btn btn-ghost !px-2 !py-1 !text-[11px]" title="ویرایش متن و مشخصات">✏️</a>
          @if($d->stored_path || $d->raw_text)<a href="{{ route('files.rag',$d) }}" class="btn btn-ghost !px-2 !py-1 !text-[11px]" title="دانلود">⬇️</a>@endif
          <form method="POST" action="{{ route('rags.reindex',[$env,$d]) }}" class="inline">@csrf<button class="btn btn-ghost !px-2 !py-1 !text-[11px]" title="ایندکس مجدد">🔄</button></form>
          <form method="POST" action="{{ route('rags.docs.destroy',[$env,$d]) }}" class="inline" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE')<button class="btn btn-danger-ghost !px-2 !py-1 !text-[11px]" title="حذف">🗑</button></form>
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
  @endif
</div>

{{-- Chunks preview --}}
<div class="card mt-4 overflow-hidden">
  <div class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-3">
    <h2 class="font-bold">🧩 پیش‌نمایش چانک‌ها ({{ fa_num($stats['chunks']) }})</h2>
    <input id="chunkSearch" class="inp !w-56 !py-1.5 !text-xs" placeholder="🔍 جستجو در چانک‌ها…">
  </div>
  <div id="chunkList" class="max-h-96 space-y-2 overflow-y-auto p-4">
    @forelse($chunks as $c)
      <details class="chunk rounded-lg border bg-slate-50/60 px-3 py-2 text-xs" data-text="{{ mb_substr(str_replace('"','',$c->content),0,400) }}">
        <summary class="flex items-center gap-2">
          <span class="font-mono text-[10px] text-slate-400">#{{ fa_num($c->id) }}</span>
          <span class="font-semibold">{{ $c->document->original_name ?? $c->source_name }}</span>
          @if($c->embedding)<span class="pill bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">وکتور ✓</span>
          @else<span class="pill bg-amber-50 text-amber-700 ring-1 ring-amber-200">بدون وکتور</span>@endif
          <span class="mr-auto text-slate-400">{{ fa_num(mb_strlen($c->content)) }} کاراکتر</span>
        </summary>
        <p class="mt-2 whitespace-pre-wrap leading-6 text-slate-600">{{ mb_substr($c->content,0,1200) }}</p>
      </details>
    @empty
      <p class="text-sm text-slate-400">چانکی نیست.</p>
    @endforelse
  </div>
</div>

@push('scripts')
<script>
const dz = document.getElementById('drop'), fi = document.getElementById('fileInp'), fl = document.getElementById('fileList');
['dragover','dragenter'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.add('border-blue-500','bg-blue-50');}));
['dragleave','drop'].forEach(e=>dz.addEventListener(e,ev=>{ev.preventDefault();dz.classList.remove('border-blue-500','bg-blue-50');}));
dz.addEventListener('drop',ev=>{ fi.files = ev.dataTransfer.files; showFiles(); });
fi.addEventListener('change', showFiles);
function showFiles(){ fl.innerHTML=[...fi.files].map(f=>`<div>📎 ${f.name} <span class="text-slate-400">(${(f.size/1024).toFixed(0)} KB)</span></div>`).join(''); }
const q=document.getElementById('chunkSearch');
q.addEventListener('input',()=>{
  const t=q.value.trim();
  document.querySelectorAll('#chunkList details.chunk').forEach(d=>{
    d.style.display = !t || d.textContent.includes(t) ? '' : 'none';
  });
});
</script>
@endpush
@endsection
