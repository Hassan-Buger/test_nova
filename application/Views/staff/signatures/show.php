<div class="tn-screen" style="max-width:1160px">
    <div style="margin-bottom:24px">
        <a href="/staff/signatures" style="color:#64748b;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-weight:600;margin-bottom:8px">
            &larr; Back to Digital Signatures
        </a>
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
            <div>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
                    <h1 style="margin:0;font-size:24px;font-weight:800;color:#1e293b"><?= htmlspecialchars($request['title']) ?></h1>
                    <?php
                    $st = $request['status'];
                    $bg = '#f1f5f9'; $fg = '#475569';
                    if ($st === 'completed') { $bg = '#ecfdf5'; $fg = '#059669'; }
                    elseif ($st === 'pending') { $bg = '#fef3c7'; $fg = '#d97706'; }
                    elseif ($st === 'declined' || $st === 'cancelled') { $bg = '#fee2e2'; $fg = '#dc2626'; }
                    ?>
                    <span style="display:inline-block;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:700;background:<?= $bg ?>;color:<?= $fg ?>;text-transform:capitalize">
                        <?= htmlspecialchars($st) ?>
                    </span>
                </div>
                <p style="margin:0;color:#61756e;font-size:14px">
                    Request ID: <strong>#<?= (int)$request['id'] ?></strong> &bull; Workflow: <strong style="text-transform:capitalize"><?= htmlspecialchars($request['signing_order']) ?></strong> &bull; Created: <?= date('d M Y, H:i', strtotime($request['created_at'])) ?>
                </p>
            </div>

            <div style="display:flex;align-items:center;gap:8px">
                <?php if ($request['status'] === 'pending'): ?>
                    <form action="/staff/signatures/resend" method="POST" style="margin:0">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Application\Core\Session::csrfToken()) ?>">
                        <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                        <button type="submit" style="background:#f0fdfa;color:#0d9488;border:1px solid #ccfbf1;padding:9px 16px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer">
                            Resend Reminders
                        </button>
                    </form>

                    <form action="/staff/signatures/cancel" method="POST" onsubmit="return confirm('Are you sure you want to cancel this signature request?')" style="margin:0">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Application\Core\Session::csrfToken()) ?>">
                        <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                        <button type="submit" style="background:#fee2e2;color:#dc2626;border:1px solid #fecaca;padding:9px 16px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer">
                            Cancel Request
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($request['status'] === 'completed' && !empty($request['signed_document_id'])): ?>
                    <a href="/staff/documents/download/<?= (int)$request['signed_document_id'] ?>" style="background:#0d9488;color:#fff;padding:9px 18px;border-radius:10px;font-weight:700;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;box-shadow:0 6px 14px -6px rgba(13,148,136,.6)">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download Sealed PDF
                    </a>
                <?php endif; ?>

                <?php if (!empty($request['qr_token'])): ?>
                    <a href="/verify/signature/<?= htmlspecialchars($request['qr_token']) ?>" target="_blank" style="background:#f8fafc;color:#475569;border:1px solid #e2e8f0;padding:9px 14px;border-radius:10px;font-weight:700;font-size:13px;text-decoration:none">
                        View Audit Certificate &rarr;
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cryptographic Summary Banner -->
    <div style="background:#fff;border-radius:20px;padding:22px;margin-bottom:24px;box-shadow:0 1px 2px rgba(16,54,45,.04),0 14px 34px -24px rgba(16,54,45,.3);border:1px solid #eef4f1">
        <div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;margin-bottom:12px">
            Cryptographic Integrity Records
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">
            <div style="background:#f8fafc;padding:12px 16px;border-radius:12px;border:1px solid #e2e8f0">
                <div style="font-size:11.5px;font-weight:600;color:#64748b;margin-bottom:4px">Original Pre-Signing Checksum (SHA-256)</div>
                <div style="font-family:monospace;font-size:11.5px;color:#1e293b;word-break:break-all">
                    <?= htmlspecialchars($request['original_checksum_sha256'] ?? 'N/A') ?>
                </div>
            </div>
            <div style="background:#f0fdfa;padding:12px 16px;border-radius:12px;border:1px solid #ccfbf1">
                <div style="font-size:11.5px;font-weight:600;color:#0f766e;margin-bottom:4px">Final Sealed PDF Checksum (SHA-256)</div>
                <div style="font-family:monospace;font-size:11.5px;color:#134e4a;word-break:break-all">
                    <?= htmlspecialchars($request['signed_checksum_sha256'] ?? 'Pending full execution') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Signers Status Card -->
    <div style="background:#fff;border-radius:20px;padding:24px;margin-bottom:24px;box-shadow:0 1px 2px rgba(16,54,45,.04),0 14px 34px -24px rgba(16,54,45,.3)">
        <h2 style="margin:0 0 16px;font-size:17px;font-weight:800;color:#1e293b">Signatories & Execution Status</h2>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;text-align:left;font-size:13.5px">
                <thead>
                    <tr style="border-bottom:2px solid #f1f5f9;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.05em">
                        <th style="padding:10px 12px;font-weight:700">Order</th>
                        <th style="padding:10px 12px;font-weight:700">Signer</th>
                        <th style="padding:10px 12px;font-weight:700">Role</th>
                        <th style="padding:10px 12px;font-weight:700">Status</th>
                        <th style="padding:10px 12px;font-weight:700">Signed At</th>
                        <th style="padding:10px 12px;font-weight:700">IP Address</th>
                        <th style="padding:10px 12px;font-weight:700;text-align:right">Signing Link</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($signers as $s): ?>
                        <tr style="border-bottom:1px solid #f1f5f9">
                            <td style="padding:14px 12px;font-weight:700;color:#475569"><?= (int)$s['signing_order'] ?></td>
                            <td style="padding:14px 12px">
                                <div style="font-weight:700;color:#1e293b"><?= htmlspecialchars($s['name']) ?></div>
                                <div style="font-size:12px;color:#64748b"><?= htmlspecialchars($s['email']) ?></div>
                            </td>
                            <td style="padding:14px 12px;text-transform:capitalize;color:#475569"><?= htmlspecialchars($s['role']) ?></td>
                            <td style="padding:14px 12px">
                                <?php if ($s['status'] === 'signed'): ?>
                                    <span style="background:#ecfdf5;color:#059669;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700">Signed</span>
                                <?php elseif ($s['status'] === 'declined'): ?>
                                    <span style="background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700">Declined</span>
                                <?php else: ?>
                                    <span style="background:#fef3c7;color:#d97706;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:14px 12px;color:#64748b;font-size:12.5px">
                                <?= $s['signed_at'] ? date('d M Y, H:i', strtotime($s['signed_at'])) : '—' ?>
                            </td>
                            <td style="padding:14px 12px;font-family:monospace;font-size:12px;color:#64748b">
                                <?= htmlspecialchars($s['ip_address'] ?? '—') ?>
                            </td>
                            <td style="padding:14px 12px;text-align:right">
                                <?php if ($s['status'] === 'pending'): ?>
                                    <button type="button" onclick="navigator.clipboard.writeText(window.location.origin + '/sign/<?= htmlspecialchars($s['token']) ?>'); alert('Signing link copied to clipboard!');" style="background:#f1f5f9;color:#334155;border:1px solid #e2e8f0;padding:6px 10px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer">
                                        Copy Link
                                    </button>
                                <?php else: ?>
                                    <span style="color:#94a3b8;font-size:12px">Executed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Audit Events Trail -->
    <div style="background:#fff;border-radius:20px;padding:24px;box-shadow:0 1px 2px rgba(16,54,45,.04),0 14px 34px -24px rgba(16,54,45,.3)">
        <h2 style="margin:0 0 16px;font-size:17px;font-weight:800;color:#1e293b">Cryptographic Audit Timeline</h2>
        <div style="display:flex;flex-direction:column;gap:12px">
            <?php foreach ($auditEvents as $e): ?>
                <div style="display:flex;align-items:flex-start;gap:12px;background:#f8fafc;padding:12px 16px;border-radius:12px;border:1px solid #f1f5f9">
                    <div style="width:8px;height:8px;border-radius:999px;background:#0d9488;margin-top:6px;flex-shrink:0"></div>
                    <div style="flex:1">
                        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                            <span style="font-weight:700;font-size:13.5px;color:#1e293b"><?= htmlspecialchars($e['description']) ?></span>
                            <span style="font-size:12px;color:#94a3b8;font-family:monospace"><?= date('d M Y, H:i:s', strtotime($e['created_at'])) ?></span>
                        </div>
                        <div style="font-size:12px;color:#64748b;margin-top:2px">
                            Type: <strong style="color:#334155"><?= htmlspecialchars($e['event_type']) ?></strong>
                            <?php if (!empty($e['ip_address'])): ?>
                                &bull; IP: <span style="font-family:monospace"><?= htmlspecialchars($e['ip_address']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
