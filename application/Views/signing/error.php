<?php
$isSuccess = !empty($resentSuccess);
?>
<div class="max-w-xl mx-auto my-14 px-4 text-center animate-fade-in">
    <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 p-8 sm:p-12">
        <?php if ($isSuccess): ?>
            <!-- Success State -->
            <div class="w-16 h-16 mx-auto rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-6 ring-8 ring-emerald-50/50">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>

            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 mb-3">
                Invitation Dispatched
            </span>

            <h1 class="text-2xl font-extrabold text-slate-900 mb-3">
                <?= htmlspecialchars($pageTitle ?? 'Link Sent Successfully') ?>
            </h1>
            <p class="text-slate-600 text-sm mb-6 leading-relaxed">
                <?= htmlspecialchars($message ?? 'A fresh signature link has been sent to your email.') ?>
            </p>

            <div class="bg-emerald-50/70 border border-emerald-200 rounded-2xl p-4 text-xs text-emerald-800 mb-6 text-left flex items-start gap-3">
                <svg class="w-4 h-4 text-emerald-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="leading-relaxed">
                    Please check your inbox (and spam/junk folder) for the email from <strong>TriNova Accounting</strong>. You can also return directly to the document below to complete your signature.
                </div>
            </div>
        <?php else: ?>
            <!-- Error / Notice State -->
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
            <p class="text-slate-600 text-sm mb-6 leading-relaxed">
                <?= htmlspecialchars($message ?? 'This signature link is invalid, expired, or no longer active.') ?>
            </p>

            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 text-left mb-6">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Need a new signature invitation?</h4>
                        <p class="text-xs text-slate-500 leading-relaxed mb-3">
                            For security, signing links are one-time use tokens bound to specific authorized signatories. You can request a fresh link sent directly to your registered email address.
                        </p>
                        <?php if (!empty($token)): ?>
                            <form action="/sign/<?= htmlspecialchars($token) ?>/resend" method="POST" onsubmit="const b=this.querySelector('button');b.disabled=true;b.innerHTML='Sending email...';">
                                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs shadow-sm transition-all cursor-pointer">
                                    <svg class="w-3.5 h-3.5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                    </svg>
                                    Send New Link to My Email
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

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
