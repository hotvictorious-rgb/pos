@extends('layouts.app')

@section('content')
<div class="subscription-container" style="padding: 1.5rem; max-width: 1280px; margin: 0 auto;">

    <!-- Header Section -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #f8fafc; letter-spacing: -0.5px; display: flex; align-items: center; gap: 0.6rem;">
                <span>⭐</span> <span>Subscription &amp; Billing Hub</span>
            </h1>
            <p style="color: #94a3b8; font-size: 0.9rem; margin-top: 0.25rem;">
                Manage business license, multi-branch capacities, and online automated renewals for <strong>{{ $tenant->name }}</strong>.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="{{ route('settings.index') }}" class="btn btn-secondary" style="font-size: 0.82rem; padding: 0.5rem 1rem;">
                ⚙️ System Settings
            </a>
            <a href="{{ route('dashboard') }}" class="btn btn-primary" style="font-size: 0.82rem; padding: 0.5rem 1rem;">
                🏠 Back to Dashboard
            </a>
        </div>
    </div>

    @if(session('success'))
        <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); color: #86efac; padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: 0.9rem; display: flex; align-items: center; gap: 0.6rem;">
            <span>✓</span> <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem; font-size: 0.9rem; display: flex; align-items: center; gap: 0.6rem;">
            <span>⚠️</span> <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- 1. EXECUTIVE STATUS DECK (4 KPI Cards) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        
        <!-- Current Tier -->
        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.25rem; position: relative; overflow: hidden;">
            <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">ACTIVE TIER</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #f8fafc; margin-top: 0.4rem; display: flex; align-items: center; gap: 0.5rem;">
                <span>{{ $plans[$tenant->plan]['name'] ?? 'Custom Enterprise' }}</span>
            </div>
            <div style="margin-top: 0.5rem;">
                @if($status === 'trial')
                    <span style="background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">
                        ⏳ 14-Day Free Trial
                    </span>
                @elseif($status === 'active')
                    <span style="background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.4); padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">
                        ✓ Active & Verified
                    </span>
                @else
                    <span style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase;">
                        ⛔ Suspended / Expired
                    </span>
                @endif
            </div>
        </div>

        <!-- Expiry / Renewal Countdown -->
        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.25rem;">
            <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">RENEWAL / EXPIRY DATE</div>
            <div style="font-size: 1.35rem; font-weight: 800; color: {{ $isExpired ? '#f87171' : '#38bdf8' }}; margin-top: 0.4rem;">
                {{ $trialEndsAt ? $trialEndsAt->format('M d, Y') : 'Lifetime / Active' }}
            </div>
            <div style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.35rem;">
                @if($daysRemaining !== null)
                    @if($isExpired)
                        <span style="color: #f87171; font-weight: 700;">Expired {{ abs($daysRemaining) }} days ago</span>
                    @else
                        <span style="color: #4ade80; font-weight: 700;">{{ $daysRemaining }} days remaining</span>
                    @endif
                @else
                    <span>Standing annual license</span>
                @endif
            </div>
        </div>

        <!-- Branch Quota Progress -->
        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">RETAIL BRANCHES</span>
                <span style="font-size: 0.8rem; font-weight: 800; color: #f8fafc;">{{ $branchesUsed }} / {{ $maxBranches > 500 ? '∞' : $maxBranches }}</span>
            </div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #f8fafc; margin-top: 0.4rem;">
                {{ $branchesUsed }} <span style="font-size: 0.85rem; font-weight: normal; color: #94a3b8;">Active Locations</span>
            </div>
            <div style="width: 100%; height: 6px; background: #0f172a; border-radius: 4px; overflow: hidden; margin-top: 0.6rem;">
                <div style="width: {{ $branchPercentage }}%; height: 100%; background: {{ $branchPercentage >= 90 ? '#ef4444' : '#0284c7' }}; border-radius: 4px; transition: width 0.3s ease;"></div>
            </div>
        </div>

        <!-- Staff Accounts Progress -->
        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px;">STAFF &amp; CASHIERS</span>
                <span style="font-size: 0.8rem; font-weight: 800; color: #f8fafc;">{{ $usersUsed }} / {{ $maxUsers > 500 ? '∞' : $maxUsers }}</span>
            </div>
            <div style="font-size: 1.35rem; font-weight: 800; color: #f8fafc; margin-top: 0.4rem;">
                {{ $usersUsed }} <span style="font-size: 0.85rem; font-weight: normal; color: #94a3b8;">Team Members</span>
            </div>
            <div style="width: 100%; height: 6px; background: #0f172a; border-radius: 4px; overflow: hidden; margin-top: 0.6rem;">
                <div style="width: {{ $userPercentage }}%; height: 100%; background: {{ $userPercentage >= 90 ? '#ef4444' : '#10b981' }}; border-radius: 4px; transition: width 0.3s ease;"></div>
            </div>
        </div>

    </div>

    <!-- 2. SUBSCRIPTION PLANS COMPARISON GRID (3 CARDS) -->
    <div style="margin-bottom: 2.5rem;">
        <h2 style="font-size: 1.25rem; font-weight: 800; color: #f8fafc; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <span>🚀</span> <span>Available Growth Tiers & Capacities</span>
        </h2>
        <p style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.25rem;">
            Scale your multi-branch operations at any time. Upgrading instantly expands your branch and cashier limits.
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(310px, 1fr)); gap: 1.25rem;">
            @foreach($plans as $pKey => $p)
                @php $isCurrent = ($tenant->plan === $pKey); @endphp
                <div style="background: #1e293b; border: 2px solid {{ $isCurrent ? '#38bdf8' : ($p['is_popular'] ?? false ? '#6366f1' : '#334155') }}; border-radius: 14px; padding: 1.75rem; position: relative; display: flex; flex-direction: column; justify-content: space-between; box-shadow: {{ $isCurrent ? '0 0 25px rgba(56, 189, 248, 0.15)' : 'none' }};">
                    
                    @if($p['is_popular'] ?? false)
                        <div style="position: absolute; top: -11px; right: 24px; background: linear-gradient(135deg, #6366f1, #0284c7); color: #ffffff; font-size: 10px; font-weight: 800; padding: 3px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px;">
                            🔥 Most Popular
                        </div>
                    @endif

                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <h3 style="font-size: 1.2rem; font-weight: 800; color: #f8fafc;">{{ $p['name'] }}</h3>
                                <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.25rem;">{{ $p['tagline'] }}</p>
                            </div>
                            @if($isCurrent)
                                <span style="background: #0369a1; color: #ffffff; font-size: 10px; font-weight: 800; padding: 3px 8px; border-radius: 6px; text-transform: uppercase;">
                                    CURRENT PLAN
                                </span>
                            @endif
                        </div>

                        <!-- Price Tag -->
                        <div style="margin: 1.25rem 0; padding-bottom: 1.25rem; border-bottom: 1px solid #334155;">
                            <div style="font-size: 2rem; font-weight: 900; color: #f8fafc; letter-spacing: -0.5px;">
                                {{ $paymentDetails['currency'] }}{{ number_format($p['price'], 0) }}
                                <span style="font-size: 0.85rem; font-weight: 500; color: #94a3b8;">/ month</span>
                            </div>
                            <div style="font-size: 0.8rem; color: #38bdf8; font-weight: 600; margin-top: 0.25rem;">
                                {{ $p['max_branches'] > 500 ? 'Unlimited' : $p['max_branches'] }} Branches • {{ $p['max_users'] > 500 ? 'Unlimited' : $p['max_users'] }} Staff Accounts
                            </div>
                        </div>

                        <!-- Features Checklist -->
                        <ul style="list-style: none; padding: 0; margin: 0 0 1.5rem 0;">
                            @foreach($p['features'] as $feat)
                                <li style="font-size: 0.82rem; color: #cbd5e1; margin-bottom: 0.65rem; display: flex; align-items: flex-start; gap: 0.5rem; line-height: 1.4;">
                                    <span style="color: #4ade80; font-weight: 800;">✓</span>
                                    <span>{{ $feat }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <!-- Action Button -->
                    <div>
                        @if($isCurrent)
                            <button type="button" disabled style="width: 100%; padding: 0.75rem; background: #334155; color: #94a3b8; border: none; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: not-allowed;">
                                ✓ Current Active Plan
                            </button>
                        @else
                            <form action="{{ route('subscription.change_plan') }}" method="POST" onsubmit="return confirm('Upgrade your business subscription to {{ $p['name'] }}?');">
                                @csrf
                                <input type="hidden" name="plan" value="{{ $pKey }}">
                                <button type="submit" style="width: 100%; padding: 0.75rem; background: linear-gradient(135deg, #0284c7, #2563eb); color: #ffffff; border: none; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: opacity 0.15s ease;">
                                    Upgrade to {{ $p['name'] }} →
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            @endforeach
        </div>
    </div>

    <!-- 3. RENEWAL & PAYMENT CHANNELS -->
    <div style="background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 1.75rem; margin-bottom: 2rem;">
        <h2 style="font-size: 1.2rem; font-weight: 800; color: #f8fafc; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <span>💳</span> <span>Renew Subscription & Online Payments</span>
        </h2>
        <p style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.5rem;">
            Instant automated renewal via Paystack or direct bank transfer into our official corporate settlement account.
        </p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
            
            <!-- Channel 1: Instant Card / Bank Checkout (Paystack) -->
            <div style="background: #0f172a; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.75rem;">
                        <span style="font-size: 1.3rem;">⚡</span>
                        <div>
                            <h3 style="font-size: 1rem; font-weight: 800; color: #f8fafc;">Instant Automated Card / Transfer</h3>
                            <div style="font-size: 0.75rem; color: #94a3b8;">Secured by Paystack • Instant Automated Activation</div>
                        </div>
                    </div>

                    <form action="{{ route('subscription.paystack.init') }}" method="POST" id="paystackForm">
                        @csrf
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <label style="font-size: 0.8rem; font-weight: 700; color: #cbd5e1; display: block; margin-bottom: 0.4rem;">Select Target Plan</label>
                            <select name="plan" style="width: 100%; padding: 0.6rem 0.75rem; background: #1e293b; border: 1px solid #475569; border-radius: 8px; color: #f8fafc; font-size: 0.85rem; outline: none;">
                                @foreach($plans as $pk => $pl)
                                    <option value="{{ $pk }}" {{ $tenant->plan === $pk ? 'selected' : '' }}>
                                        {{ $pl['name'] }} ({{ $paymentDetails['currency'] }}{{ number_format($pl['price'], 0) }} / mo)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label style="font-size: 0.8rem; font-weight: 700; color: #cbd5e1; display: block; margin-bottom: 0.4rem;">Subscription Duration</label>
                            <select name="months" style="width: 100%; padding: 0.6rem 0.75rem; background: #1e293b; border: 1px solid #475569; border-radius: 8px; color: #f8fafc; font-size: 0.85rem; outline: none;">
                                <option value="1">1 Month</option>
                                <option value="3">3 Months (Quarterly)</option>
                                <option value="6">6 Months (Bi-Annual)</option>
                                <option value="12">12 Months (Annual - Best Value)</option>
                            </select>
                        </div>

                        <button type="submit" style="width: 100%; padding: 0.85rem; background: #0ea5e9; color: #ffffff; border: none; border-radius: 8px; font-weight: 800; font-size: 0.9rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; transition: background 0.15s ease;">
                            <span>🔒</span> <span>Pay Now via Paystack</span>
                        </button>
                    </form>
                </div>
                <div style="font-size: 0.75rem; color: #64748b; margin-top: 1rem; text-align: center;">
                    Mastercard, Visa, Verve, USSD & Bank Transfer accepted.
                </div>
            </div>

            <!-- Channel 2: Official Corporate Bank Transfer -->
            <div style="background: #0f172a; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.75rem;">
                    <span style="font-size: 1.3rem;">🏛️</span>
                    <div>
                        <h3 style="font-size: 1rem; font-weight: 800; color: #f8fafc;">Official Corporate Bank Transfer</h3>
                        <div style="font-size: 0.75rem; color: #94a3b8;">Manual wire transfer & accountant receipt verification</div>
                    </div>
                </div>

                <!-- Account Box -->
                <div style="background: #1e293b; border: 1px dashed #475569; border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem;">
                    <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">BANK NAME</div>
                    <div style="font-size: 0.95rem; font-weight: 800; color: #f8fafc; margin-bottom: 0.5rem;">{{ $paymentDetails['bank_name'] }}</div>

                    <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">ACCOUNT NUMBER</div>
                    <div style="font-size: 1.25rem; font-weight: 900; color: #38bdf8; font-family: monospace; letter-spacing: 1px; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <span id="bankAccNo">{{ $paymentDetails['bank_account_number'] }}</span>
                        <button type="button" onclick="navigator.clipboard.writeText('{{ $paymentDetails['bank_account_number'] }}'); alert('Account number copied!');" style="background: #334155; color: #f8fafc; border: none; padding: 2px 6px; border-radius: 4px; font-size: 10px; cursor: pointer;">
                            Copy
                        </button>
                    </div>

                    <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">ACCOUNT NAME</div>
                    <div style="font-size: 0.85rem; font-weight: 700; color: #cbd5e1;">{{ $paymentDetails['bank_account_name'] }}</div>
                </div>

                <!-- Submission Form -->
                <form action="{{ route('subscription.manual_payment') }}" method="POST">
                    @csrf
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <div>
                            <label style="font-size: 0.75rem; color: #94a3b8; display: block; margin-bottom: 0.2rem;">Bank Paid From</label>
                            <input type="text" name="bank_paid_from" required placeholder="e.g. GTBank / Kuda" style="width: 100%; padding: 0.5rem; background: #1e293b; border: 1px solid #475569; border-radius: 6px; color: #f8fafc; font-size: 0.8rem;">
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: #94a3b8; display: block; margin-bottom: 0.2rem;">Amount Paid (₦)</label>
                            <input type="number" name="amount_paid" required min="1000" placeholder="e.g. 35000" style="width: 100%; padding: 0.5rem; background: #1e293b; border: 1px solid #475569; border-radius: 6px; color: #f8fafc; font-size: 0.8rem;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                        <div>
                            <label style="font-size: 0.75rem; color: #94a3b8; display: block; margin-bottom: 0.2rem;">Reference / Session ID</label>
                            <input type="text" name="reference" required placeholder="e.g. 0000132891" style="width: 100%; padding: 0.5rem; background: #1e293b; border: 1px solid #475569; border-radius: 6px; color: #f8fafc; font-size: 0.8rem;">
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: #94a3b8; display: block; margin-bottom: 0.2rem;">Target Plan</label>
                            <select name="plan" style="width: 100%; padding: 0.5rem; background: #1e293b; border: 1px solid #475569; border-radius: 6px; color: #f8fafc; font-size: 0.8rem;">
                                @foreach($plans as $pk => $pl)
                                    <option value="{{ $pk }}" {{ $tenant->plan === $pk ? 'selected' : '' }}>{{ $pl['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <button type="submit" style="width: 100%; padding: 0.75rem; background: #334155; color: #f8fafc; border: 1px solid #475569; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">
                        📤 Submit Transfer Confirmation
                    </button>
                </form>

            </div>

        </div>
    </div>

    <!-- 4. Support & Direct Help Line -->
    <div style="text-align: center; color: #64748b; font-size: 0.8rem; padding: 1rem;">
        Need assistance with custom enterprise scaling or custom invoice settlement?<br>
        Contact SaaS Platform Accounts: <strong>{{ $paymentDetails['support_email'] }}</strong> • <strong>{{ $paymentDetails['support_phone'] }}</strong>
    </div>

</div>
@endsection
