@extends('layout')
@section('title','داشبورد')
@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="text-2xl font-extrabold">داشبورد </h1>
    <p class="mt-1 text-sm text-slate-500">نمای کلی پلتفرم ایجنت‌ها · امروز {{ fa_date(now(), false) }}</p>
  </div>
  <a href="{{ route('agents.create') }}" class="btn btn-primary">＋ ایجنت جدید</a>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <a href="{{ route('agents.index') }}" class="card card-hover p-5">
    <div class="flex items-center justify-between"><span class="text-3xl">{!! svg_icon('brain','w-4 h-4 inline-block -mt-0.5') !!} </span><span class="text-3xl font-extrabold">{{ fa_num($agents) }}</span></div>
    <div class="mt-2 font-bold">ایجنت‌ها</div><div class="text-xs text-slate-500">مدیریت پرووایدر، ابزار و RAG ←</div>
  </a>
  <a href="{{ route('rags.index') }}" class="card card-hover p-5">
    <div class="flex items-center justify-between"><span class="text-3xl">{!! svg_icon('book','w-4 h-4 inline-block -mt-0.5') !!} </span><span class="text-3xl font-extrabold">{{ fa_num($envs) }}</span></div>
    <div class="mt-2 font-bold">محیط‌های دانش (RAG)</div><div class="text-xs text-slate-500">اسناد و چانک‌های ایندکس‌شده ←</div>
  </a>
  <a href="{{ route('mcps.index') }}" class="card card-hover p-5">
    <div class="flex items-center justify-between"><span class="text-3xl">{!! svg_icon('settings','w-4 h-4 inline-block -mt-0.5') !!} </span><span class="text-3xl font-extrabold">{{ fa_num($mcps ?? 0) }}</span></div>
    <div class="mt-2 font-bold">سرورهای MCP</div><div class="text-xs text-slate-500">اتصال http به ابزارهای خارجی ←</div>
  </a>
  <a href="{{ route('chats.index') }}" class="card card-hover p-5">
    <div class="flex items-center justify-between"><span class="text-3xl">{!! svg_icon('chat','w-4 h-4 inline-block -mt-0.5') !!} </span><span class="text-3xl font-extrabold">{{ fa_num($sessions) }}</span></div>
    <div class="mt-2 font-bold">گفتگوها</div><div class="text-xs text-slate-500">چت زنده با استریم ←</div>
  </a>
</div>

<div class="card mt-6 p-5">
  <h2 class="mb-3 font-bold">{!! svg_icon('send','w-4 h-4 inline-block -mt-0.5') !!}  شروع گفتگوی سریع</h2>
  @if($agentsList->isEmpty())
    <p class="text-sm text-slate-500">هنوز ایجنتی نساخته‌اید. <a class="font-semibold text-blue-600" href="{{ route('agents.create') }}">اولین ایجنت را بسازید</a></p>
  @else
    <div class="flex flex-wrap gap-2">
      @foreach($agentsList as $a)
        <form method="POST" action="{{ route('chats.start', $a) }}">@csrf
          <button class="btn btn-ghost !py-2">{!! svg_icon('chat','w-4 h-4 inline-block -mt-0.5') !!}  {{ $a->name }}
            <span class="pill ring-1 {{ provider_badge_class($a->provider) }}">{{ $a->provider }}</span>
          </button>
        </form>
      @endforeach
    </div>
  @endif
</div>

<div class="card mt-6 overflow-hidden">
  <div class="flex items-center justify-between border-b px-5 py-3">
    <h2 class="font-bold">{!! svg_icon('clock','w-4 h-4 inline-block -mt-0.5') !!}  گفتگوهای اخیر</h2>
    <a href="{{ route('chats.index') }}" class="text-xs font-semibold text-blue-600">همه ←</a>
  </div>
  @if($recentSessions->isEmpty())
    <p class="px-5 py-6 text-sm text-slate-400">هنوز گفتگویی ثبت نشده است.</p>
  @else
  <table class="tbl w-full">
    <thead><tr><th>عنوان</th><th>ایجنت</th><th>زمان</th><th></th></tr></thead>
    <tbody>
    @foreach($recentSessions as $s)
      <tr>
        <td class="font-semibold">{{ $s->title }}</td>
        <td><span class="pill ring-1 {{ provider_badge_class($s->agent->provider ?? '') }}">{{ $s->agent->name ?? '—' }}</span></td>
        <td class="whitespace-nowrap text-xs text-slate-500">{{ fa_date($s->created_at) }}</td>
        <td class="text-left"><a href="{{ route('chats.show',$s) }}" class="btn btn-ghost !px-3 !py-1 !text-xs">باز کردن ←</a></td>
      </tr>
    @endforeach
    </tbody>
  </table>
  @endif
</div>
@endsection
