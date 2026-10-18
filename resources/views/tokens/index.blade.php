@extends('layout')
@section('title','توکن‌های API')
@section('content')
<h1 class="text-2xl font-bold mb-4">🔑 توکن‌های API</h1>
<div class="bg-white rounded shadow p-4 mb-4">
<h2 class="font-bold mb-2">ساخت توکن جدید</h2>
<form method="POST" action="{{ route('tokens.store') }}" class="flex flex-wrap gap-2 items-end">
@csrf
<div><label class="text-sm">نام</label><input name="name" class="border rounded p-2" required></div>
<div><label class="text-sm">محدود به ایجنت‌ها (خالی = همه)</label><div class="flex gap-2">@foreach($agents as $a)<label class="text-sm border rounded p-1"><input type="checkbox" name="agent_ids[]" value="{{ $a->id }}"> {{ $a->name }}</label>@endforeach</div></div>
<button class="bg-blue-600 text-white px-4 py-2 rounded">ساخت</button>
</form>
</div>
<div class="bg-white rounded shadow p-4">
<table class="w-full text-sm"><tr class="text-gray-500 border-b"><td>نام</td><td>پیشوند</td><td>دسترسی</td><td>آخرین استفاده</td><td></td></tr>
@foreach($tokens as $t)
<tr class="border-b"><td class="py-2">{{ $t->name }}</td><td dir="ltr">{{ $t->plain_prefix }}...</td><td>{{ empty($t->agent_ids)?'همه ایجنت‌ها':count($t->agent_ids).' ایجنت' }}</td><td>{{ $t->last_used_at ?? '—' }}</td>
<td><form method="POST" action="{{ route('tokens.destroy',$t) }}" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE')<button class="text-red-600">حذف</button></form></td></tr>
@endforeach
</table>
</div>
@endsection
