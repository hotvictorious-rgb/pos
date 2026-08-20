@extends('installer.layout')
@php $currentStep = 5; @endphp
@section('title', 'Installing…')

@section('content')
    <div class="text-center" id="installing-view">
        <div class="spinner" id="spinner"></div>
        <div class="card-title" id="install-title">Installing Hysam Ventures…</div>
        <div class="card-subtitle" id="install-subtitle">Please wait while we set up your database.</div>

        <div class="progress-wrap">
            <div class="progress-label">
                <span id="progress-label">Running migrations…</span>
                <span id="progress-pct">0%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill" style="width:0%"></div>
            </div>
        </div>

        <div class="log-box" id="log-box">Starting installation…</div>
    </div>

    <div class="text-center" id="error-view" style="display:none;">
        <div style="font-size:3rem;margin-bottom:1rem;">❌</div>
        <div class="card-title" style="color:#f87171;">Installation Failed</div>
        <div class="error-box mt-2" id="error-message"></div>
        <a href="{{ route('installer.admin') }}" class="btn btn-secondary btn-full" style="margin-top:1rem;">← Try Again</a>
    </div>
@endsection

<script>
(function () {
    const fill    = document.getElementById('progress-fill');
    const pct     = document.getElementById('progress-pct');
    const label   = document.getElementById('progress-label');
    const logBox  = document.getElementById('log-box');

    function log(msg) {
        logBox.textContent += '\n> ' + msg;
        logBox.scrollTop = logBox.scrollHeight;
    }

    function setProgress(p, text) {
        fill.style.width = p + '%';
        pct.textContent  = p + '%';
        label.textContent = text;
        log(text);
    }

    // Simulate progress then fire AJAX
    setProgress(20, 'Applying database migrations…');

    setTimeout(() => setProgress(40, 'Creating tables…'),    800);
    setTimeout(() => setProgress(60, 'Seeding default data…'), 1600);
    setTimeout(() => setProgress(80, 'Creating admin user…'),  2400);

    setTimeout(function () {
        fetch('{{ route('installer.run') }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                setProgress(100, 'Installation complete!');
                log('✓ All done – redirecting…');
                setTimeout(() => window.location.href = '{{ route('installer.complete') }}', 1200);
            } else {
                document.getElementById('installing-view').style.display = 'none';
                document.getElementById('error-view').style.display      = 'block';
                document.getElementById('error-message').textContent     = data.error || 'Unknown error.';
            }
        })
        .catch(err => {
            document.getElementById('installing-view').style.display = 'none';
            document.getElementById('error-view').style.display      = 'block';
            document.getElementById('error-message').textContent     = 'Network error: ' + err.message;
        });
    }, 3200);
})();
</script>
