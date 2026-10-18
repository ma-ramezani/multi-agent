@extends('layout')
@section('title','گفتگو با '.$session->agent->name)
@section('content')
<div class="flex justify-between items-center mb-3">
<div><h1 class="text-xl font-bold">💬 {{ $session->title }}</h1><div class="text-sm text-gray-500">ایجنت: <b>{{ $session->agent->name }}</b> ({{ $session->agent->provider }}/{{ $session->agent->model }}) · thread: <code dir="ltr">{{ $session->thread_id }}</code></div></div>
<a href="{{ route('chats.index',['agent_id'=>$session->agent_id]) }}" class="text-gray-500">← همه گفتگوها</a>
</div>

<div id="messages" class="bg-white rounded shadow p-4 mb-4 space-y-3 max-h-[55vh] overflow-y-auto">
@foreach($session->messages as $m)
<div class="{{ $m->role=='user'?'flex justify-end':'flex justify-start' }}">
<div class="{{ $m->role=='user'?'chat-bubble-user':'chat-bubble-ai' }} rounded-xl px-4 py-2 max-w-[80%]">
@if($m->attachments)<div class="text-xs opacity-80 mb-1">📎 {{ implode('، ', array_column($m->attachments,'name')) }}</div>@endif
<div class="text-sm" style="white-space:pre-wrap">{{ $m->content }}</div>
</div>
</div>
@endforeach
<div id="live" class="hidden flex justify-start"><div class="chat-bubble-ai rounded-xl px-4 py-2 max-w-[80%]"><div class="text-xs text-gray-400">در حال نوشتن...</div><div id="liveText" class="text-sm" style="white-space:pre-wrap"></div></div></div>
</div>

@if(!empty($session->meta['pending_attachments']))
<div class="bg-yellow-50 border rounded p-2 mb-2 text-sm">📎 فایل‌های آماده ارسال: {{ implode('، ', array_column($session->meta['pending_attachments'],'name')) }}</div>
@endif

<form method="POST" action="{{ route('chats.upload',$session) }}" enctype="multipart/form-data" class="flex gap-2 mb-2">
@csrf
<input type="file" name="file" class="border rounded p-2 flex-1 bg-white">
<button class="bg-gray-700 text-white px-4 py-2 rounded">آپلود فایل در چت</button>
</form>

<div class="flex gap-2">
<input id="msgInput" class="flex-1 border rounded p-3" placeholder="پیام خود را بنویسید... (Enter = ارسال زنده)">
<button onclick="sendStream()" class="bg-blue-600 text-white px-6 py-2 rounded">ارسال ⚡ زنده</button>
</div>
<form method="POST" action="{{ route('chats.send',$session) }}" class="mt-2 text-sm text-gray-500">
@csrf
<input type="hidden" name="message" id="fallbackMsg">
<button onclick="document.getElementById('fallbackMsg').value=document.getElementById('msgInput').value" class="underline">ارسال عادی (بدون استریم)</button>
</form>

<script>
const sessionId = {{ $session->id }};
const box = document.getElementById('messages');
box.scrollTop = box.scrollHeight;
document.getElementById('msgInput').addEventListener('keydown', e=>{ if(e.key==='Enter'){e.preventDefault();sendStream();} });

function addUser(text){
  const d=document.createElement('div');d.className='flex justify-end';
  d.innerHTML='<div class="chat-bubble-user rounded-xl px-4 py-2 max-w-[80%]"><div class="text-sm" style="white-space:pre-wrap"></div></div>';
  d.querySelector('div div').textContent=text;box.appendChild(d);box.scrollTop=box.scrollHeight;
}
function sendStream(){
  const input=document.getElementById('msgInput');
  const text=input.value.trim();if(!text)return;input.value='';
  addUser(text);
  const live=document.getElementById('live');const liveText=document.getElementById('liveText');
  live.classList.remove('hidden');liveText.textContent='';box.scrollTop=box.scrollHeight;
  const es=new EventSource(`/chats/${sessionId}/stream?message=${encodeURIComponent(text)}`);
  es.onmessage=e=>{
    const data=JSON.parse(e.data);
    if(data.done){es.close();location.reload();return;}
    if(data.text){liveText.textContent+=data.text;box.scrollTop=box.scrollHeight;}
  };
  es.onerror=()=>{es.close();liveText.textContent+='\n[قطع ارتباط]';};
}
</script>
@endsection
