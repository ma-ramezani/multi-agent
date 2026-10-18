@extends('layout')
@section('title','محیط '.$env->name)
@section('content')
<div class="flex justify-between items-center mb-4">
<h1 class="text-2xl font-bold">📚 {{ $env->name }}</h1>
<a href="{{ route('rags.index') }}" class="text-gray-500">← بازگشت</a>
</div>
<div class="bg-white rounded shadow p-4 mb-4">
<h2 class="font-bold mb-2">افزودن دانش</h2>
<form method="POST" action="{{ route('rags.upload',$env) }}" enctype="multipart/form-data" class="grid gap-3">
@csrf
<div><label class="text-sm">آپلود فایل (txt, md, csv, json — چندتایی مجاز)</label><input type="file" name="files[]" multiple class="w-full border rounded p-2"></div>
<div><label class="text-sm">یا متن دستی</label><input name="raw_name" placeholder="عنوان متن" class="w-full border rounded p-2 mb-1"><textarea name="raw_text" rows="4" placeholder="متن دانش را اینجا بچسبانید..." class="w-full border rounded p-2"></textarea></div>
<button class="bg-green-600 text-white px-6 py-2 rounded w-fit">افزودن و ایندکس</button>
</form>
<p class="text-xs text-gray-500 mt-2">اگر کلید Embedding تنظیم نشده باشد، سیستم به‌صورت خودکار از جستجوی کلیدواژه‌ای استفاده می‌کند (بدون نیاز به API).</p>
</div>
<div class="bg-white rounded shadow p-4">
<h2 class="font-bold mb-2">اسناد ({{ $env->documents->count() }})</h2>
<table class="w-full text-sm"><tr class="text-gray-500 border-b"><td class="py-2">نام</td><td>وضعیت</td><td>چانک</td><td>خطا</td><td></td></tr>
@foreach($env->documents as $d)
<tr class="border-b"><td>{{ $d->original_name }}<div class="text-xs text-gray-400">{{ $d->mime }} · {{ number_format($d->size) }} بایت</div></td>
<td>@if($d->status=='ready')<span class="text-green-600">✅ آماده</span>@elseif($d->status=='processing')<span class="text-yellow-600">⏳...</span>@else<span class="text-red-600">❌ {{ $d->status }}</span>@endif</td>
<td>{{ $d->chunks_count }}</td><td class="text-xs text-red-500">{{ $d->error }}</td>
<td class="whitespace-nowrap"><form method="POST" action="{{ route('rags.reindex',[$env,$d]) }}" class="inline">@csrf<button class="text-blue-600">ایندکس مجدد</button></form> |
<form method="POST" action="{{ route('rags.docs.destroy',[$env,$d]) }}" class="inline" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE')<button class="text-red-600">حذف</button></form></td></tr>
@endforeach
</table>
</div>
@endsection
