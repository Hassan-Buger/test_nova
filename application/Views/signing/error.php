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
            For security, signing links are one-time use tokens bound to specific authorized signatories. If you believe this is an error, please ask your TriNova contact to resend the signature invitation.
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <?php if (!empty($token)): ?>
                <a href="/sign/<?= htmlspecialchars($token) ?>" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-sm transition-all shadow-md shadow-teal-600/20">
                    &larr; Return to Document & Sign
                </a>
            <?php endif; ?>
            <a href="/login" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm transition-all border border-slate-200">
                Return to TriNova Portal
            </a>
        </div>
    </div>
</div>
