@extends('installer.layout')
@php $currentStep = 4; @endphp
@section('title', 'Admin Account')

@section('content')
    <div class="card-title">Create Admin Account</div>
    <div class="card-subtitle">This will be the super-administrator login for Hysam Ventures.</div>

    @if($errors->any())
    <div class="error-box">
        {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ route('installer.install') }}">
        @csrf

        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="admin_name" value="{{ old('admin_name') }}" placeholder="e.g. Hysam Admin" required />
            <div class="error">{{ $errors->first('admin_name') }}</div>
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="admin_email" value="{{ old('admin_email') }}" placeholder="admin@hysamventures.com" required />
            <div class="error">{{ $errors->first('admin_email') }}</div>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="admin_password" placeholder="Minimum 8 characters" required />
            <div class="error">{{ $errors->first('admin_password') }}</div>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="admin_password_confirmation" placeholder="Repeat password" required />
        </div>

        <div class="btn-row">
            <a href="{{ route('installer.database') }}" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary">Install Now →</button>
        </div>
    </form>
@endsection
