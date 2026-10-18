@extends('layout')
@section('title', $session->title)
@section('content')
{{-- Header --}}
<div class="card mb-4 flex flex-wrap items-center gap-3 p-4">
  <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-violet-600 text-2xl text-white">🧠</span>
  <div class="min-w-0 flex-1">
    <h1 class="truncate font-extrabold">{{ $session->title }}</h1>
    <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px]">
      <b>{{ $session->agent->name }}</b>
      <span class="pill ring-1 {{ provider_badge_class($session->agent->provider) }}">{{ $session->agent->provider }}</span>
      <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px]" dir="ltr">{{ $session->agent->model }}</code>
      @if(count($session->agent->resolvedRagIds())>0)<span class="pill bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">📚 RAG فعال</span>@endif
    </div>
  </div>
  <a href="{{ route('chats.index',['agent_id'=>$session->agent_id]) }}" class="btn btn-ghost !text-xs">← همه گفتگوها</a>
</div>

{{-- Messages --}}
<div id="box" class="card space-y-4 p-4 md:p-6" style="min-height:40vh;max-height:62vh;overflow-y:auto">
  @foreach($session->messages as $m)
    @if($m->role === 'user')
      <div class="msg flex justify-start gap-2">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm text-white">👤</span>
        <div class="max-w-[85%]">
          <div class="rounded-2xl rounded-tr-md bg-blue-600 px-4 py-2.5 text-sm leading-7 text-white shadow-sm" data-md="{{ e($m->content) }}"></div>
          @if($m->attachments)
            <div class="mt-1.5 flex flex-wrap gap-1.5">
              @foreach($m->attachments as $i=>$att)
                <a href="{{ route('files.chat',[$m,$i]) }}" class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2 py-1 text-[11px] text-blue-700 ring-1 ring-blue-200 hover:bg-blue-100">📎 {{ $att['name'] ?? 'فایل' }}</a>
              @endforeach
            </div>
          @endif
          <div class="mt-1 text-[10px] text-slate-400">{{ fa_date($m->created_at) }}</div>
        </div>
      </div>
    @else
      <div class="msg flex justify-start gap-2">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-violet-600 to-blue-600 text-sm text-white">🤖</span>
        <div class="max-w-[90%] flex-1">
          <div class="rounded-2xl rounded-tl-md border bg-slate-50 px-4 py-2.5 text-sm leading-7 text-slate-800" data-md="{{ e($m->content) }}"></div>
          <div class="mt-1 flex items-center gap-2 text-[10px] text-slate-400">
            <span>{{ $session->agent->name }} · {{ fa_date($m->created_at) }}</span>
            <button onclick="copyMsg(this)" class="rounded px-1.5 py-0.5 hover:bg-slate-200" title="کپی پاسخ">📋 کپی</button>
          </div>
        </div>
      </div>
    @endif
  @endforeach

  {{-- live --}}
  <div id="live" class="hidden gap-2">
    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-violet-600 to-blue-600 text-sm text-white">🤖</span>
    <div class="max-w-[90%] flex-1 rounded-2xl rounded-tl-md border bg-slate-50 px-4 py-3">
      <div class="mb-1 flex gap-1"><span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span></div>
      <div id="liveText" class="text-sm leading-7 text-slate-800"></div>
    </div>
  </div>
</div>

{{-- Pending attachments --}}
@if(!empty($session->meta['pending_attachments']))
  <div class="mt-3 flex flex-wrap items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs">
    <b>📎 آماده ارسال ({{ fa_num(count($session->meta['pending_attachments'])) }}):</b>
    @foreach($session->meta['pending_attachments'] as $p)
      <span class="rounded-lg bg-white px-2 py-1 ring-1 ring-amber-200" title="{{ $p['detail'] ?? '' }}">
        {{ $p['name'] ?? '' }} <span class="text-slate-400">({{ fa_bytes($p['size'] ?? 0) }})</span>
        @if(!empty($p['readable']))<span class="text-emerald-600">✓ {{ fa_num($p['chars'] ?? 0) }} حرف</span>
        @else<span class="text-red-500">⚠ {{ $p['detail'] ?? 'ناخوانا' }}</span>@endif
      </span>
    @endforeach
  </div>
@endif

{{-- Composer --}}
<div class="card mt-3 p-3">
  <div class="flex items-end gap-2">
    <form method="POST" action="{{ route('chats.upload',$session) }}" enctype="multipart/form-data" id="upForm">
      @csrf
      <label class="btn btn-ghost !px-3" title="پیوست فایل (Word/Excel/PowerPoint/PDF/عکس/متن)">📎<input type="file" name="files[]" id="fileInp" multiple accept=".txt,.md,.markdown,.csv,.json,.log,.xml,.html,.htm,.pdf,.docx,.xlsx,.xls,.pptx,.png,.jpg,.jpeg,.webp,.gif,.bmp" class="hidden" onchange="document.getElementById('upForm').submit()"></label>
    </form>
    <textarea id="inp" rows="1" placeholder="پیام خود را بنویسید… فایل را هم می‌توانید روی همین کادر رها کنید (Enter = ارسال ⚡ · Shift+Enter = خط جدید)" class="inp max-h-40 flex-1 resize-none !py-2.5"></textarea>
    <button id="sendBtn" onclick="sendStream()" class="btn btn-primary">ارسال ⚡</button>
  </div>
  <div class="mt-1.5 flex items-center justify-between text-[11px] text-slate-400">
    <span>استریم زنده فعال · <span id="charCount">۰</span> کاراکتر</span>
    <form method="POST" action="{{ route('chats.send',$session) }}" class="inline">@csrf<input type="hidden" name="message" id="fbMsg"><button onclick="document.getElementById('fbMsg').value=document.getElementById('inp').value" class="underline hover:text-slate-600">ارسال عادی (بدون استریم)</button></form>
  </div>
