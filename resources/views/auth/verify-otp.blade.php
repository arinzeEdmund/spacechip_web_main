<x-guest-layout>
    <style>
        .otp-wrap{display:flex;gap:10px;justify-content:center;margin:24px 0}
        .otp-digit{width:48px;height:58px;text-align:center;font-size:26px;font-weight:900;border-radius:14px;border:2px solid rgba(20,84,84,.18);background:rgba(255,255,255,.85);color:#0b1a1a;outline:none;transition:border-color .15s,box-shadow .15s;caret-color:transparent;font-variant-numeric:tabular-nums}
        .otp-digit:focus{border-color:#f27457;box-shadow:0 0 0 3px rgba(242,116,87,.18)}
        .otp-digit.filled{border-color:rgba(20,84,84,.35);background:#fff}
        .countdown{font-size:13px;color:rgba(15,31,31,.5);text-align:center;margin-top:4px}
        .countdown span{font-weight:700;color:rgba(242,116,87,.9)}
    </style>

    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-3" style="background:linear-gradient(135deg,rgba(242,116,87,.12),rgba(20,84,84,.12));border:1px solid rgba(20,84,84,.12)">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2Z" stroke="#145454" stroke-width="1.8"/>
                <path d="m2 8 8.5 6a3 3 0 0 0 3 0L22 8" stroke="#145454" stroke-width="1.8"/>
            </svg>
        </div>
        <h1 class="text-xl font-extrabold text-gray-900 tracking-tight">Check your email</h1>
        <p class="mt-1 text-sm text-gray-500">We sent a 6-digit code to <strong class="text-gray-700">{{ $email }}</strong></p>
    </div>

    @if (session('status') === 'otp-resent')
        <div class="mb-4 px-4 py-3 rounded-xl text-sm font-semibold text-emerald-700" style="background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.2)">
            A new code has been sent to your email.
        </div>
    @endif

    @if ($errors->has('otp'))
        <div class="mb-4 px-4 py-3 rounded-xl text-sm font-semibold text-red-600" style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.18)">
            {{ $errors->first('otp') }}
        </div>
    @endif

    <form method="POST" action="{{ route('verification.otp.verify') }}" id="otpForm">
        @csrf
        <input type="hidden" name="otp" id="otpHidden">

        <div class="otp-wrap" id="otpBoxes">
            @for ($i = 0; $i < 6; $i++)
                <input
                    class="otp-digit"
                    type="text"
                    inputmode="numeric"
                    maxlength="1"
                    pattern="[0-9]"
                    autocomplete="one-time-code"
                    data-index="{{ $i }}"
                    {{ $i === 0 ? 'autofocus' : '' }}
                >
            @endfor
        </div>

        <div class="countdown" id="countdown">Code expires in <span id="timer">10:00</span></div>

        <div class="mt-6">
            <button type="submit" id="submitBtn" class="btn-primary w-full justify-center py-3 px-4 rounded-2xl text-sm font-bold tracking-wide uppercase opacity-50 cursor-not-allowed" disabled>
                Verify Email
            </button>
        </div>
    </form>

    <div class="mt-6 text-center">
        <form method="POST" action="{{ route('verification.otp.resend') }}">
            @csrf
            <button type="submit" id="resendBtn" class="text-sm text-gray-500 hover:text-gray-800 transition-colors" disabled>
                Didn't receive the code? <span class="font-bold text-[#145454]">Resend</span>
            </button>
        </form>
    </div>

    <div class="mt-4 text-center">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-xs text-gray-400 hover:text-gray-600 underline transition-colors">
                Cancel and log out
            </button>
        </form>
    </div>

    <script>
    (function () {
        const digits   = Array.from(document.querySelectorAll('.otp-digit'));
        const hidden   = document.getElementById('otpHidden');
        const form     = document.getElementById('otpForm');
        const submitBtn = document.getElementById('submitBtn');
        const resendBtn = document.getElementById('resendBtn');
        const timerEl  = document.getElementById('timer');

        // ── OTP input logic ──────────────────────────────────────────
        function getCode() { return digits.map(d => d.value).join(''); }

        function updateSubmit() {
            const full = getCode().length === 6;
            submitBtn.disabled = !full;
            submitBtn.classList.toggle('opacity-50', !full);
            submitBtn.classList.toggle('cursor-not-allowed', !full);
        }

        digits.forEach((el, i) => {
            el.addEventListener('input', (e) => {
                const val = e.target.value.replace(/\D/g, '');
                e.target.value = val.slice(-1);
                if (val) el.classList.add('filled');
                else el.classList.remove('filled');
                updateSubmit();
                if (val && i < 5) digits[i + 1].focus();
                if (getCode().length === 6) {
                    hidden.value = getCode();
                    // Small delay so user sees filled state before submit
                    setTimeout(() => form.requestSubmit(), 120);
                }
            });

            el.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace') {
                    if (el.value) { el.value = ''; el.classList.remove('filled'); updateSubmit(); }
                    else if (i > 0) { digits[i - 1].focus(); digits[i - 1].value = ''; digits[i - 1].classList.remove('filled'); updateSubmit(); }
                    e.preventDefault();
                } else if (e.key === 'ArrowLeft' && i > 0) {
                    digits[i - 1].focus();
                } else if (e.key === 'ArrowRight' && i < 5) {
                    digits[i + 1].focus();
                }
            });

            // Handle paste on any digit
            el.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                pasted.split('').forEach((ch, j) => {
                    if (digits[j]) { digits[j].value = ch; digits[j].classList.add('filled'); }
                });
                updateSubmit();
                const next = Math.min(pasted.length, 5);
                digits[next].focus();
                if (pasted.length === 6) {
                    hidden.value = pasted;
                    setTimeout(() => form.requestSubmit(), 120);
                }
            });
        });

        form.addEventListener('submit', () => {
            hidden.value = getCode();
        });

        // ── Countdown timer (10 min) ─────────────────────────────────
        let seconds = 600;
        const tick = setInterval(() => {
            seconds--;
            if (seconds <= 0) {
                clearInterval(tick);
                timerEl.textContent = '00:00';
                timerEl.parentElement.textContent = 'Code expired. Please request a new one.';
                resendBtn.disabled = false;
                return;
            }
            const m = String(Math.floor(seconds / 60)).padStart(2, '0');
            const s = String(seconds % 60).padStart(2, '0');
            timerEl.textContent = `${m}:${s}`;
        }, 1000);

        // Enable resend after 60 seconds
        setTimeout(() => { resendBtn.disabled = false; }, 60000);
    })();
    </script>
</x-guest-layout>
