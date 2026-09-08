@extends('layouts.app')

@section('title', 'Change Account Password - Victorious POS')

@section('content')
<div style="max-width: 680px; margin: 2rem auto; padding: 0 1rem;">
    <!-- Breadcrumb & Header -->
    <div style="margin-bottom: 1.75rem;">
        <a href="{{ route('dashboard') }}" style="color: #93c5fd; text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.35rem; margin-bottom: 0.5rem;">
            <span>&larr;</span> <span>Back to Dashboard</span>
        </a>
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.65rem; font-weight: 800; color: #f8fafc; margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                    <span>🛡️</span> <span>Change Password</span>
                </h1>
                <p style="color: #94a3b8; font-size: 0.88rem; margin-top: 0.35rem;">
                    Update your account credentials. Secure your terminal with an authoritative password.
                </p>
            </div>
            <div style="background: rgba(30, 41, 59, 0.8); border: 1px solid var(--border); border-radius: 12px; padding: 0.5rem 0.85rem; text-align: right;">
                <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8;">Active Account</div>
                <div style="font-size: 0.85rem; font-weight: 700; color: #38bdf8;">{{ $user->email }}</div>
            </div>
        </div>
    </div>

    <!-- Security Warning & Policy Callout -->
    <div style="background: linear-gradient(135deg, rgba(30, 58, 138, 0.25), rgba(15, 23, 42, 0.6)); border: 1px solid rgba(59, 130, 246, 0.35); border-radius: 14px; padding: 1.15rem; margin-bottom: 1.75rem; display: flex; gap: 0.9rem; align-items: flex-start;">
        <div style="font-size: 1.5rem; line-height: 1;">🔒</div>
        <div style="font-size: 0.83rem; color: #cbd5e1; line-height: 1.5;">
            <strong style="color: #60a5fa;">Cryptographic Invariant:</strong>
            Passwords must contain at least <strong>8 characters</strong>, include at least <strong>one uppercase letter (A-Z)</strong>, and at least <strong>one number (0-9)</strong>.
            Your password is encrypted using a salted adaptive bcrypt hash. Plaintext passwords are never stored or logged.
        </div>
    </div>

    <!-- Form Card -->
    <div class="card" style="background: rgba(15, 23, 42, 0.85); border: 1px solid rgba(51, 65, 85, 0.7); border-radius: 18px; padding: 2rem; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);">
        <form action="{{ route('account.password.update') }}" method="POST" autocomplete="off" id="changePasswordForm">
            @csrf

            <!-- Current Password -->
            <div style="margin-bottom: 1.5rem;">
                <label for="current_password" style="display: block; font-size: 0.85rem; font-weight: 700; color: #e2e8f0; margin-bottom: 0.45rem;">
                    Current Password <span style="color: #ef4444;">*</span>
                </label>
                <div style="position: relative;">
                    <input type="password" 
                           name="current_password" 
                           id="current_password" 
                           required 
                           autocomplete="current-password"
                           placeholder="Enter your current password"
                           style="width: 100%; padding: 0.75rem 2.75rem 0.75rem 0.9rem; background: rgba(30, 41, 59, 0.7); border: 1px solid #475569; border-radius: 10px; color: #f8fafc; font-size: 0.92rem; outline: none; transition: border-color 0.2s;"
                           onfocus="this.style.borderColor='#3b82f6'" 
                           onblur="this.style.borderColor='#475569'">
                    <button type="button" 
                            tabindex="-1"
                            onclick="togglePasswordVisibility('current_password', this)"
                            style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: #94a3b8; cursor: pointer; padding: 0.25rem; font-size: 1rem;"
                            title="Toggle visibility">
                        👁️
                    </button>
                </div>
                @error('current_password')
                    <div style="color: #f87171; font-size: 0.8rem; margin-top: 0.35rem; font-weight: 600;">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <hr style="border: 0; border-top: 1px solid rgba(51, 65, 85, 0.7); margin: 1.5rem 0;">

            <!-- New Password -->
            <div style="margin-bottom: 1.5rem;">
                <label for="new_password" style="display: block; font-size: 0.85rem; font-weight: 700; color: #e2e8f0; margin-bottom: 0.45rem;">
                    New Secure Password <span style="color: #ef4444;">*</span>
                </label>
                <div style="position: relative;">
                    <input type="password" 
                           name="new_password" 
                           id="new_password" 
                           required 
                           autocomplete="new-password"
                           placeholder="At least 8 characters with uppercase & number"
                           onkeyup="checkPasswordStrength(this.value)"
                           style="width: 100%; padding: 0.75rem 2.75rem 0.75rem 0.9rem; background: rgba(30, 41, 59, 0.7); border: 1px solid #475569; border-radius: 10px; color: #f8fafc; font-size: 0.92rem; outline: none; transition: border-color 0.2s;"
                           onfocus="this.style.borderColor='#3b82f6'" 
                           onblur="this.style.borderColor='#475569'">
                    <button type="button" 
                            tabindex="-1"
                            onclick="togglePasswordVisibility('new_password', this)"
                            style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: #94a3b8; cursor: pointer; padding: 0.25rem; font-size: 1rem;"
                            title="Toggle visibility">
                        👁️
                    </button>
                </div>
                @error('new_password')
                    <div style="color: #f87171; font-size: 0.8rem; margin-top: 0.35rem; font-weight: 600;">
                        {{ $message }}
                    </div>
                @enderror

                <!-- Live Policy Checklist -->
                <div style="margin-top: 0.75rem; background: rgba(30, 41, 59, 0.45); border: 1px solid rgba(51, 65, 85, 0.5); border-radius: 10px; padding: 0.75rem 0.9rem; display: flex; flex-direction: column; gap: 0.4rem;">
                    <div id="check-len" style="font-size: 0.78rem; color: #94a3b8; display: flex; align-items: center; gap: 0.45rem;">
                        <span id="check-len-icon">⚪</span> <span>Minimum 8 characters</span>
                    </div>
                    <div id="check-upper" style="font-size: 0.78rem; color: #94a3b8; display: flex; align-items: center; gap: 0.45rem;">
                        <span id="check-upper-icon">⚪</span> <span>At least one uppercase letter (A-Z)</span>
                    </div>
                    <div id="check-num" style="font-size: 0.78rem; color: #94a3b8; display: flex; align-items: center; gap: 0.45rem;">
                        <span id="check-num-icon">⚪</span> <span>At least one numeric digit (0-9)</span>
                    </div>
                </div>
            </div>

            <!-- Confirm New Password -->
            <div style="margin-bottom: 2rem;">
                <label for="new_password_confirmation" style="display: block; font-size: 0.85rem; font-weight: 700; color: #e2e8f0; margin-bottom: 0.45rem;">
                    Confirm New Password <span style="color: #ef4444;">*</span>
                </label>
                <div style="position: relative;">
                    <input type="password" 
                           name="new_password_confirmation" 
                           id="new_password_confirmation" 
                           required 
                           autocomplete="new-password"
                           placeholder="Re-enter your new password exactly"
                           onkeyup="checkPasswordMatch()"
                           style="width: 100%; padding: 0.75rem 2.75rem 0.75rem 0.9rem; background: rgba(30, 41, 59, 0.7); border: 1px solid #475569; border-radius: 10px; color: #f8fafc; font-size: 0.92rem; outline: none; transition: border-color 0.2s;"
                           onfocus="this.style.borderColor='#3b82f6'" 
                           onblur="this.style.borderColor='#475569'">
                    <button type="button" 
                            tabindex="-1"
                            onclick="togglePasswordVisibility('new_password_confirmation', this)"
                            style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: #94a3b8; cursor: pointer; padding: 0.25rem; font-size: 1rem;"
                            title="Toggle visibility">
                        👁️
                    </button>
                </div>
                <div id="match-indicator" style="font-size: 0.8rem; margin-top: 0.35rem; display: none;"></div>
            </div>

            <!-- Actions -->
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                <a href="{{ route('dashboard') }}" class="btn btn-secondary" style="padding: 0.65rem 1.25rem;">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem; background: #2563eb; border-color: #3b82f6;">
                    <span>🔒</span> <span>Update Password</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePasswordVisibility(fieldId, btn) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    if (field.type === 'password') {
        field.type = 'text';
        btn.textContent = '🙈';
    } else {
        field.type = 'password';
        btn.textContent = '👁️';
    }
}

