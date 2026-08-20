@extends('installer.layout')
@php $currentStep = 2; @endphp
@section('title', 'Requirements')

@section('content')
    <div class="card-title">Server Requirements</div>
    <div class="card-subtitle">Checking if your server meets all requirements.</div>

    @foreach($requirements as $req)
    <div class="req-item">
        <span class="req-name">{{ $req['name'] }}</span>
        @if($req['passed'])
            <span class="badge badge-ok">✓ Pass</span>
        @else
            <span class="badge badge-fail">✗ Fail</span>
        @endif
    </div>
    @endforeach

    <div class="btn-row" style="margin-top:1.5rem;">
        <a href="{{ route('installer.welcome') }}" class="btn btn-secondary">← Back</a>
        @if($allPassed)
            <a href="{{ route('installer.database') }}" class="btn btn-primary">Continue →</a>
        @else
            <button class="btn btn-primary" disabled style="opacity:.5;cursor:not-allowed;">
                Fix Issues First
            </button>
        @endif
    </div>

    @if(!$allPassed)
    <div class="error-box" style="margin-top:1rem;">
        ⚠️ Some requirements are not met. Please contact your hosting provider to resolve them before proceeding.
    </div>
    @endif
@endsection
