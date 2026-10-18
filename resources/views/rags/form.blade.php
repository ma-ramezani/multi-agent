@extends('layout')
@section('title', ($env->exists?'ویرایش محیط':'محیط جدید'))
@section('content')
<h1 class="text-2xl font-bold mb-4">{{ $env->exists?'ویرایش محیط RAG':'محیط RAG جدید' }}</h1>
<form method="POST" action="{{ $env->exists?route('rags.update',$env):route('rags.store') }}" class="bg-white rounded shadow p-6 grid grid-cols-2 gap-4">
@csrf @if($env->exists)@method('PUT')@endif
<div><label class="text-sm">نام *</label><input name="name" value="{{ old('name',$env->name) }}" class="w-full border rounded p-2" required></div>
<div><label class="text-sm">slug</label><input name="slug" value="{{ old('slug',$env->slug) }}" class="w-full border rounded p-2" dir="ltr"></div>
<div class="col-span-2"><label class="text-sm">توضیح</label><input name="description" value="{{ old('description',$env->description) }}" class="w-full border rounded p-2"></div>
<div><label class="text-sm">Embedding Provider</label><select name="embedding_provider" class="w-full border rounded p-2">@foreach(\App\Models\RagEnvironment::embeddingProviders() as $k=>$v)<option value="{{ $k }}" {{ old('embedding_provider',$env->embedding_provider)==$k?'selected':'' }}>{{ $v }}</option>@endforeach</select></div>
<div><label class="text-sm">Embedding Model</label><input name="embedding_model" value="{{ old('embedding_model',$env->embedding_model) }}" class="w-full border rounded p-2" dir="ltr"></div>
<div><label class="text-sm">Embedding API Key (خالی = از env)</label><input name="embedding_api_key" type="password" class="w-full border rounded p-2" dir="ltr"></div>
<div><label class="text-sm">Embedding Base URL</label><input name="embedding_base_url" value="{{ old('embedding_base_url',$env->embedding_base_url) }}" class="w-full border rounded p-2" dir="ltr"></div>
<div><label class="text-sm">Chunk Size</label><input name="chunk_size" type="number" min="200" max="8000" value="{{ old('chunk_size',$env->chunk_size) }}" class="w-full border rounded p-2" dir="ltr"></div>
<div><label class="text-sm">Chunk Overlap</label><input name="chunk_overlap" type="number" min="0" max="1000" value="{{ old('chunk_overlap',$env->chunk_overlap) }}" class="w-full border rounded p-2" dir="ltr"></div>
<div><label class="text-sm">Top K (تعداد چانک بازیابی)</label><input name="top_k" type="number" min="1" max="20" value="{{ old('top_k',$env->top_k) }}" class="w-full border rounded p-2" dir="ltr"></div>
<div><label class="flex gap-2 items-center"><input type="checkbox" name="is_active" value="1" {{ old('is_active',$env->is_active??true)?'checked':'' }}> فعال</label></div>
<div class="col-span-2"><button class="bg-blue-600 text-white px-6 py-2 rounded">ذخیره</button></div>
</form>
@endsection