function checkPasswordStrength(pw) {
    const hasLength = pw.length >= 8;
    const hasUpper = /[A-Z]/.test(pw);
    const hasNum = /[0-9]/.test(pw);

    updateCriterion('check-len', 'check-len-icon', hasLength);
    updateCriterion('check-upper', 'check-upper-icon', hasUpper);
    updateCriterion('check-num', 'check-num-icon', hasNum);

    checkPasswordMatch();
}

function updateCriterion(elemId, iconId, isValid) {
    const elem = document.getElementById(elemId);
    const icon = document.getElementById(iconId);
    if (!elem || !icon) return;

    if (isValid) {
        elem.style.color = '#4ade80';
        icon.textContent = '✅';
    } else {
        elem.style.color = '#94a3b8';
        icon.textContent = '⚪';
    }
}

function checkPasswordMatch() {
    const pw = document.getElementById('new_password').value;
    const conf = document.getElementById('new_password_confirmation').value;
    const indicator = document.getElementById('match-indicator');
    if (!indicator) return;

    if (!conf) {
        indicator.style.display = 'none';
        return;
    }

    indicator.style.display = 'block';
    if (pw === conf) {
        indicator.style.color = '#4ade80';
        indicator.textContent = '✓ Passwords match';
    } else {
        indicator.style.color = '#f87171';
        indicator.textContent = '✗ Passwords do not match';
    }
}
</script>
@endsection
