@extends('layout')
@section('title','محیط‌های RAG')
@section('content')
<div class="flex justify-between items-center mb-4">
<h1 class="text-2xl font-bold">محیط‌های RAG (دانش)</h1>
<a href="{{ route('rags.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded">＋ محیط جدید</a>
</div>
<div class="grid gap-4">
@foreach($envs as $e)
<div class="bg-white rounded shadow p-4 flex justify-between">
<div><b class="text-lg">{{ $e->name }}</b> <span class="text-xs text-gray-500">{{ $e->slug }}</span>
<div class="text-sm text-gray-600">{{ $e->description }}</div>
<div class="text-xs mt-1" dir="ltr">{{ $e->embedding_provider }} / {{ $e->embedding_model }} · chunk {{ $e->chunk_size }}/{{ $e->chunk_overlap }} · topK {{ $e->top_k }}</div>
<div class="text-xs">📄 {{ $e->documents_count }} سند · 🧩 {{ $e->chunks_count }} چانک</div></div>
<div class="flex flex-col gap-2"><a href="{{ route('rags.show',$e) }}" class="bg-green-600 text-white px-4 py-1 rounded text-center">مدیریت اسناد</a><a href="{{ route('rags.edit',$e) }}" class="text-blue-600 text-sm text-center">ویرایش تنظیمات</a>
<form method="POST" action="{{ route('rags.destroy',$e) }}" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE')<button class="text-red-600 text-sm">حذف</button></form></div>
</div>
@endforeach
@if($envs->isEmpty())<p class="text-gray-500">هنوز محیطی ساخته نشده.</p>@endif
</div>
@endsection
