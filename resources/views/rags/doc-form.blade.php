@extends('layout')
@section('title','ویرایش سند')
@section('content')
<div class="mb-5">
  <h1 class="text-2xl font-extrabold">✏️ ویرایش سند</h1>
  <p class="mt-1 text-sm text-slate-500">محیط <b>{{ $env->name }}</b> · متن را اصلاح کنید یا فایل را جایگزین کنید؛ بعد از ذخیره، سند دوباره ایندکس می‌شود.</p>
</div>

<div class="mb-4 flex flex-wrap gap-2 text-xs">
  <span class="pill bg-slate-100 text-slate-600 ring-1 ring-slate-200">📄 {{ $doc->original_name }}</span>
  @if($doc->status=='ready')<span class="pill bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">✅ آماده · {{ fa_num($doc->chunks_count) }} چانک</span>
  @else<span class="pill bg-red-50 text-red-700 ring-1 ring-red-200">❌ {{ $doc->error ?: $doc->status }}</span>@endif
  @if($readKind)<span class="pill bg-blue-50 text-blue-700 ring-1 ring-blue-200">خوانش: {{ $readKind }}</span>@endif
  @if($doc->stored_path)<a href="{{ route('files.rag',$doc) }}" class="pill bg-slate-100 text-slate-700 ring-1 ring-slate-200 hover:bg-slate-200">⬇️ دانلود فایل اصلی</a>@endif
  @if($doc->mime)<span class="pill bg-slate-50 text-slate-500 ring-1 ring-slate-200" dir="ltr">{{ $doc->mime }}</span>@endif
</div>

<form method="POST" action="{{ route('rags.docs.update',[$env,$doc]) }}" enctype="multipart/form-data">
@csrf @method('PUT')
<div class="card p-5">
  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div><label class="lbl">نام سند *</label><input name="original_name" value="{{ old('original_name',$doc->original_name) }}" class="inp" required></div>
    <div>
      <label class="lbl">جایگزینی فایل <span class="font-normal text-slate-400">— اختیاری</span></label>
      <input type="file" name="replace_file" class="inp">
      <p class="hint">pdf · docx · xlsx/xls · pptx · عکس (OCR فارسی) · txt/md/csv… — با انتخاب فایل جدید، متن دستی نادیده گرفته می‌شود.</p>
      <label class="mt-2 flex cursor-pointer items-start gap-2 rounded-xl border border-violet-200 bg-violet-50 p-2.5 text-xs leading-5">
        <input type="checkbox" name="force_ocr" value="1" {{ old('force_ocr', $doc->force_ocr ?? false) ? 'checked' : '' }} class="mt-0.5 accent-violet-600">
        <span><b>استخراج با OCR</b> — اگر متن استخراج‌شده PDF به‌هم‌ریخته/معکوس است، تیک بزنید تا کل PDF صفحه‌به‌صفحه OCR شود (کندتر ولی تمیز؛ در پس‌زمینه انجام می‌شود).</span>
      </label>
    </div>
  </div>
  <div class="mt-4">
    <div class="mb-1 flex items-center justify-between">
      <label class="lbl !mb-0">متن سند (منبع ایندکس)</label>
      <span class="text-[11px] text-slate-400"><span id="cCount">۰</span> کاراکتر · <span id="wCount">۰</span> کلمه تقریبی</span>
    </div>
    <textarea name="content" id="contentTa" rows="16" class="inp font-mono !text-[13px] !leading-7" dir="auto">{{ old('content', $extracted) }}</textarea>
    <p class="hint">این همان متنی است که چانک و وکتور از روی آن ساخته می‌شود. اصلاح کنید و ذخیره بزنید. برای عکس‌ها، اگر OCR چیزی جا انداخته، اینجا کاملش کنید.</p>
  </div>
</div>
<div class="mt-4 flex gap-2">
  <button class="btn btn-primary">💾 ذخیره و ایندکس مجدد</button>
  <a href="{{ route('rags.show',$env) }}" class="btn btn-ghost">انصراف</a>
</div>
</form>

@push('scripts')
<script>
const ta=document.getElementById('contentTa');
function upd(){
  document.getElementById('cCount').textContent=Number(ta.value.length).toLocaleString('fa-IR');
  document.getElementById('wCount').textContent=Number(ta.value.trim().split(/\s+/).filter(Boolean).length).toLocaleString('fa-IR');
}
ta.addEventListener('input',upd); upd();
</script>
@endpush
@endsection
