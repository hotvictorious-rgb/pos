@extends('installer.layout')
@php $currentStep = 6; @endphp
@section('title', 'Installation Complete')

@section('content')
    <div class="text-center">
        <div class="success-icon">✓</div>

        <div class="card-title">Installation Complete!</div>
        <div class="card-subtitle mt-1">
            Hysam Ventures has been successfully installed<br>and is ready to use.
        </div>

        <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:12px;padding:1.25rem;margin:1.5rem 0;text-align:left;">
            <div style="font-size:.85rem;color:#4ade80;font-weight:600;margin-bottom:.75rem;">✓ Setup Summary</div>
            <ul style="list-style:none;display:flex;flex-direction:column;gap:.4rem;">
                @foreach(['Database configured and connected','Migrations applied successfully','Admin account created','Installer locked (cannot be re-run)','Config & route caches applied'] as $item)
                <li style="font-size:.85rem;color:#86efac;display:flex;align-items:center;gap:.5rem;">
                    <span>✓</span> {{ $item }}
                </li>
                @endforeach
            </ul>
        </div>

        <div style="background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);border-radius:10px;padding:1rem;margin-bottom:1.5rem;font-size:.8rem;color:#fcd34d;">
            🔒 The installer has been locked. Delete the <code>storage/installed</code> file only if you need to re-run setup.
        </div>

        <a href="{{ url('/') }}" class="btn btn-primary btn-full" style="font-size:1rem;">
            Go to Dashboard →
        </a>

        <p style="font-size:.75rem;color:#475569;margin-top:1rem;">
            Log in with the admin credentials you just created.
        </p>
    </div>
@endsection
