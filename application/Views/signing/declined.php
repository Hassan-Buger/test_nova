<div class="max-w-xl mx-auto my-14 px-4 text-center animate-fade-in">
    <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 p-8 sm:p-12">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mb-6 ring-8 ring-amber-50/50">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
        </div>

        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-100 text-amber-800 mb-3">
            Signature Declined
        </span>

        <h1 class="text-2xl font-extrabold text-slate-900 mb-3">
            Document Signing Declined
        </h1>
        <p class="text-slate-600 text-sm mb-6">
            You have chosen to decline signing <strong><?= htmlspecialchars($request['title'] ?? 'this document') ?></strong>. The TriNova accounting team has been notified and the signing workflow has been halted.
        </p>

        <?php if (!empty($signer['declined_reason'])): ?>
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-left mb-6">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Reason Provided:</p>
                <p class="text-sm text-slate-700 italic">"<?= htmlspecialchars($signer['declined_reason']) ?>"</p>
            </div>
        <?php endif; ?>

        <p class="text-xs text-slate-400">
            If this was an error or you need amendments made to the document, please contact your TriNova account manager directly.
        </p>
    </div>
</div>
