<div class="tn-screen" style="max-width:1160px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
        <div>
            <h1 style="margin:0 0 6px;font-size:24px;font-weight:800;color:#1e293b">Digital Signatures</h1>
            <p style="margin:0;color:#61756e;font-size:14.5px">Manage e-signature requests, track real-time signer progression, and inspect cryptographic audit certificates.</p>
        </div>
        <a href="/staff/signatures/create" style="background:#0d9488;color:#fff;padding:12px 22px;border-radius:14px;font-weight:700;font-size:14px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;box-shadow:0 8px 18px -8px rgba(13,148,136,.7)">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Request Signature
        </a>
    </div>

    <!-- Filters Form -->
    <form action="/staff/signatures" method="GET" style="background:#fff;border-radius:22px;padding:18px;margin-bottom:20px;box-shadow:0 1px 2px rgba(16,54,45,.04),0 14px 34px -24px rgba(16,54,45,.3)">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
            <input type="search" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Search document title, client, or entity…" style="padding:11px 14px;border:1.5px solid #e0e9e5;border-radius:13px;background:#fbfdfc;font-size:13.5px">
            
            <select name="status" onchange="this.form.submit()" style="padding:11px 12px;border:1.5px solid #e0e9e5;border-radius:13px;background:#fbfdfc;color:#3a4d47">
                <option value="">All statuses</option>
                <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending Signatures</option>
                <option value="completed" <?= ($filters['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed & Sealed</option>
                <option value="declined" <?= ($filters['status'] ?? '') === 'declined' ? 'selected' : '' ?>>Declined</option>
                <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                <option value="draft" <?= ($filters['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
            </select>

            <select name="client_id" onchange="this.form.submit()" style="padding:11px 12px;border:1.5px solid #e0e9e5;border-radius:13px;background:#fbfdfc;color:#3a4d47">
                <option value="">All clients</option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)($filters['client_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <div style="display:flex;gap:8px">
                <button type="submit" style="flex:1;background:#f0fdfa;color:#0f766e;border:1.5px solid #ccfbf1;font-weight:700;border-radius:13px;padding:10px 16px;cursor:pointer">Apply Filters</button>
                <a href="/staff/signatures" style="background:#f8fafc;color:#64748b;border:1.5px solid #e2e8f0;font-weight:700;border-radius:13px;padding:10px 14px;text-decoration:none;display:inline-flex;align-items:center">Reset</a>
            </div>
        </div>
    </form>

    <!-- Main Table -->
    <div style="background:#fff;border-radius:22px;padding:20px;box-shadow:0 1px 2px rgba(16,54,45,.04),0 14px 34px -24px rgba(16,54,45,.3)">
        <?php if (empty($requests)): ?>
            <div style="text-align:center;padding:50px 20px;color:#8a9a94">
                <svg style="margin:0 auto 12px;display:block;opacity:.6" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                </svg>
                <h3 style="margin:0 0 6px;color:#3a4d47;font-size:16px;font-weight:700">No signature requests found</h3>
                <p style="margin:0;font-size:13.5px">Create your first signature request to securely execute client agreements.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;text-align:left">
                    <thead>
                        <tr style="border-bottom:2px solid #eef4f1;color:#7d8e88;font-size:12.5px;text-transform:uppercase;letter-spacing:.05em">
                            <th style="padding:12px 16px;font-weight:700">Request Title</th>
                            <th style="padding:12px 16px;font-weight:700">Client / Record</th>
                            <th style="padding:12px 16px;font-weight:700">Signers</th>
                            <th style="padding:12px 16px;font-weight:700">Workflow</th>
                            <th style="padding:12px 16px;font-weight:700">Status</th>
                            <th style="padding:12px 16px;font-weight:700">Created</th>
                            <th style="padding:12px 16px;font-weight:700;text-align:right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                            <tr style="border-bottom:1px solid #eef4f1">
                                <td style="padding:16px">
                                    <a href="/staff/signatures/<?= (int)$req['id'] ?>" style="font-weight:700;color:#1e293b;text-decoration:none;font-size:14px">
                                        <?= htmlspecialchars($req['title']) ?>
                                    </a>
                                    <div style="font-size:12px;color:#64748b;margin-top:2px">
                                        Doc: <?= htmlspecialchars($req['original_filename']) ?>
                                    </div>
                                </td>
                                <td style="padding:16px;color:#334155;font-size:13.5px">
                                    <div style="font-weight:700"><?= htmlspecialchars($req['client_name']) ?></div>
                                    <?php if (!empty($req['entity_name'])): ?>
                                        <div style="font-size:12px;color:#64748b"><?= htmlspecialchars($req['entity_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:16px">
                                    <div style="display:flex;align-items:center;gap:6px">
                                        <div style="font-size:13px;font-weight:700;color:#0f766e">
                                            <?= (int)$req['signed_signers'] ?> / <?= (int)$req['total_signers'] ?> signed
                                        </div>
                                    </div>
                                    <div style="width:100px;height:5px;background:#e2e8f0;border-radius:999px;margin-top:4px;overflow:hidden">
                                        <?php $pct = $req['total_signers'] > 0 ? ($req['signed_signers'] / $req['total_signers']) * 100 : 0; ?>
                                        <div style="width:<?= $pct ?>%;height:100%;background:#0d9488"></div>
                                    </div>
                                </td>
                                <td style="padding:16px;font-size:12.5px;color:#64748b;text-transform:capitalize">
                                    <?= htmlspecialchars($req['signing_order']) ?>
                                </td>
                                <td style="padding:16px">
                                    <?php
                                    $st = $req['status'];
                                    $bg = '#f1f5f9'; $fg = '#475569';
                                    if ($st === 'completed') { $bg = '#ecfdf5'; $fg = '#059669'; }
                                    elseif ($st === 'pending') { $bg = '#fef3c7'; $fg = '#d97706'; }
                                    elseif ($st === 'declined' || $st === 'cancelled') { $bg = '#fee2e2'; $fg = '#dc2626'; }
                                    ?>
                                    <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;font-size:11.5px;font-weight:700;background:<?= $bg ?>;color:<?= $fg ?>;text-transform:capitalize">
                                        <?= htmlspecialchars($st) ?>
                                    </span>
                                </td>
                                <td style="padding:16px;color:#64748b;font-size:12.5px">
                                    <?= date('d M Y, H:i', strtotime($req['created_at'])) ?>
                                </td>
                                <td style="padding:16px;text-align:right;white-space:nowrap">
                                    <a href="/staff/signatures/<?= (int)$req['id'] ?>" style="background:#f0fdfa;color:#0d9488;border:1px solid #ccfbf1;padding:7px 12px;border-radius:10px;font-weight:700;font-size:12.5px;text-decoration:none;display:inline-block;margin-right:4px">
                                        View Details
                                    </a>
                                    <?php if ($req['status'] === 'completed' && !empty($req['signed_document_id'])): ?>
                                        <a href="/staff/documents/download/<?= (int)$req['signed_document_id'] ?>" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;padding:7px 12px;border-radius:10px;font-weight:700;font-size:12.5px;text-decoration:none;display:inline-block">
                                            Signed PDF
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding-top:18px;margin-top:12px;border-top:1px solid #eef4f1;font-size:13px;color:#64748b">
                    <span>Page <?= $page ?> of <?= $totalPages ?> (Total <?= $total ?> requests)</span>
                    <div style="display:flex;gap:6px">
                        <?php if ($page > 1): ?>
                            <a href="/staff/signatures?page=<?= $page - 1 ?>" style="padding:6px 12px;border-radius:8px;background:#f1f5f9;color:#334155;font-weight:700;text-decoration:none">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="/staff/signatures?page=<?= $page + 1 ?>" style="padding:6px 12px;border-radius:8px;background:#f1f5f9;color:#334155;font-weight:700;text-decoration:none">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
