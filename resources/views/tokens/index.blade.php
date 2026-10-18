@extends('layout')
@section('title','توکن‌های API')
@section('content')
<div class="mb-5">
  <h1 class="text-2xl font-extrabold">🔑 توکن‌های API</h1>
  <p class="mt-1 text-sm text-slate-500">نرم‌افزارهای دیگر با این توکن‌ها (هدر <code dir="ltr">Authorization: Bearer</code>) به ایجنت‌ها وصل می‌شوند</p>
</div>

<div class="card p-5">
  <h2 class="mb-3 font-bold">＋ ساخت توکن جدید</h2>
  <form method="POST" action="{{ route('tokens.store') }}" class="flex flex-wrap items-end gap-3">
    @csrf
    <div><label class="lbl">نام توکن *</label><input name="name" class="inp" required placeholder="مثلاً: سایت اصلی"></div>
    <div>
      <label class="lbl">محدود به ایجنت‌ها <span class="font-normal text-slate-400">— خالی = همه</span></label>
      <div class="flex flex-wrap gap-1.5">
        @foreach($agents as $a)
          <label class="cursor-pointer rounded-lg border px-2 py-1 text-xs has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50"><input type="checkbox" name="agent_ids[]" value="{{ $a->id }}" class="accent-blue-600"> {{ $a->name }}</label>
        @endforeach
      </div>
    </div>
    <button class="btn btn-primary">ساخت</button>
  </form>
</div>

<div class="card mt-4 overflow-hidden">
  <div class="border-b px-5 py-3 font-bold">توکن‌های موجود ({{ fa_num($tokens->count()) }})</div>
  <table class="tbl w-full">
    <thead><tr><th>نام</th><th>پیشوند</th><th>دسترسی</th><th>آخرین استفاده</th><th>وضعیت</th><th></th></tr></thead>
    <tbody>
    @forelse($tokens as $t)
      <tr>
        <td class="font-semibold">{{ $t->name }}</td>
        <td><code class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[11px]" dir="ltr">{{ $t->plain_prefix }}…</code></td>
        <td class="text-xs">{{ empty($t->agent_ids) ? 'همه ایجنت‌ها' : fa_num(count($t->agent_ids)).' ایجنت' }}</td>
        <td class="text-xs text-slate-500">{{ $t->last_used_at ? fa_date($t->last_used_at) : '—' }}</td>
        <td>@if($t->is_active)<span class="pill bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">● فعال</span>@else<span class="pill bg-slate-100 text-slate-500">○</span>@endif</td>
        <td class="text-left"><form method="POST" action="{{ route('tokens.destroy',$t) }}" onsubmit="return confirm('توکن حذف شود؟')">@csrf @method('DELETE')<button class="btn btn-danger-ghost !px-2 !py-1 !text-[11px]">🗑</button></form></td>
      </tr>
    @empty
      <tr><td colspan="6" class="px-5 py-6 text-center text-sm text-slate-400">توکنی ساخته نشده است.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
