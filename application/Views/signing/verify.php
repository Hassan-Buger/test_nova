<?php
$origChecksum = $request['original_checksum_sha256'] ?? null;
if (empty($origChecksum) && !empty($request['original_stored_path'])) {
    $origPath = \Application\Services\FileStorageService::resolvePath((string)$request['original_stored_path'], (string)($request['original_filename'] ?? ''));
    if (is_file($origPath)) {
        $origChecksum = hash_file('sha256', $origPath);
    }
}
$signedChecksum = $request['signed_checksum_sha256'] ?? null;
if (empty($signedChecksum) && !empty($request['signed_stored_path'])) {
    $signedPath = \Application\Services\FileStorageService::resolvePath((string)$request['signed_stored_path'], (string)($request['signed_filename'] ?? ''));
    if (is_file($signedPath)) {
        $signedChecksum = hash_file('sha256', $signedPath);
    }
}
$signedDocId = (int)($request['signed_doc_id'] ?? $request['signed_document_id'] ?? 0);
?>
<div class="max-w-4xl mx-auto my-10 px-4 animate-fade-in">
    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-teal-900 via-teal-800 to-slate-900 rounded-3xl p-8 text-white shadow-xl mb-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 mb-3">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Cryptographically Sealed &amp; Verified
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                <?= htmlspecialchars($request['title'] ?? 'Document') ?>
            </h1>
            <p class="text-teal-200/80 text-xs sm:text-sm mt-1">
                TriNova Audit Certificate ID: <span class="font-mono text-white"><?= htmlspecialchars($qrToken ?? '') ?></span>
            </p>
        </div>
        <div class="text-right flex flex-col sm:flex-row items-end sm:items-center gap-3">
            <span class="inline-block px-4 py-2 rounded-xl text-sm font-bold bg-white/10 backdrop-blur border border-white/20">
                Status: <span class="text-emerald-300 uppercase"><?= htmlspecialchars($request['status'] ?? 'COMPLETED') ?></span>
            </span>
            <?php if ($signedDocId > 0): ?>
                <a href="/documents/download/<?= $signedDocId ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold bg-emerald-400 hover:bg-emerald-300 text-slate-950 transition-all shadow-md">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download Sealed PDF
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cryptographic Verification Box -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-8">
        <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-400 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            Cryptographic Integrity &amp; Digital Checksums
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200/80">
                <div class="text-xs font-semibold text-slate-500 mb-1">Pre-Execution Document SHA-256</div>
                <div class="font-mono text-xs text-slate-800 break-all bg-white p-2.5 rounded-lg border border-slate-200">
                    <?= htmlspecialchars($origChecksum ?: 'N/A') ?>
                </div>
            </div>
            <div class="p-4 rounded-xl bg-teal-50/50 border border-teal-200/60">
                <div class="text-xs font-semibold text-teal-800 mb-1">Sealed Final PDF SHA-256</div>
                <div class="font-mono text-xs text-teal-900 break-all bg-white p-2.5 rounded-lg border border-teal-200">
                    <?= htmlspecialchars($signedChecksum ?: ($request['status'] === 'completed' ? 'Sealed document' : 'Pending full execution')) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Signatories Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-8">
        <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-400 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            Signatory Status &amp; Cryptographic Events
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs text-slate-500 uppercase tracking-wider">
                        <th class="py-3 px-3">Signer</th>
                        <th class="py-3 px-3">Role</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Signed At</th>
                        <th class="py-3 px-3">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($signers as $s): ?>
                        <tr>
                            <td class="py-3 px-3 font-semibold text-slate-900">
                                <?= htmlspecialchars($s['name'] ?? 'Signer') ?>
                                <div class="text-xs font-normal text-slate-500"><?= htmlspecialchars($s['email'] ?? '') ?></div>
                            </td>
                            <td class="py-3 px-3 text-slate-600 capitalize"><?= htmlspecialchars($s['role'] ?? 'signer') ?></td>
                            <td class="py-3 px-3">
                                <?php if (($s['status'] ?? '') === 'signed'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Signed
                                    </span>
                                <?php elseif (($s['status'] ?? '') === 'declined'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                        Declined
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                        Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-3 text-slate-600 font-mono text-xs">
                                <?= !empty($s['signed_at']) ? date('d M Y, H:i:s T', strtotime($s['signed_at'])) : '—' ?>
                            </td>
                            <td class="py-3 px-3 text-slate-600 font-mono text-xs">
                                <?= htmlspecialchars($s['ip_address'] ?? '—') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Audit Trail -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-400 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Comprehensive Audit Trail
        </h2>
        <div class="space-y-4">
            <?php foreach ($auditEvents as $e): ?>
                <div class="flex items-start gap-4 p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="w-2.5 h-2.5 rounded-full bg-teal-600 mt-2 shrink-0"></div>
                    <div class="flex-1">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <span class="font-bold text-sm text-slate-800"><?= htmlspecialchars($e['event_description'] ?? $e['description'] ?? '') ?></span>
                            <span class="text-xs text-slate-400 font-mono"><?= !empty($e['created_at']) ? date('d M Y, H:i:s T', strtotime($e['created_at'])) : '' ?></span>
                        </div>
                        <div class="text-xs text-slate-500 mt-1 flex flex-wrap gap-x-4 gap-y-1">
                            <span>Event: <strong class="text-slate-700"><?= htmlspecialchars($e['event_type'] ?? '') ?></strong></span>
                            <?php if (!empty($e['ip_address'])): ?>
                                <span>IP: <span class="font-mono text-slate-700"><?= htmlspecialchars($e['ip_address']) ?></span></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
