@extends('installer.layout')
@php $currentStep = 3; @endphp
@section('title', 'Database Setup')

@section('content')
    <div class="card-title">Database Configuration</div>
    <div class="card-subtitle">Enter the MySQL credentials for your hosting account.</div>

    @if($errors->any())
    <div class="error-box">
        {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ route('installer.database.save') }}">
        @csrf

        <div class="form-group">
            <label>Database Host</label>
            <input type="text" name="db_host" value="{{ old('db_host', '127.0.0.1') }}" placeholder="127.0.0.1" required />
            <div class="error">{{ $errors->first('db_host') }}</div>
        </div>

        <div class="form-group">
            <label>Database Port</label>
            <input type="number" name="db_port" value="{{ old('db_port', '3306') }}" placeholder="3306" required />
        </div>

        <div class="form-group">
            <label>Database Name</label>
            <input type="text" name="db_name" value="{{ old('db_name') }}" placeholder="hysam_db" required />
        </div>

        <div class="form-group">
            <label>Database Username</label>
            <input type="text" name="db_username" value="{{ old('db_username') }}" placeholder="hysam_user" required />
        </div>

        <div class="form-group">
            <label>Database Password</label>
            <input type="password" name="db_password" placeholder="Leave blank if no password" />
        </div>

        <div class="btn-row">
            <a href="{{ route('installer.requirements') }}" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary">Test & Continue →</button>
        </div>
    </form>

    <p style="font-size:.75rem;color:#475569;margin-top:1.25rem;">
        💡 On Whogohost cPanel, find your DB details under <strong>MySQL Databases</strong>.
    </p>
@endsection
