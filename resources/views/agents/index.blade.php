@extends('layout')
@section('title','ایجنت‌ها')
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h1 class="text-2xl font-extrabold">{!! svg_icon('brain','w-4 h-4 inline-block -mt-0.5') !!}  ایجنت‌ها</h1>
    <p class="mt-1 text-sm text-slate-500">{{ fa_num($agents->count()) }} ایجنت تعریف شده · هر ایجنت پرووایدر، ابزار و محیط‌های RAG خودش را دارد</p>
  </div>
  <a href="{{ route('agents.create') }}" class="btn btn-primary">＋ ایجنت جدید</a>
</div>

@if($agents->isEmpty())
  <div class="card p-10 text-center">
    <div class="text-5xl">{!! svg_icon('brain','w-4 h-4 inline-block -mt-0.5') !!} </div>
    <h2 class="mt-3 font-extrabold">هنوز ایجنتی نساخته‌اید</h2>
    <p class="mt-1 text-sm text-slate-500">پرووایدر را انتخاب کنید، ابزارها را تیک بزنید و محیط RAG وصل کنید.</p>
    <a href="{{ route('agents.create') }}" class="btn btn-primary mt-4">ساخت اولین ایجنت</a>
  </div>
@else
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
@foreach($agents as $a)
  <div class="card card-hover flex flex-col p-5">
    <div class="flex items-start justify-between gap-2">
      <div>
        <h2 class="font-extrabold">{{ $a->name }}</h2>
        <div class="mt-0.5 font-mono text-[11px] text-slate-400" dir="ltr">{{ $a->slug }}</div>
      </div>
      @if($a->is_active)
        <span class="pill bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">● فعال</span>
      @else
        <span class="pill bg-slate-100 text-slate-500 ring-1 ring-slate-200">○ غیرفعال</span>
      @endif
    </div>
    @if($a->description)<p class="mt-2 text-xs leading-5 text-slate-500">{{ $a->description }}</p>@endif
    <div class="mt-3 flex flex-wrap items-center gap-1.5">
      <span class="pill ring-1 {{ provider_badge_class($a->provider) }}">{{ \App\Models\Agent::providers()[$a->provider] ?? $a->provider }}</span>
      <code class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[11px] text-slate-600" dir="ltr">{{ $a->model }}</code>
      @if($a->temperature !== null)<span class="pill bg-slate-50 text-slate-500 ring-1 ring-slate-200" title="Temperature">{!! svg_icon('gauge','w-4 h-4 inline-block -mt-0.5') !!}  {{ $a->temperature }}</span>@endif
    </div>
    <div class="mt-2 flex flex-wrap gap-1.5 text-[11px]">
      <span class="font-semibold text-slate-500">{!! svg_icon('settings','w-4 h-4 inline-block -mt-0.5') !!}  {{ fa_num(count($a->resolvedTools())) }} ابزار:</span>
      @forelse($a->resolvedTools() as $t)
        <span class="rounded bg-sky-50 px-2 py-0.5 text-sky-700">{{ \App\Neuron\ToolRegistry::AVAILABLE[$t]['label'] ?? $t }}</span>
      @empty
        <span class="text-slate-400">—</span>
      @endforelse
    </div>
    <div class="mt-1.5 flex flex-wrap gap-1.5 text-[11px]">
      <span class="font-semibold text-slate-500">{!! svg_icon('book','w-4 h-4 inline-block -mt-0.5') !!}  {{ fa_num(count($a->resolvedRagIds())) }} محیط RAG</span>
      <span class="text-slate-400">· {!! svg_icon('settings','w-4 h-4 inline-block -mt-0.5') !!}  {{ fa_num($a->mcp_servers_count ?? 0) }} MCP</span>
      <span class="text-slate-400">· {!! svg_icon('chat','w-4 h-4 inline-block -mt-0.5') !!}  {{ fa_num($a->chatSessions_count) }} گفتگو</span>
    </div>
    <div class="mt-4 flex flex-wrap gap-2 border-t pt-3">
      <form method="POST" action="{{ route('chats.start',$a) }}">@csrf<button class="btn btn-green !py-1.5 !text-xs">{!! svg_icon('chat','w-4 h-4 inline-block -mt-0.5') !!}  چت</button></form>
      <a href="{{ route('agents.edit',$a) }}" class="btn btn-ghost !py-1.5 !text-xs">{!! svg_icon('edit','w-4 h-4 inline-block -mt-0.5') !!}  ویرایش</a>
      <form method="POST" action="{{ route('agents.destroy',$a) }}" class="mr-auto" onsubmit="return confirm('ایجنت «{{ $a->name }}» و همه گفتگوهایش حذف شود؟')">@csrf @method('DELETE')<button class="btn btn-danger-ghost !py-1.5 !text-xs">{!! svg_icon('trash','w-4 h-4 inline-block -mt-0.5') !!}  حذف</button></form>
    </div>
  </div>
@endforeach
</div>
@endif
@endsection
