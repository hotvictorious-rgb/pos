{{--
  DEPRECATED & RETIRED (Pass 16):
  The legacy React single-page container has been retired.
  VMarket POS uses authoritative server-rendered Blade templates extending 'layouts.app'.
--}}
@extends('layouts.app')

@section('title', 'Legacy Interface Retired')

@section('content')
<div class="p-8 text-center max-w-lg mx-auto mt-16 bg-gray-900 border border-gray-800 rounded-xl">
    <div class="inline-flex p-3 bg-red-500/10 rounded-full text-red-400 mb-4">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
    </div>
    <h2 class="text-xl font-bold text-white mb-2">Legacy SPA Container Retired</h2>
    <p class="text-gray-400 text-sm mb-6">The client-side single page app has been decommissioned. All POS operations execute through authoritative Laravel Blade views.</p>
    <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition">
        Return to Executive Dashboard &rarr;
    </a>
</div>
@endsection
