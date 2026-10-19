@extends('layout')
@section('title', ($env->exists ? 'ویرایش محیط' : 'محیط جدید'))
@section('content')
<div class="mb-5">
  <h1 class="text-2xl font-extrabold">{{ $env->exists ? '{!! svg_icon('settings','w-4 h-4 inline-block -mt-0.5') !!}  ویرایش محیط RAG' : '＋ محیط RAG جدید' }}</h1>
  <p class="mt-1 text-sm text-slate-500">امبدینگ لوکال Ollama رایگان است و نیازی به کلید ندارد.</p>
</div>
<form method="POST" action="{{ $env->exists ? route('rags.update',$env) : route('rags.store') }}">
@csrf @if($env->exists)@method('PUT')@endif
<div class="card p-5">
  <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div><label class="lbl">نام محیط *</label><input name="name" value="{{ old('name',$env->name) }}" class="inp" required placeholder="مثلاً: دانش شرکت"></div>
    <div><label class="lbl">نامک (slug)</label><input name="slug" value="{{ old('slug',$env->slug) }}" class="inp font-mono" dir="ltr" placeholder="auto-generated"></div>
    <div class="md:col-span-2"><label class="lbl">توضیح</label><input name="description" value="{{ old('description',$env->description) }}" class="inp"></div>
    <div>
      <label class="lbl">سرویس امبدینگ</label>
      <select name="embedding_provider" id="embSel" class="inp">
        @foreach(\App\Models\RagEnvironment::embeddingProviders() as $k=>$v)
          <option value="{{ $k }}" {{ old('embedding_provider',$env->embedding_provider)==$k?'selected':'' }}>{{ $v }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="lbl">مدل امبدینگ</label>
      <input name="embedding_model" id="embModel" list="embList" value="{{ old('embedding_model',$env->embedding_model) }}" class="inp font-mono" dir="ltr">
      <datalist id="embList"></datalist>
      <p class="hint" id="embHint"></p>
    </div>
    <div><label class="lbl">کلید امبدینگ <span class="font-normal text-slate-400">— خالی = از env</span></label><input name="embedding_api_key" type="password" class="inp font-mono" dir="ltr" autocomplete="new-password"></div>
    <div><label class="lbl">Base URL امبدینگ</label><input name="embedding_base_url" value="{{ old('embedding_base_url',$env->embedding_base_url) }}" class="inp font-mono" dir="ltr" placeholder="http://127.0.0.1:11434/api"></div>
    <div><label class="lbl">اندازه چانک (کاراکتر)</label><input name="chunk_size" type="number" min="200" max="8000" value="{{ old('chunk_size',$env->chunk_size ?? 1000) }}" class="inp font-mono" dir="ltr"></div>
    <div><label class="lbl">هم‌پوشانی چانک‌ها</label><input name="chunk_overlap" type="number" min="0" max="1000" value="{{ old('chunk_overlap',$env->chunk_overlap ?? 100) }}" class="inp font-mono" dir="ltr"></div>
    <div><label class="lbl">TopK بازیابی</label><input name="top_k" type="number" min="1" max="20" value="{{ old('top_k',$env->top_k ?? 4) }}" class="inp font-mono" dir="ltr"></div>
    <div class="flex items-end"><label class="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" name="is_active" value="1" {{ old('is_active',$env->is_active ?? true)?'checked':'' }} class="h-4 w-4 accent-emerald-600"> فعال</label></div>
  </div>
</div>
<div class="mt-4 flex gap-2">
  <button class="btn btn-primary">{!! svg_icon('save','w-4 h-4 inline-block -mt-0.5') !!}  ذخیره</button>
  <a href="{{ route('rags.index') }}" class="btn btn-ghost">انصراف</a>
</div>
</form>
@push('scripts')
<script>
const EMB = { openai:['text-embedding-3-small','text-embedding-3-large'], ollama:['nomic-embed-text','mxbai-embed-large'], gemini:['gemini-embedding-001'], openai_like:[] };
const HINTS = { openai:'نیاز به OPENAI_API_KEY دارد', ollama:'لوکال و رایگان · نیاز به Ollama روشن', gemini:'نیاز به GEMINI_API_KEY دارد', openai_like:'Base URL سرویس سازگار را وارد کنید' };
const s=document.getElementById('embSel'), l=document.getElementById('embList');
function ref(){ l.innerHTML=(EMB[s.value]||[]).map(m=>`<option value="${m}">`).join(''); document.getElementById('embHint').textContent=HINTS[s.value]||''; }
s.addEventListener('change',ref); ref();
</script>
@endpush
@endsection
