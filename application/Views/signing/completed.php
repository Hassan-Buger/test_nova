<div class="max-w-2xl mx-auto my-12 px-4 text-center animate-fade-in">
    <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 p-8 sm:p-12">
        <div class="w-20 h-20 mx-auto rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-6 shadow-sm ring-8 ring-emerald-50/50">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800 mb-3">
            Execution Successful
        </span>

        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-2">
            You're All Done!
        </h1>
        <p class="text-slate-600 text-sm sm:text-base max-w-md mx-auto mb-8">
            Thank you, <strong class="text-slate-800"><?= htmlspecialchars($signer['name'] ?? 'Signer') ?></strong>. Your signature has been securely sealed onto <strong class="text-slate-800"><?= htmlspecialchars($request['title'] ?? 'this document') ?></strong>.
        </p>

        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 text-left mb-8 space-y-3">
            <div class="flex justify-between items-center text-xs text-slate-500 border-b border-slate-200/80 pb-2.5">
                <span>Document:</span>
                <span class="font-bold text-slate-800 text-right"><?= htmlspecialchars($request['title'] ?? '') ?></span>
            </div>
            <div class="flex justify-between items-center text-xs text-slate-500 border-b border-slate-200/80 pb-2.5">
                <span>Signer:</span>
                <span class="font-bold text-slate-800"><?= htmlspecialchars($signer['email'] ?? '') ?></span>
            </div>
            <div class="flex justify-between items-center text-xs text-slate-500 border-b border-slate-200/80 pb-2.5">
                <span>Status:</span>
                <span class="font-bold <?= ($request['status'] ?? '') === 'completed' ? 'text-emerald-700' : 'text-teal-700' ?>">
                    <?= ($request['status'] ?? '') === 'completed' ? 'Fully Completed & Sealed' : 'Your Signature Recorded (Pending Co-Signers)' ?>
                </span>
            </div>
            <div class="flex justify-between items-center text-xs text-slate-500">
                <span>Timestamp:</span>
                <span class="font-bold text-slate-700"><?= date('d M Y, H:i:s T') ?></span>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="/sign/<?= htmlspecialchars($token) ?>/download" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-sm shadow-lg shadow-teal-600/20 transition-all transform active:scale-95">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download Document Copy
            </a>

            <?php if (!empty($request['qr_token'])): ?>
                <a href="/verify/signature/<?= htmlspecialchars($request['qr_token']) ?>" target="_blank" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Audit Certificate
                </a>
            <?php endif; ?>
        </div>

        <p class="text-xs text-slate-400 mt-6">
            A confirmation receipt and completed copy will be delivered to your email once all parties finish.
        </p>
    </div>
</div>
