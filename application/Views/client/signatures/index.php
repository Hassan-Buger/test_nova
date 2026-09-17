<div class="tn-screen" style="max-width:960px">
    <div style="margin-bottom:24px">
        <h1 style="margin:0 0 6px;font-size:24px;font-weight:800;color:#1e293b">Pending Signatures</h1>
        <p style="margin:0;color:#61756e;font-size:14.5px">Review and digitally execute official accounting and statutory documents requiring your legal signature.</p>
    </div>

    <div style="background:#fff;border-radius:24px;padding:24px;box-shadow:0 1px 2px rgba(16,54,45,.04),0 14px 34px -24px rgba(16,54,45,.3)">
        <?php if (empty($pending)): ?>
            <div style="text-align:center;padding:60px 20px;color:#8a9a94">
                <div style="width:64px;height:64px;border-radius:20px;background:#f0fdfa;color:#0d9488;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 style="margin:0 0 6px;color:#1e293b;font-size:18px;font-weight:800">You're All Caught Up!</h3>
                <p style="margin:0;font-size:14px;color:#64748b;max-width:400px;margin:0 auto">There are no documents currently awaiting your digital signature.</p>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:16px">
                <?php foreach ($pending as $p): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;padding:20px;border-radius:18px;background:#f8fafc;border:1.5px solid #e2e8f0;transition:all .15s">
                        <div style="display:flex;align-items:center;gap:14px">
                            <div style="width:48px;height:48px;border-radius:14px;background:#ecfdf5;color:#059669;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 style="margin:0 0 4px;font-size:16px;font-weight:800;color:#1e293b">
                                    <?= htmlspecialchars($p['title']) ?>
                                </h3>
                                <div style="font-size:12.5px;color:#64748b;display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                                    <span>File: <strong><?= htmlspecialchars($p['original_filename']) ?></strong></span>
                                    <?php if (!empty($p['entity_name'])): ?>
                                        <span>&bull;</span>
                                        <span>Entity: <strong><?= htmlspecialchars($p['entity_name']) ?></strong></span>
                                    <?php endif; ?>
                                    <span>&bull;</span>
                                    <span>Requested: <?= date('d M Y', strtotime($p['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>

                        <div style="display:flex;align-items:center;gap:10px">
                            <a href="/sign/<?= htmlspecialchars($p['signer_token']) ?>" class="tn-btn-sign" style="background:#0d9488;color:#fff;padding:10px 22px;border-radius:12px;font-weight:700;font-size:13.5px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 12px -2px rgba(13,148,136,.4)">
                                Review & Sign
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