</div>

@push('scripts')
<script>
const box = document.getElementById('box');
const inp = document.getElementById('inp');
box.scrollTop = box.scrollHeight;

/* --- tiny safe markdown renderer --- */
function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function renderMD(src){
  let h = esc(src);
  const codes = [];
  h = h.replace(/```(\w*)\n([\s\S]*?)(```|$)/g, (m,l,c)=>{ codes.push(c.replace(/\n$/,'')); return `\u0000CODE${codes.length-1}\u0000`; });
  h = h.replace(/`([^`\n]+)`/g, '<code class="rounded bg-slate-800/90 px-1.5 py-0.5 font-mono text-[12px] text-emerald-200" dir="ltr">$1</code>');
  h = h.replace(/^###### (.*)$/gm,'<b>$1</b>').replace(/^#### (.*)$/gm,'<b class="text-[15px]">$1</b>')
       .replace(/^### (.*)$/gm,'<b class="text-[15px]">$1</b>').replace(/^## (.*)$/gm,'<b>$1</b>');
  h = h.replace(/\*\*([^*]+)\*\*/g,'<b>$1</b>');
  h = h.replace(/^\s*[-*] (.*)$/gm,'<div>• $1</div>').replace(/^\s*(\d+)[.)] (.*)$/gm,'<div><b>$1.</b> $2</div>');
  h = h.replace(/\n/g,'<br>');
  h = h.replace(/\u0000CODE(\d+)\u0000/g, (m,i)=>`<div class="md-code">${esc(codes[+i])}</div>`);
  return h;
}
document.querySelectorAll('[data-md]').forEach(el=>{ el.innerHTML = renderMD(el.getAttribute('data-md')); });
function copyMsg(btn){
  const txt = btn.closest('.msg, div').parentElement.querySelector('[data-md]')?.getAttribute('data-md') || '';
  navigator.clipboard.writeText(txt).then(()=>{ btn.textContent='✓ شد'; setTimeout(()=>btn.textContent='📋 کپی',1200); });
}

/* --- composer --- */
inp.addEventListener('input', ()=>{
  inp.style.height='auto'; inp.style.height=Math.min(inp.scrollHeight,160)+'px';
  document.getElementById('charCount').textContent = Number(inp.value.length).toLocaleString('fa-IR');
});
inp.addEventListener('keydown', e=>{ if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); sendStream(); } });
/* drag & drop files onto the textarea */
['dragover','dragenter'].forEach(e=>inp.addEventListener(e,ev=>{ev.preventDefault();inp.classList.add('!border-blue-500');}));
['dragleave','drop'].forEach(e=>inp.addEventListener(e,ev=>{ev.preventDefault();inp.classList.remove('!border-blue-500');}));
inp.addEventListener('drop', ev=>{
  if(ev.dataTransfer.files.length){
    const dt = new DataTransfer();
    [...ev.dataTransfer.files].forEach(f=>dt.items.add(f));
    document.getElementById('fileInp').files = dt.files;
    document.getElementById('upForm').submit();
  }
});

function addUser(text){
  const d=document.createElement('div');
  d.className='msg flex justify-start gap-2 fade-in';
  d.innerHTML=`<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-600 text-sm text-white">👤</span>
    <div class="max-w-[85%]"><div class="rounded-2xl rounded-tr-md bg-blue-600 px-4 py-2.5 text-sm leading-7 text-white shadow-sm"></div></div>`;
  d.querySelector('div div').innerHTML = renderMD(text);
  box.appendChild(d); box.scrollTop = box.scrollHeight;
}
let es=null;
function sendStream(){
  const text = inp.value.trim();
  if(!text || es) return;
  inp.value=''; inp.style.height='auto';
  document.getElementById('charCount').textContent='۰';
  addUser(text);
  const live=document.getElementById('live'), liveText=document.getElementById('liveText');
  live.classList.remove('hidden'); live.classList.add('flex'); liveText.innerHTML='';
  box.scrollTop = box.scrollHeight;
  const btn=document.getElementById('sendBtn'); btn.disabled=true; btn.classList.add('opacity-50');
  es = new EventSource(`{{ route('chats.stream',$session) }}?message=${encodeURIComponent(text)}`);
  let full='';
  es.onmessage = e=>{
    const data = JSON.parse(e.data);
    if(data.done){ es.close(); es=null; btn.disabled=false; btn.classList.remove('opacity-50');
      setTimeout(()=>location.reload(), 400); return; }
    if(data.text){ full+=data.text; liveText.innerHTML = renderMD(full); box.scrollTop = box.scrollHeight; }
    if(data.error){ full+=`\n⚠️ ${data.error}`; liveText.innerHTML = renderMD(full); }
  };
  es.onerror = ()=>{ es.close(); es=null; btn.disabled=false; btn.classList.remove('opacity-50');
    liveText.innerHTML += '<br>⚠️ <b>ارتباط قطع شد.</b> صفحه را رفرش کنید.'; };
}
</script>
@endpush
@endsection
