<div class="tn-screen" style="max-width:1120px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
        <div>
            <p style="margin:0;color:#61756e;font-size:14.5px">Overview of all client uploads and dispatch new files to clients.</p>
        </div>
        <button onclick="document.getElementById('dispatchModal').style.display='flex'" style="background:#41556f;color:#fff;padding:12px 22px;border-radius:14px;font-weight:700;font-size:14px;cursor:pointer;border:none;box-shadow:0 8px 18px -8px rgba(65,85,111,.7)">+ Dispatch Document to Client</button>
    </div>

    <?php
    $activeFilterLabels = [];
    if (($filters['search'] ?? '') !== '') $activeFilterLabels[] = 'Search: ' . $filters['search'];
    if (!empty($filters['client_id'])) {
        foreach ($clients as $filterClient) {
            if ((int)$filterClient['id'] === (int)$filters['client_id']) {
                $activeFilterLabels[] = 'Client: ' . $filterClient['name'];
                break;
            }
        }
    }
    if (($filters['direction'] ?? '') !== '') $activeFilterLabels[] = $filters['direction'] === 'client_upload' ? 'Client uploads' : 'From TriNova';
    if (($filters['status'] ?? '') !== '') $activeFilterLabels[] = 'Status: ' . $filters['status'];
    if (($filters['file_type'] ?? '') !== '') $activeFilterLabels[] = 'Type: ' . ucfirst($filters['file_type']);
    if (($filters['date_from'] ?? '') !== '') $activeFilterLabels[] = 'From: ' . $filters['date_from'];
    if (($filters['date_to'] ?? '') !== '') $activeFilterLabels[] = 'To: ' . $filters['date_to'];
    ?>

    <form action="/staff/documents" method="GET" data-ajax-form style="background:#fff;border-radius:22px;padding:18px;margin-bottom:20px;box-shadow:0 1px 2px rgba(16,54,45,.04),0 14px 34px -24px rgba(16,54,45,.3)">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
            <input type="search" name="q" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" data-ajax-search placeholder="Search filename, client, description…" aria-label="Search documents" style="padding:11px 14px;border:1.5px solid #e0e9e5;border-radius:13px;background:#fbfdfc;font-size:13.5px">
            <select name="client_id" onchange="this.form.requestSubmit()" aria-label="Filter by client" style="padding:11px 12px;border:1.5px solid #e0e9e5;border-radius:13px;background:#fbfdfc;color:#3a4d47">
                <option value="">All clients</option>
                <?php foreach ($clients as $filterClient): ?>
                    <option value="<?= (int)$filterClient['id'] ?>" <?= (int)($filters['client_id'] ?? 0) === (int)$filterClient['id'] ? 'selected' : '' ?>><?= htmlspecialchars($filterClient['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="direction" onchange="this.form.requestSubmit()" aria-label="Filter by direction" style="padding:11px 12px;border:1.5px solid #e0e9e5;border-radius:13px;background:#fbfdfc;color:#3a4d47">
                <option value="">All directions</option>
                <option value="client_upload" <?= ($filters['direction'] ?? '') === 'client_upload' ? 'selected' : '' ?>>Client uploads</option>
                <option value="from_trinova" <?= ($filters['direction'] ?? '') === 'from_trinova' ? 'selected' : '' ?>>From TriNova</option>
            </select>
            <select name="status" onchange="this.form.requestSubmit()" aria-label="Filter by status" style="padding:11px 12px;border:1.5px solid #e0e9e5;border-radius:13px;background:#fbfdfc;color:#3a4d47">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= htmlspecialchars($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="file_type" onchange="this.form.requestSubmit()" aria-label="Filter by file type" style="padding:11px 12px;border:1.5px solid #e0e9e5;border-radius:13px;background:#fbfdfc;color:#3a4d47">
                <option value="">All file types</option>
                <?php foreach (['pdf' => 'PDF', 'word' => 'Word', 'spreadsheet' => 'Spreadsheet', 'image' => 'Image', 'archive' => 'ZIP archive', 'text' => 'Text', 'other' => 'Other'] as $typeValue => $typeLabel): ?>
                    <option value="<?= $typeValue ?>" <?= ($filters['file_type'] ?? '') === $typeValue ? 'selected' : '' ?>><?= $typeLabel ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" onchange="this.form.requestSubmit()" aria-label="Uploaded from date" title="Uploaded from" style="padding:10px 12px;border:1.5px solid #e0e9e5;border-radius:13px;background:#fbfdfc;color:#3a4d47">
            <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" onchange="this.form.requestSubmit()" aria-label="Uploaded to date" title="Uploaded to" style="padding:10px 12px;border:1.5px solid #e0e9e5;border-radius:13px;background:#fbfdfc;color:#3a4d47">
            <div style="display:flex;gap:8px">
                <select name="sort" onchange="this.form.requestSubmit()" aria-label="Sort documents" style="flex:1;padding:11px 12px;border:1.5px solid #e0e9e5;border-radius:13px;background:#fbfdfc;color:#3a4d47">
                    <option value="newest" <?= ($filters['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest first</option>
                    <option value="oldest" <?= ($filters['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                    <option value="client_asc" <?= ($filters['sort'] ?? '') === 'client_asc' ? 'selected' : '' ?>>Client A–Z</option>
                    <option value="filename_asc" <?= ($filters['sort'] ?? '') === 'filename_asc' ? 'selected' : '' ?>>Filename A–Z</option>
                </select>
                <select name="per_page" onchange="this.form.requestSubmit()" aria-label="Results per page" style="width:105px;padding:11px 9px;border:1.5px solid #e0e9e5;border-radius:13px;background:#fbfdfc;color:#3a4d47">
                    <?php foreach ([10, 20, 50] as $pageSize): ?>
                        <option value="<?= $pageSize ?>" <?= (int)($pagination['per_page'] ?? 20) === $pageSize ? 'selected' : '' ?>><?= $pageSize ?> / page</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php if ($activeFilterLabels): ?>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:14px">
                <?php foreach ($activeFilterLabels as $filterLabel): ?>
                    <span style="padding:5px 10px;border-radius:999px;background:#e6ecf5;color:#41556f;font-size:11.5px;font-weight:700"><?= htmlspecialchars($filterLabel) ?></span>
                <?php endforeach; ?>
                <a href="/staff/documents" style="margin-left:auto;color:#e07d24;font-size:12.5px;font-weight:800">Reset filters</a>
            </div>
        <?php endif; ?>
    </form>

    <!-- Dispatch Document Modal -->
    <div id="dispatchModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(20,40,35,.45);backdrop-filter:blur(6px);z-index:99;align-items:center;justify-content:center;padding:20px">
        <div style="background:#fff;border-radius:24px;width:100%;max-width:540px;padding:32px;box-shadow:0 24px 60px -28px rgba(0,0,0,.4);animation:tnpop .25s ease">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
                <h3 style="margin:0;font-size:20px;font-weight:800">Dispatch Document to Client</h3>
                <button onclick="document.getElementById('dispatchModal').style.display='none'" style="background:none;border:none;font-size:24px;cursor:pointer;color:#8a9a94">&times;</button>
            </div>

            <form action="/staff/documents/upload" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Application\Core\Session::csrfToken()) ?>">
                
                <div style="margin-bottom:16px">
                    <label style="display:block;font-size:13px;font-weight:700;color:#3a4d47;margin-bottom:6px">Company or personal record</label>
                    <select name="entity_id" required style="width:100%;padding:13px 16px;border:1.5px solid #e0e9e5;border-radius:14px;font-size:14px;background:#fbfdfc">
                        <option value="">-- Choose record --</option>
                        <?php foreach ($entities as $entity): ?>
                            <option value="<?= (int)$entity['id'] ?>"><?= htmlspecialchars($entity['company_name']) ?> — <?= htmlspecialchars(ucfirst($entity['entity_scope'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom:16px">
                    <label style="display:block;font-size:13px;font-weight:700;color:#3a4d47;margin-bottom:6px">File Artifact</label>
                    <input type="file" name="file" required style="width:100%;padding:12px;border:1.5px solid #e0e9e5;border-radius:14px;font-size:13.5px;background:#fbfdfc">
                </div>

                <div style="margin-bottom:24px">
                    <label style="display:block;font-size:13px;font-weight:700;color:#3a4d47;margin-bottom:6px">Description / Note for Client</label>
                    <textarea name="description" rows="3" placeholder="e.g. Final Corporation Tax return calculation summary 2025" style="width:100%;padding:13px 16px;border:1.5px solid #e0e9e5;border-radius:14px;font-size:14px;background:#fbfdfc;resize:vertical"></textarea>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:12px">
                    <button type="button" onclick="document.getElementById('dispatchModal').style.display='none'" style="background:#f0f5f3;color:#5f726c;border:none;padding:12px 20px;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer">Cancel</button>
                    <button type="submit" style="background:#0d9488;color:#fff;border:none;padding:12px 24px;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer">Upload & Dispatch</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteDocModal" style="display:none;position:fixed;inset:0;background:rgba(20,40,35,.45);backdrop-filter:blur(6px);z-index:199;align-items:center;justify-content:center;padding:20px">
        <div style="background:#fff;border-radius:24px;width:100%;max-width:440px;padding:32px;box-shadow:0 24px 60px -28px rgba(0,0,0,.4);animation:tnpop .25s ease">
            <h3 style="margin:0 0 12px;font-size:19px;font-weight:800">Delete Document?</h3>
            <p style="color:#61756e;font-size:14px;margin:0 0 24px">This document will be soft-deleted and can be restored from the Trash workspace.</p>
            <form action="/staff/documents/delete" method="POST" data-ajax-form>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Application\Core\Session::csrfToken()) ?>">
                <input type="hidden" name="document_id" id="deleteDocId">
                <div style="display:flex;justify-content:flex-end;gap:12px">
                    <button type="button" onclick="document.getElementById('deleteDocModal').style.display='none'" style="background:#f0f5f3;color:#5f726c;border:none;padding:11px 20px;border-radius:12px;font-weight:700;cursor:pointer">Cancel</button>
                    <button type="submit" style="background:#dc2626;color:#fff;border:none;padding:11px 22px;border-radius:12px;font-weight:700;cursor:pointer">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Documents Table -->
    <div style="background:#fff;border-radius:24px;padding:26px;box-shadow:0 1px 2px rgba(16,54,45,.04),0 14px 34px -24px rgba(16,54,45,.4)">
        <!-- Bulk Action Bar -->
        <div id="docBulkBar" style="display:none;align-items:center;gap:12px;padding:12px 16px;background:#fff8ee;border-radius:14px;margin-bottom:16px;border:1px solid #f6dfc0">
            <span id="docBulkCount" style="font-weight:700;color:#e07d24;font-size:13.5px">0 selected</span>
            <form action="/staff/documents/bulk-delete" method="POST" data-ajax-form id="docBulkForm" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Application\Core\Session::csrfToken()) ?>">
                <div id="docBulkIds"></div>
                <button type="submit" onclick="return confirm('Delete all selected documents? They can be restored from Trash.')" style="background:#dc2626;color:#fff;border:none;padding:8px 18px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer">Delete Selected</button>
            </form>
            <button type="button" onclick="tnDocSelectNone()" style="background:#f0f5f3;color:#5f726c;border:none;padding:8px 14px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer">Clear</button>
        </div>
        <?php if (empty($documents)): ?>
            <div style="padding:48px 24px;text-align:center;color:#8a9a94">
                <?= $activeFilterLabels ? 'No documents match the selected filters.' : 'No documents recorded in the repository.' ?>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;text-align:left">
                    <thead>
                        <tr style="border-bottom:2px solid #eef4f1;color:#7d8e88;font-size:12.5px;text-transform:uppercase;letter-spacing:.05em">
                            <th style="padding:12px 10px;font-weight:700;width:36px"><input type="checkbox" id="docCheckAll" aria-label="Select all documents" onchange="tnDocToggleAll(this)" style="width:16px;height:16px;cursor:pointer"></th>
                            <th style="padding:12px 16px;font-weight:700">Client</th>
                            <th style="padding:12px 16px;font-weight:700">Filename</th>
                            <th style="padding:12px 16px;font-weight:700">Direction</th>
                            <th style="padding:12px 16px;font-weight:700">Status</th>
                            <th style="padding:12px 16px;font-weight:700">Uploaded By</th>
                            <th style="padding:12px 16px;font-weight:700">Date</th>
                            <th style="padding:12px 16px;font-weight:700;text-align:right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr style="border-bottom:1px solid #eef4f1">
                                <td style="padding:10px 10px"><input type="checkbox" class="tn-doc-check" value="<?= (int)$doc['id'] ?>" aria-label="Select document <?= htmlspecialchars($doc['filename']) ?>" onchange="tnDocUpdateBar()" style="width:16px;height:16px;cursor:pointer"></td>
                                <td style="padding:16px;font-weight:700;color:#213330">
                                    <?= htmlspecialchars($doc['client_name'] ?? 'Client') ?>
                                </td>
                                <td style="padding:16px;font-weight:600">
                                    <?= htmlspecialchars($doc['filename']) ?>
                                    <?php if ($doc['description']): ?>
                                        <div style="font-size:12.5px;color:#7d8e88;font-weight:400;margin-top:2px"><?= htmlspecialchars($doc['description']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:16px;white-space:nowrap;min-width:125px">
                                    <?php if ($doc['direction'] === 'client_upload'): ?>
                                        <span style="display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;line-height:1;padding:7px 12px;border-radius:999px;font-size:11.5px;font-weight:700;background:#fdecdc;color:#e07d24">Client Upload</span>
                                    <?php else: ?>
                                        <span style="display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;line-height:1;padding:7px 12px;border-radius:999px;font-size:11.5px;font-weight:700;background:#e6ecf5;color:#41556f">From TriNova</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:16px">
                                    <?php $statusReady = in_array(strtolower((string)$doc['status']), ['ready', 'completed'], true); ?>
                                    <span style="padding:4px 10px;border-radius:999px;font-size:11.5px;font-weight:700;white-space:nowrap;<?= $statusReady ? 'background:#e2f3ea;color:#3f9d6d' : 'background:#fff8ee;color:#e07d24' ?>">
                                        <?= htmlspecialchars($doc['status'] ?? 'Ready') ?>
                                    </span>
                                </td>
                                <td style="padding:16px;color:#61756e;font-size:13.5px">
                                    <?= htmlspecialchars($doc['uploaded_by_name'] ?? 'User') ?>
                                </td>
                                <td style="padding:16px;color:#7d8e88;font-size:13px">
                                    <?= date('d M Y, H:i', strtotime($doc['created_at'])) ?>
                                </td>
                                <td style="padding:16px;text-align:right;white-space:nowrap">
                                    <?php $previewable = in_array(strtolower(pathinfo((string)$doc['filename'], PATHINFO_EXTENSION)), ['pdf', 'png', 'jpg', 'jpeg', 'txt', 'csv'], true); ?>
                                    <?php if ($previewable): ?>
                                        <a href="/staff/documents/download/<?= (int)$doc['id'] ?>?preview=1" target="_blank" rel="noopener" data-no-ajax data-document-preview data-document-id="<?= (int)$doc['id'] ?>" style="background:#fff;color:#41556f;border:1px solid #dfe8e4;padding:8px 14px;border-radius:10px;font-weight:700;font-size:13px;display:inline-flex;margin-right:4px">View</a>
                                    <?php endif; ?>
                                    <?php $isPdf = strtolower(pathinfo((string)$doc['filename'], PATHINFO_EXTENSION)) === 'pdf'; ?>
                                    <?php if ($isPdf): ?>
                                        <a href="/staff/signatures/create?document_id=<?= (int)$doc['id'] ?>" style="background:#e6fffa;color:#0d9488;border:1px solid #99f6e4;padding:8px 12px;border-radius:10px;font-weight:700;font-size:13px;display:inline-flex;align-items:center;gap:4px;margin-right:4px" title="Request Digital Signature">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            Sign
                                        </a>
                                    <?php endif; ?>
                                    <a href="/documents/download/<?= $doc['id'] ?>" style="background:#f0f5f3;color:#0d9488;padding:8px 14px;border-radius:10px;font-weight:700;font-size:13px;display:inline-flex;align-items:center;gap:6px;margin-right:4px">Download</a>
                                    <button type="button" onclick="tnDocDelete(<?= (int)$doc['id'] ?>)" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:8px 12px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (($pagination['total'] ?? 0) > 0): ?>
            <?php
            $currentPage = (int)$pagination['page'];
            $totalPages = (int)$pagination['total_pages'];
            $documentQuery = array_filter([
                'q' => $filters['search'] ?? '',
                'client_id' => $filters['client_id'] ?? 0,
                'direction' => $filters['direction'] ?? '',
                'status' => $filters['status'] ?? '',
                'file_type' => $filters['file_type'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'sort' => $filters['sort'] ?? 'newest',
                'per_page' => $pagination['per_page'],
            ], static fn($value) => $value !== '' && $value !== 0);
            ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding-top:18px;margin-top:12px;border-top:1px solid #eef4f1;gap:12px;flex-wrap:wrap;color:#61756e;font-size:13px">
                <span>Showing <?= (($currentPage - 1) * $pagination['per_page']) + 1 ?>–<?= min($pagination['total'], $currentPage * $pagination['per_page']) ?> of <?= $pagination['total'] ?> documents</span>
                <div style="display:flex;gap:8px;align-items:center">
                    <?php if ($currentPage > 1): ?>
                        <a href="/staff/documents?<?= http_build_query($documentQuery + ['page' => $currentPage - 1]) ?>" style="padding:8px 12px;border-radius:10px;background:#eef4f1;color:#0d9488;font-weight:700">Previous</a>
                    <?php endif; ?>
                    <span style="padding:8px 10px;font-weight:700">Page <?= $currentPage ?> of <?= $totalPages ?></span>
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="/staff/documents?<?= http_build_query($documentQuery + ['page' => $currentPage + 1]) ?>" style="padding:8px 12px;border-radius:10px;background:#eef4f1;color:#0d9488;font-weight:700">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function tnDocDelete(id) {
    document.getElementById('deleteDocId').value = id;
    document.getElementById('deleteDocModal').style.display = 'flex';
}
function tnDocToggleAll(el) {
    document.querySelectorAll('.tn-doc-check').forEach(c => { c.checked = el.checked; });
    tnDocUpdateBar();
}
function tnDocSelectNone() {
    document.querySelectorAll('.tn-doc-check, #docCheckAll').forEach(c => { c.checked = false; });
    tnDocUpdateBar();
}
function tnDocUpdateBar() {
    const checked = [...document.querySelectorAll('.tn-doc-check:checked')];
    const bar = document.getElementById('docBulkBar');
    document.getElementById('docBulkCount').textContent = checked.length + ' selected';
    const container = document.getElementById('docBulkIds');
    container.innerHTML = '';
    checked.forEach(c => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = c.value;
        container.appendChild(inp);
    });
    bar.style.display = checked.length > 0 ? 'flex' : 'none';
}
</script>
