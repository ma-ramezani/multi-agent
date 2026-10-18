@extends('layout')
@section('title', ($agent->exists?'ویرایش ایجنت':'ایجنت جدید'))
@section('content')
<h1 class="text-2xl font-bold mb-4">{{ $agent->exists?'ویرایش ایجنت: '.$agent->name:'ایجنت جدید' }}</h1>
<form method="POST" action="{{ $agent->exists?route('agents.update',$agent):route('agents.store') }}" class="bg-white rounded shadow p-6 grid grid-cols-2 gap-4">
@csrf @if($agent->exists)@method('PUT')@endif
<div><label class="text-sm">نام *</label><input name="name" value="{{ old('name',$agent->name) }}" class="w-full border rounded p-2" required></div>
<div><label class="text-sm">slug (اختیاری، یکتا)</label><input name="slug" value="{{ old('slug',$agent->slug) }}" class="w-full border rounded p-2" dir="ltr"></div>
<div class="col-span-2"><label class="text-sm">توضیح</label><input name="description" value="{{ old('description',$agent->description) }}" class="w-full border rounded p-2"></div>
<div><label class="text-sm">پرووایدر *</label><select name="provider" class="w-full border rounded p-2">@foreach(\App\Models\Agent::providers() as $k=>$v)<option value="{{ $k }}" {{ old('provider',$agent->provider)==$k?'selected':'' }}>{{ $v }}</option>@endforeach</select></div>
<div><label class="text-sm">مدل *</label><input name="model" value="{{ old('model',$agent->model) }}" class="w-full border rounded p-2" dir="ltr" placeholder="gpt-4o-mini / claude-3-5-sonnet / llama3.1 ..."></div>
<div class="col-span-2 bg-sky-50 border border-sky-200 rounded p-3 text-sm">
<b>مدل‌های پیشنهادی OpenCode Go</b> (کلید را در <code dir="ltr">OPENCODE_GO_API_KEY</code> بگذارید):
<span dir="ltr">longcat-2.5-preview-free (رایگان)</span> ·
<span dir="ltr">mimo-v2.6-flash</span> ·
<span dir="ltr">muse-spark-1.3-contributor</span> ·
<span dir="ltr">glm-5.3-flash</span> ·
<span dir="ltr">deepseek-v4.1-flash</span> ·
<span dir="ltr">qwen3.8-flash</span>
</div>
<div><label class="text-sm">API Key (خالی = از env خوانده می‌شود)</label><input name="api_key" value="" type="password" class="w-full border rounded p-2" dir="ltr" placeholder="{{ $agent->exists&&$agent->api_key?'••• ذخیره شده •••':'' }}"></div>
<div><label class="text-sm">Base URL (برای Ollama / OpenAI-Like)</label><input name="base_url" value="{{ old('base_url',$agent->base_url) }}" class="w-full border rounded p-2" dir="ltr" placeholder="http://localhost:11434/api"></div>
<div class="col-span-2"><label class="text-sm">System Prompt (دستورالعمل ایجنت)</label><textarea name="system_prompt" rows="4" class="w-full border rounded p-2">{{ old('system_prompt',$agent->system_prompt) }}</textarea></div>
<div><label class="text-sm">Temperature (0-2)</label><input name="temperature" type="number" step="0.1" min="0" max="2" value="{{ old('temperature',$agent->temperature) }}" class="w-full border rounded p-2" dir="ltr"></div>
<div><label class="text-sm">Max Tokens</label><input name="max_tokens" type="number" value="{{ old('max_tokens',$agent->max_tokens) }}" class="w-full border rounded p-2" dir="ltr"></div>
<div><label class="text-sm">Context Window</label><input name="context_window" type="number" value="{{ old('context_window',$agent->context_window??128000) }}" class="w-full border rounded p-2" dir="ltr"></div>
<div><label class="text-sm">Max Tool Runs / RAG TopK</label><div class="flex gap-2"><input name="max_tool_runs" type="number" min="1" max="50" value="{{ old('max_tool_runs',$agent->max_tool_runs??10) }}" class="w-1/2 border rounded p-2" dir="ltr"><input name="rag_top_k" type="number" min="1" max="20" value="{{ old('rag_top_k',$agent->rag_top_k??4) }}" class="w-1/2 border rounded p-2" dir="ltr"></div></div>
<div class="col-span-2 border rounded p-3"><div class="font-bold mb-2">🛠 ابزارهایی که ایجنت به آن‌ها دسترسی دارد:</div>
<div class="grid grid-cols-2 gap-2">@foreach(\App\Neuron\ToolRegistry::AVAILABLE as $k=>$info)<label class="flex gap-2 items-start border rounded p-2"><input type="checkbox" name="tools[]" value="{{ $k }}" {{ in_array($k, old('tools',$agent->resolvedTools()))?'checked':'' }} class="mt-1"><span><b>{{ $info['label'] }}</b><br><span class="text-xs text-gray-500">{{ $info['desc'] }}</span></span></label>@endforeach</div></div>
<div class="col-span-2 border rounded p-3"><div class="font-bold mb-2">📚 محیط‌های RAG که این ایجنت به آن‌ها دسترسی دارد:</div>
@if($envs->isEmpty())<p class="text-sm text-gray-500">هنوز محیطی نیست. <a class="text-blue-600" href="{{ route('rags.create') }}">ساخت محیط RAG</a></p>@endif
<div class="grid grid-cols-2 gap-2">@foreach($envs as $e)<label class="flex gap-2 items-center border rounded p-2"><input type="checkbox" name="rag_environment_ids[]" value="{{ $e->id }}" {{ in_array($e->id, old('rag_environment_ids',$agent->resolvedRagIds()))?'checked':'' }}><span><b>{{ $e->name }}</b> <span class="text-xs text-gray-500">({{ $e->slug }})</span></span></label>@endforeach</div></div>
<div><label class="flex gap-2 items-center"><input type="checkbox" name="is_active" value="1" {{ old('is_active',$agent->is_active??true)?'checked':'' }}> فعال</label></div>
<div class="col-span-2"><button class="bg-blue-600 text-white px-6 py-2 rounded">ذخیره</button> <a href="{{ route('agents.index') }}" class="text-gray-500">انصراف</a></div>
</form>
@endsection
