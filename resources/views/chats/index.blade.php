@extends('layout')
@section('title','چت زنده')
@section('content')
<h1 class="text-2xl font-bold mb-4">💬 چت زنده</h1>
<form method="GET" action="{{ route('chats.index') }}" class="mb-4 flex gap-2">
<select name="agent_id" class="border rounded p-2" onchange="this.form.submit()">
@foreach($agents as $a)<option value="{{ $a->id }}" {{ $selectedAgent && $selectedAgent->id==$a->id?'selected':'' }}>{{ $a->name }} ({{ $a->provider }}/{{ $a->model }})</option>@endforeach
</select>
</form>
@if($selectedAgent)
<form method="POST" action="{{ route('chats.start',$selectedAgent) }}" class="mb-4">@csrf<button class="bg-green-600 text-white px-4 py-2 rounded">＋ گفتگوی جدید با {{ $selectedAgent->name }}</button></form>
<div class="bg-white rounded shadow divide-y">
@foreach($sessions as $s)
<div class="p-3 flex justify-between"><div><a href="{{ route('chats.show',$s) }}" class="font-bold text-blue-700">{{ $s->title }}</a><div class="text-xs text-gray-500">{{ $s->created_at }} · {{ $s->messages_count ?? $s->messages()->count() }} پیام</div></div>
<form method="POST" action="{{ route('chats.destroy',$s) }}" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE')<button class="text-red-500 text-sm">حذف</button></form></div>
@endforeach
@if($sessions->isEmpty())<p class="p-4 text-gray-500">گفتگویی نیست.</p>@endif
</div>
@else
<p>ایجنتی وجود ندارد. <a href="{{ route('agents.create') }}" class="text-blue-600">بسازید</a></p>
@endif
@endsection
