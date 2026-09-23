<div class="max-w-xl mx-auto my-14 px-4 text-center animate-fade-in">
    <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 p-8 sm:p-12">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mb-6 ring-8 ring-rose-50/50">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>

        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-rose-100 text-rose-800 mb-3">
            Unable to Process
        </span>

        <h1 class="text-2xl font-extrabold text-slate-900 mb-3">
            <?= htmlspecialchars($pageTitle ?? 'Notice') ?>
        </h1>
        <p class="text-slate-600 text-sm mb-6">
            <?= htmlspecialchars($message ?? 'This signature link is invalid, expired, or no longer active.') ?>
        </p>

        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-xs text-slate-500 mb-6">
            For security, signing links are one-time use tokens bound to specific authorized signatories. If your link expired or you encountered an error, you can request a fresh link below.
        </div>

        <?php if (!empty($token)): ?>
            <div class="mb-6">
                <button type="button" id="resendBtn" onclick="requestResendLink()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-sm shadow-md shadow-teal-600/20 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span id="resendBtnText">Send New Link to My Email</span>
                </button>
                <div id="resendFeedback" class="hidden mt-3 text-xs font-semibold"></div>
            </div>

            <script>
                function requestResendLink() {
                    const btn = document.getElementById('resendBtn');
                    const btnText = document.getElementById('resendBtnText');
                    const feedback = document.getElementById('resendFeedback');
                    
                    btn.disabled = true;
                    btnText.textContent = 'Sending invitation...';

                    fetch('/sign/<?= htmlspecialchars($token) ?>/resend', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(res => res.json())
                    .then(data => {
                        feedback.classList.remove('hidden');
                        if (data.success) {
                            feedback.className = 'mt-3 text-xs font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-lg p-3';
                            feedback.textContent = data.message || 'A fresh signature link has been sent to your email!';
                            btnText.textContent = 'Invitation Sent';
                        } else {
                            feedback.className = 'mt-3 text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 rounded-lg p-3';
                            feedback.textContent = data.message || 'Could not send link. Please contact your TriNova account manager.';
                            btn.disabled = false;
                            btnText.textContent = 'Retry Sending Link';
                        }
                    })
                    .catch(() => {
                        feedback.classList.remove('hidden');
                        feedback.className = 'mt-3 text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 rounded-lg p-3';
                        feedback.textContent = 'Network error. Please try again.';
                        btn.disabled = false;
                        btnText.textContent = 'Retry Sending Link';
                    });
                }
            </script>
        <?php endif; ?>

        <div>
            <a href="/login" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-sm transition-all">
                Return to TriNova Portal
            </a>
        </div>
    </div>
</div>
