@extends('layout')
@section('title','مستندات API')
@section('content')
<h1 class="text-2xl font-bold mb-4">مستندات API ساده</h1>
<div class="bg-white rounded shadow p-6 space-y-4 text-sm" dir="ltr" style="text-align:left">
<p dir="rtl" class="text-right">نرم‌افزارهای دیگر با توکن (ساخته‌شده در بخش توکن‌ها) می‌توانند به ایجنت‌ها وصل شوند:</p>

<h3 class="font-bold">1) لیست ایجنت‌ها</h3>
<pre class="bg-gray-900 text-green-300 rounded p-3">GET /api/v1/agents
Authorization: Bearer YOUR_TOKEN</pre>

<h3 class="font-bold">2) چت با ایجنت (با slug ایجنت)</h3>
<pre class="bg-gray-900 text-green-300 rounded p-3">POST /api/v1/agents/{slug}/chat
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "message": "سلام، محصول X چیست؟",
  "thread_id": "optional-thread-to-continue",
  "stream": false
}</pre>
<p>پاسخ:</p>
<pre class="bg-gray-900 text-green-300 rounded p-3">{"thread_id": "th_...", "answer": "..."}</pre>

<h3 class="font-bold">3) چت استریم (SSE)</h3>
<pre class="bg-gray-900 text-green-300 rounded p-3">POST /api/v1/agents/{slug}/chat
{"message": "سلام", "stream": true}
// events: data: {"text": "..."} ... data: {"done": true}</pre>

<h3 class="font-bold">4) تاریخچه</h3>
<pre class="bg-gray-900 text-green-300 rounded p-3">GET /api/v1/agents/{slug}/history?thread_id=th_...
Authorization: Bearer YOUR_TOKEN</pre>

<h3 class="font-bold">مثال curl</h3>
<pre class="bg-gray-900 text-green-300 rounded p-3">curl -X POST http://localhost:8000/api/v1/agents/my-agent/chat \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"message":"سلام"}'</pre>

<h3 class="font-bold">مثال PHP</h3>
<pre class="bg-gray-900 text-green-300 rounded p-3">$ch = curl_init("http://localhost:8000/api/v1/agents/my-agent/chat");
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => ["Authorization: Bearer YOUR_TOKEN","Content-Type: application/json"],
  CURLOPT_POSTFIELDS => json_encode(["message" => "سلام"]),
]);
echo curl_exec($ch);</pre>
</div>
@endsection
