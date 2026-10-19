@extends('layout')
@section('title','مستندات API')
@section('content')
<div class="mb-5">
  <h1 class="text-2xl font-extrabold">{!! svg_icon('doc','w-4 h-4 inline-block -mt-0.5') !!}  مستندات API</h1>
  <p class="mt-1 text-sm text-slate-500">اتصال نرم‌افزارهای دیگر به ایجنت‌ها با توکن Bearer</p>
</div>

<div class="grid grid-cols-1 gap-4">
@php
$steps = [
  ['t'=>'۱ · لیست ایجنت‌ها','req'=>"GET /api/v1/agents\nAuthorization: Bearer TOKEN",'res'=>'[{"id":1,"name":"پشتیبانی","slug":"support",...}]'],
  ['t'=>'۲ · چت (thread_id اختیاری برای ادامه گفتگو)','req'=>"POST /api/v1/agents/{slug}/chat\nAuthorization: Bearer TOKEN\nContent-Type: application/json\n\n{\n  \"message\": \"سلام، پلن‌ها چیست؟\",\n  \"thread_id\": \"th_... (optional)\",\n  \"stream\": false\n}",'res'=>"{\n  \"thread_id\": \"th_...\",\n  \"answer\": \"...\"\n}"],
  ['t'=>'۳ · چت استریم (SSE)','req'=>"POST /api/v1/agents/{slug}/chat\n{\n  \"message\": \"سلام\",\n  \"stream\": true\n}\n\nevents → data: {\"text\": \"...\"} … data: {\"done\": true}",'res'=>'Server-Sent Events'],
  ['t'=>'۴ · تاریخچه گفتگو','req'=>"GET /api/v1/agents/{slug}/history?thread_id=th_...\nAuthorization: Bearer TOKEN",'res'=>'sessions + messages'],
];
@endphp
@foreach($steps as $s)
  <div class="card overflow-hidden">
    <div class="border-b bg-slate-50/70 px-5 py-2.5 text-sm font-bold">{{ $s['t'] }}</div>
    <div class="grid gap-0 md:grid-cols-2" dir="ltr" style="text-align:left">
      <div class="border-b md:border-b-0 md:border-r"><div class="px-4 pt-2 text-[11px] font-bold text-slate-400">REQUEST</div><pre class="md-code !bg-transparent !text-slate-800">{{ $s['req'] }}</pre></div>
      <div class="bg-slate-900"><div class="px-4 pt-2 text-[11px] font-bold text-slate-500">RESPONSE</div><pre class="md-code !bg-transparent">{{ $s['res'] }}</pre></div>
    </div>
  </div>
@endforeach

  <div class="card overflow-hidden">
    <div class="border-b bg-slate-50/70 px-5 py-2.5 text-sm font-bold">{!! svg_icon('info','w-4 h-4 inline-block -mt-0.5') !!}  مثال curl</div>
    <div dir="ltr" style="text-align:left"><pre class="md-code !m-4">curl -X POST {{ rtrim(config('app.url'),' /') }}/api/v1/agents/general-assistant/chat \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"message":"سلام"}'</pre></div>
  </div>

  <div class="card overflow-hidden">
    <div class="border-b bg-slate-50/70 px-5 py-2.5 text-sm font-bold">{!! svg_icon('info','w-4 h-4 inline-block -mt-0.5') !!}  مثال PHP</div>
    <div dir="ltr" style="text-align:left"><pre class="md-code !m-4">$ch = curl_init("{{ rtrim(config('app.url'),' /') }}/api/v1/agents/general-assistant/chat");
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ["Authorization: Bearer TOKEN", "Content-Type: application/json"],
  CURLOPT_POSTFIELDS => json_encode(["message" => "سلام", "thread_id" => $threadId]),
]);
$answer = json_decode(curl_exec($ch), true)["answer"] ?? null;</pre></div>
  </div>
</div>
@endsection
