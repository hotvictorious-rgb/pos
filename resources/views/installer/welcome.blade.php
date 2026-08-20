@extends('installer.layout')
@php $currentStep = 1; @endphp
@section('title', 'Welcome')

@section('content')
    <div class="text-center">
        <div style="font-size:3rem;margin-bottom:1rem;">📦</div>
        <div class="card-title">Welcome to Hysam Ventures</div>
        <div class="card-subtitle">
            This wizard will guide you through the setup of your<br>
            inventory management system in just a few minutes.
        </div>

        <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:12px;padding:1.25rem;margin-bottom:2rem;text-align:left;">
            <div style="font-size:.85rem;color:#93c5fd;font-weight:600;margin-bottom:.75rem;">What this wizard will do:</div>
            <ul style="list-style:none;display:flex;flex-direction:column;gap:.5rem;">
                @foreach(['Check server requirements','Configure your database','Create your admin account','Run database migrations','Lock the installer for security'] as $item)
                <li style="display:flex;align-items:center;gap:.6rem;font-size:.875rem;color:#cbd5e1;">
                    <span style="color:#22c55e;">✓</span> {{ $item }}
                </li>
                @endforeach
            </ul>
        </div>

        <a href="{{ route('installer.requirements') }}" class="btn btn-primary btn-full">
            Get Started →
        </a>

        <p style="font-size:.75rem;color:#475569;margin-top:1rem;">
            Framework: Laravel 10 · PHP 8.3 · MySQL
        </p>
    </div>
@endsection
