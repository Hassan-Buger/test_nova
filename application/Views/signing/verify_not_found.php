<div class="max-w-xl mx-auto my-14 px-4 text-center animate-fade-in">
    <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 p-8 sm:p-12">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-100 text-slate-500 flex items-center justify-center mb-6 ring-8 ring-slate-50">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>

        <h1 class="text-2xl font-extrabold text-slate-900 mb-3">
            Certificate Not Found
        </h1>
        <p class="text-slate-600 text-sm mb-6">
            The verification token <code class="px-2 py-1 bg-slate-100 rounded text-xs text-slate-700"><?= htmlspecialchars($qrToken ?? '') ?></code> could not be located in our cryptographic registry.
        </p>

        <p class="text-xs text-slate-400">
            Please ensure you have scanned the official QR code stamped on the TriNova Certificate of Completion page.
        </p>
    </div>
</div>
