<x-guest-layout>
    <main class="login-page">
        <section class="login-story" aria-label="Billing account overview">
            <div class="login-story-glow login-story-glow-one"></div>
            <div class="login-story-glow login-story-glow-two"></div>

            <a href="{{ url('/') }}" class="login-brand">
                <span class="login-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 40 40" fill="none">
                        <path d="M4 14.5 20 6l16 8.5L20 23 4 14.5Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/>
                        <path d="M10 18v8c5.8 5 14.2 5 20 0v-8M36 15v10" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="36" cy="28" r="2" fill="currentColor"/>
                    </svg>
                </span>
                <span class="login-brand-copy">
                    <span class="login-brand-name">Billing System</span>
                    <span class="login-brand-subtitle">STUDENT ACCOUNTS</span>
                </span>
            </a>

            <div class="login-story-content">
                <span class="login-eyebrow"><span></span> STUDENT ACCOUNTS · PAYMENT TRACKING</span>
                <h1>Tuition, tracked.<br><span>Clarity, delivered.</span></h1>
                <p>Your school account, organized in one place. Keep an eye on charges, payment progress, and what comes next.</p>

                <div class="login-ledger-card" aria-label="Illustrative sample billing statement">
                    <div class="login-ledger-heading">
                        <span><span class="login-ledger-indicator"></span> BILLING OVERVIEW</span>
                        <span class="login-ledger-sample">SAMPLE BILL</span>
                    </div>
                    <div class="login-ledger-balance">
                        <span>CURRENT BALANCE</span>
                        <strong><span>₱</span> 8,450.00</strong>
                    </div>
                    <div class="login-ledger-rows">
                        <div><span>Tuition balance</span><strong>₱7,500.00</strong></div>
                        <div><span>School fees</span><strong>₱950.00</strong></div>
                    </div>
                    <div class="login-ledger-progress">
                        <div><span>Payment progress</span><strong>64%</strong></div>
                        <span class="login-ledger-progress-track"><span></span></span>
                    </div>
                    <div class="login-ledger-footer">
                        <span>ACCOUNT SNAPSHOT</span>
                        <span>Charges · Payments · Balance</span>
                    </div>
                </div>
            </div>

            <p class="login-story-footer">A better school experience starts here.</p>
        </section>

        <section class="login-panel">
            <div class="login-panel-inner">
                <a href="{{ url('/') }}" class="login-mobile-brand">
                    <span class="login-brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 40 40" fill="none">
                            <path d="M4 14.5 20 6l16 8.5L20 23 4 14.5Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/>
                            <path d="M10 18v8c5.8 5 14.2 5 20 0v-8M36 15v10" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="36" cy="28" r="2" fill="currentColor"/>
                        </svg>
                    </span>
                    <span class="login-brand-copy">
                        <span class="login-brand-name">Billing System</span>
                        <span class="login-brand-subtitle">STUDENT ACCOUNTS</span>
                    </span>
                </a>

                <div class="login-form-heading">
                    <span class="login-form-kicker">YOUR ACCOUNT, AT A GLANCE</span>
                    <h2>Welcome back.</h2>
                    <p>Sign in to review your school billing account.</p>
                </div>

                @if ($errors->any())
                    <div class="login-alert" role="alert">
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="7.5" stroke="currentColor" stroke-width="1.5"/><path d="M10 6.5v4m0 3h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                        <div>
                            <strong>We couldn't sign you in.</strong>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @session('status')
                    <div class="login-status" role="status">{{ $value }}</div>
                @endsession

                <form method="POST" action="{{ route('login') }}" class="login-form">
                    @csrf

                    <div class="login-field">
                        <label for="username">{{ __('Username') }}</label>
                        <div class="login-input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.6"/><path d="M5 20a7 7 0 0 1 14 0m-12-9.5a3.5 3.5 0 0 0-2.5 3.35V15m15-4.5a3.5 3.5 0 0 1 2.5 3.35V15" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                            <input id="username" class="login-input" type="text" name="username" value="{{ old('username') }}" placeholder="Enter your username" required autofocus autocomplete="username" aria-describedby="username-error" @error('username') aria-invalid="true" @enderror>
                        </div>
                        @error('username')
                            <p id="username-error" class="login-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="login-field">
                        <div class="login-label-row">
                            <label for="password">{{ __('Password') }}</label>
                            @if (Route::has('password.request'))
                                <a class="login-forgot" href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
                            @endif
                        </div>
                        <div class="login-input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="4.5" y="10" width="15" height="10.5" rx="2.5" stroke="currentColor" stroke-width="1.6"/><path d="M8 10V7a4 4 0 1 1 8 0v3m-4 4v2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                            <input id="password" class="login-input" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password" aria-describedby="password-error" @error('password') aria-invalid="true" @enderror>
                        </div>
                        @error('password')
                            <p id="password-error" class="login-field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <label for="remember_me" class="login-remember">
                        <input id="remember_me" type="checkbox" name="remember">
                        <span>{{ __('Keep me signed in') }}</span>
                    </label>

                    <button type="submit" class="login-submit">
                        <span>{{ __('Sign in') }}</span>
                        <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </form>

                <p class="login-help">Having trouble accessing your account? Contact your school administrator.</p>
            </div>

            <footer class="login-panel-footer">
                <span>&copy; {{ date('Y') }} {{ config('app.name', 'Billing System') }}</span>
                <span class="login-secure-note">
                    <svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="3.25" y="7" width="9.5" height="7" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                    SECURE SIGN IN
                </span>
            </footer>
        </section>
    </main>
</x-guest-layout>
