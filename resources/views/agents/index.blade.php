@extends('layout')
@section('title','ایجنت‌ها')
@section('content')
<div class="flex justify-between items-center mb-4">
<h1 class="text-2xl font-bold">ایجنت‌ها</h1>
<a href="{{ route('agents.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded">＋ ایجنت جدید</a>
</div>
<div class="bg-white rounded shadow overflow-hidden">
<table class="w-full text-sm">
<tr class="bg-gray-100 text-gray-600"><td class="p-3">نام</td><td>پرووایدر / مدل</td><td>ابزارها</td><td>RAG</td><td>وضعیت</td><td></td></tr>
@foreach($agents as $a)
<tr class="border-t">
<td class="p-3 font-bold">{{ $a->name }}<div class="text-xs text-gray-500 font-normal">{{ $a->slug }} · {{ $a->chatSessions_count }} گفتگو</div><div class="text-xs text-gray-400">{{ $a->description }}</div></td>
<td class="p-3" dir="ltr">{{ $a->provider }}<br>{{ $a->model }}</td>
<td class="p-3">{{ implode('، ', array_map(fn($t)=>\App\Neuron\ToolRegistry::AVAILABLE[$t]['label']??$t, $a->resolvedTools())) ?: '—' }}</td>
<td class="p-3">{{ count($a->resolvedRagIds()) }} محیط</td>
<td class="p-3">{{ $a->is_active?'✅ فعال':'⏸ غیرفعال' }}</td>
<td class="p-3 whitespace-nowrap">
<form method="POST" action="{{ route('chats.start',$a) }}" class="inline">@csrf<button class="text-green-600">چت</button></form> |
<a class="text-blue-600" href="{{ route('agents.edit',$a) }}">ویرایش</a> |
<form method="POST" action="{{ route('agents.destroy',$a) }}" class="inline" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE')<button class="text-red-600">حذف</button></form>
</td>
</tr>
@endforeach
</table>
</div>
@endsection
