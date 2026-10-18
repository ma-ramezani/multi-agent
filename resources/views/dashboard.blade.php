@extends('layout')
@section('title','داشبورد')
@section('content')
<h1 class="text-2xl font-bold mb-4">داشبورد</h1>
<div class="grid grid-cols-3 gap-4 mb-6">
  <div class="bg-white rounded shadow p-4"><div class="text-sm text-gray-500">ایجنت‌ها</div><div class="text-3xl font-bold">{{ $agents }}</div><a href="{{ route('agents.index') }}" class="text-blue-600 text-sm">مدیریت →</a></div>
  <div class="bg-white rounded shadow p-4"><div class="text-sm text-gray-500">محیط‌های RAG</div><div class="text-3xl font-bold">{{ $envs }}</div><a href="{{ route('rags.index') }}" class="text-blue-600 text-sm">مدیریت →</a></div>
  <div class="bg-white rounded shadow p-4"><div class="text-sm text-gray-500">گفتگوها</div><div class="text-3xl font-bold">{{ $sessions }}</div><a href="{{ route('chats.index') }}" class="text-blue-600 text-sm">چت زنده →</a></div>
</div>
<div class="bg-white rounded shadow p-4 mb-6">
  <h2 class="font-bold mb-3">شروع گفتگوی سریع</h2>
  <div class="flex flex-wrap gap-2">
  @foreach($agentsList as $a)
    <form method="POST" action="{{ route('chats.start', $a) }}">@csrf<button class="bg-blue-600 text-white px-4 py-2 rounded">💬 {{ $a->name }} ({{ $a->provider }}/{{ $a->model }})</button></form>
  @endforeach
  @if($agentsList->isEmpty())<p class="text-gray-500">هنوز ایجنتی نساخته‌اید. <a class="text-blue-600" href="{{ route('agents.create') }}">ساخت ایجنت</a></p>@endif
  </div>
</div>
<div class="bg-white rounded shadow p-4">
  <h2 class="font-bold mb-3">گفتگوهای اخیر</h2>
  <table class="w-full text-sm"><tr class="text-gray-500 border-b"><td class="py-2">عنوان</td><td>ایجنت</td><td>تاریخ</td><td></td></tr>
  @foreach($recentSessions as $s)
  <tr class="border-b"><td>{{ $s->title }}</td><td>{{ $s->agent->name ?? '-' }}</td><td>{{ $s->created_at }}</td><td><a class="text-blue-600" href="{{ route('chats.show',$s) }}">باز کردن</a></td></tr>
  @endforeach
  </table>
</div>
@endsection
