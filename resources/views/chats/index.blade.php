@extends('layout')
@section('title','چت زنده')
@section('content')
<div class="mb-5">
  <h1 class="text-2xl font-extrabold">{!! svg_icon('chat','w-4 h-4 inline-block -mt-0.5') !!}  چت زنده</h1>
  <p class="mt-1 text-sm text-slate-500">گفتگو با استریم زنده (SSE) · امکان پیوست فایل در هر پیام</p>
</div>

<div class="card mb-4 p-4">
  <form method="GET" action="{{ route('chats.index') }}" class="flex flex-wrap items-end gap-3">
    <div class="min-w-64 flex-1">
      <label class="lbl">ایجنت</label>
      <select name="agent_id" class="inp" onchange="this.form.submit()">
        @foreach($agents as $a)
          <option value="{{ $a->id }}" {{ $selectedAgent && $selectedAgent->id==$a->id?'selected':'' }}>{{ $a->name }} — {{ $a->provider }}/{{ $a->model }}</option>
        @endforeach
      </select>
    </div>
    @if($selectedAgent)
      <form method="POST" action="{{ route('chats.start',$selectedAgent) }}">@csrf
        <button class="btn btn-green">＋ گفتگوی جدید</button>
      </form>
    @endif
  </form>
</div>

@if(!$selectedAgent)
  <div class="card p-10 text-center text-sm text-slate-500">ایجنتی وجود ندارد. <a href="{{ route('agents.create') }}" class="font-semibold text-blue-600">بسازید</a></div>
@elseif($sessions->isEmpty())
  <div class="card p-10 text-center">
    <div class="text-4xl">{!! svg_icon('chat','w-4 h-4 inline-block -mt-0.5') !!} </div>
    <p class="mt-2 text-sm text-slate-500">با {{ $selectedAgent->name }} هنوز گفتگویی نشده است.</p>
    <form method="POST" action="{{ route('chats.start',$selectedAgent) }}" class="mt-3">@csrf<button class="btn btn-primary">شروع اولین گفتگو</button></form>
  </div>
@else
  <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
  @foreach($sessions as $s)
    <a href="{{ route('chats.show',$s) }}" class="card card-hover p-4">
      <div class="flex items-start justify-between gap-2">
        <b class="text-sm">{{ $s->title }}</b>
        <span class="whitespace-nowrap text-[11px] text-slate-400">{{ fa_date($s->updated_at) }}</span>
      </div>
      <div class="mt-2 text-[11px] text-slate-500">{!! svg_icon('chat','w-4 h-4 inline-block -mt-0.5') !!}  {{ fa_num($s->messages()->count()) }} پیام · thread: <code dir="ltr">{{ mb_substr($s->thread_id,0,14) }}…</code></div>
    </a>
  @endforeach
  </div>
@endif
@endsection
