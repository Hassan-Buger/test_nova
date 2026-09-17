<div class="tn-screen" style="max-width:960px">
    <div style="margin-bottom:24px">
        <a href="/staff/signatures" style="color:#64748b;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;font-weight:600;margin-bottom:8px">
            &larr; Back to Digital Signatures
        </a>
        <h1 style="margin:0 0 6px;font-size:24px;font-weight:800;color:#1e293b">Create Signature Request</h1>
        <p style="margin:0;color:#61756e;font-size:14.5px">Prepare a document for electronic signature, configure signatories, and dispatch signing invitations.</p>
    </div>

    <form id="createSigForm" action="/staff/signatures/create" method="POST" style="background:#fff;border-radius:24px;padding:32px;box-shadow:0 1px 2px rgba(16,54,45,.04),0 14px 34px -24px rgba(16,54,45,.3)">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Application\Core\Session::csrfToken()) ?>">
        <input type="hidden" name="signers" id="signersJsonInput" />
        <input type="hidden" name="fields" id="fieldsJsonInput" />

        <!-- Document Selection -->
        <div style="margin-bottom:24px">
            <label style="display:block;font-size:13.5px;font-weight:700;color:#1e293b;margin-bottom:8px">1. Select PDF Document <span style="color:#ef4444">*</span></label>
            <select name="document_id" id="documentSelect" required onchange="handleDocChange(this)" style="width:100%;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:14px;font-size:14px;background:#f8fafc;color:#1e293b">
                <option value="">-- Choose an uploaded PDF --</option>
                <?php foreach ($pdfDocs as $doc): ?>
                    <option value="<?= (int)$doc['id'] ?>" <?= ($selectedDoc && (int)$selectedDoc['id'] === (int)$doc['id']) ? 'selected' : '' ?> data-filename="<?= htmlspecialchars($doc['filename']) ?>" data-client="<?= htmlspecialchars($doc['client_name'] ?? '') ?>">
                        <?= htmlspecialchars($doc['filename']) ?> (Client: <?= htmlspecialchars($doc['client_name'] ?? 'Unknown') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <span style="font-size:12px;color:#64748b;display:block;margin-top:4px">Only PDF files can be sealed with legal digital signatures.</span>
        </div>

        <!-- Request Title -->
        <div style="margin-bottom:24px">
            <label style="display:block;font-size:13.5px;font-weight:700;color:#1e293b;margin-bottom:8px">2. Request Title <span style="color:#ef4444">*</span></label>
            <input type="text" name="title" id="requestTitle" required placeholder="e.g. FY24 Engagement Letter Execution" value="<?= $selectedDoc ? 'Signature Request: ' . htmlspecialchars($selectedDoc['filename']) : '' ?>" style="width:100%;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:14px;font-size:14px;background:#f8fafc;color:#1e293b" />
        </div>

        <!-- Workflow & Settings Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px">
            <div>
                <label style="display:block;font-size:13.5px;font-weight:700;color:#1e293b;margin-bottom:8px">Signing Workflow</label>
                <select name="signing_order" style="width:100%;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:14px;font-size:14px;background:#f8fafc;color:#1e293b">
                    <option value="parallel">Parallel (All parties can sign simultaneously)</option>
                    <option value="sequential">Sequential (Signatories must sign in strict order)</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:13.5px;font-weight:700;color:#1e293b;margin-bottom:8px">Expiration Date (Optional)</label>
                <input type="date" name="expires_at" min="<?= date('Y-m-d') ?>" style="width:100%;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:14px;font-size:14px;background:#f8fafc;color:#1e293b" />
            </div>
        </div>

        <hr style="border:0;border-top:1px solid #f1f5f9;margin:28px 0" />

        <!-- Signatories Configuration -->
        <div style="margin-bottom:28px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
                <div>
                    <h3 style="margin:0 0 4px;font-size:16px;font-weight:800;color:#1e293b">3. Authorized Signatories</h3>
                    <p style="margin:0;font-size:12.5px;color:#64748b">Specify individuals who are authorized to sign this legal agreement.</p>
                </div>
                <button type="button" onclick="addSignerRow()" style="background:#f0fdfa;color:#0d9488;border:1.5px solid #ccfbf1;padding:8px 16px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer">
                    + Add Signer
                </button>
            </div>

            <?php if (!empty($suggestedSigners)): ?>
                <div style="background:#f0fdfa;border:1px solid #ccfbf1;border-radius:14px;padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                    <div style="font-size:12.5px;color:#0f766e">
                        <strong>Suggested from Entity:</strong> <?= count($suggestedSigners) ?> directors/contacts detected.
                    </div>
                    <button type="button" onclick="useSuggestedSigners()" style="background:#0d9488;color:#fff;border:0;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer">
                        Populate All Suggestions
                    </button>
                </div>
            <?php endif; ?>

            <div id="signersContainer" style="display:flex;flex-direction:column;gap:12px">
                <!-- Rows injected by JavaScript -->
            </div>
        </div>

        <hr style="border:0;border-top:1px solid #f1f5f9;margin:28px 0" />

        <!-- Field Placement Configuration -->
        <div style="margin-bottom:28px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
                <div>
                    <h3 style="margin:0 0 4px;font-size:16px;font-weight:800;color:#1e293b">4. Signature Field Positioning</h3>
                    <p style="margin:0;font-size:12.5px;color:#64748b">Fields dictate where signatures, dates, and names appear on the executed PDF.</p>
                </div>
                <button type="button" onclick="addFieldRow()" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:8px 14px;border-radius:10px;font-weight:700;font-size:12.5px;cursor:pointer">
                    + Add Custom Field
                </button>
            </div>

            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:14px;margin-bottom:14px;font-size:12.5px;color:#475569">
                <strong style="color:#0f766e">Documenso Smart Placement:</strong> If no custom fields are entered below, signature and date boxes will be automatically placed in a clean footer grid on the final execution page of your document.
            </div>

            <div id="fieldsContainer" style="display:flex;flex-direction:column;gap:10px">
                <!-- Field rows -->
            </div>
        </div>

        <!-- Immediate Send Option -->
        <div style="margin-bottom:28px;background:#f8fafc;padding:16px;border-radius:14px;border:1px solid #e2e8f0">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
                <input type="checkbox" name="send_now" value="1" checked style="width:18px;height:18px;accent-color:#0d9488" />
                <span style="font-size:13.5px;font-weight:700;color:#1e293b">Send invitation emails immediately to signers upon creation</span>
            </label>
            <p style="margin:4px 0 0 28px;font-size:12px;color:#64748b">Signers will receive a direct, legally secure one-time link requiring no account registration.</p>
        </div>

        <!-- Submit Buttons -->
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px">
            <a href="/staff/signatures" style="padding:12px 20px;border-radius:12px;background:#f1f5f9;color:#475569;font-weight:700;font-size:14px;text-decoration:none">
                Cancel
            </a>
            <button type="button" onclick="submitCreateForm()" style="padding:12px 28px;border-radius:12px;background:#0d9488;color:#fff;font-weight:700;font-size:14px;border:none;cursor:pointer;box-shadow:0 8px 18px -8px rgba(13,148,136,.7)">
                Create & Dispatch Request
            </button>
        </div>
    </form>
</div>

<script>
    const suggestedSignersData = <?= json_encode($suggestedSigners ?? []) ?>;

    function handleDocChange(el) {
        const opt = el.options[el.selectedIndex];
        const filename = opt.getAttribute('data-filename');
        if (filename && !document.getElementById('requestTitle').value) {
            document.getElementById('requestTitle').value = 'Signature Request: ' + filename;
        }
    }

    let signerRowCount = 0;
    function addSignerRow(name = '', email = '', role = 'signer', order = 1) {
        signerRowCount++;
        const id = 'signer_' + signerRowCount;
        const div = document.createElement('div');
        div.id = id;
        div.className = 'signer-row';
        div.style.cssText = 'display:grid;grid-template-columns:1.5fr 2fr 1fr 0.8fr 40px;gap:10px;align-items:center;background:#f8fafc;padding:12px;border-radius:12px;border:1px solid #e2e8f0';
        div.innerHTML = `
            <input type="text" placeholder="Signer Full Name *" value="${name}" required class="signer-name" style="padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;background:#fff" />
            <input type="email" placeholder="Email Address *" value="${email}" required class="signer-email" style="padding:10px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;background:#fff" />
            <select class="signer-role" style="padding:10px 8px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;background:#fff">
                <option value="signer" ${role === 'signer' ? 'selected' : ''}>Signer</option>
                <option value="approver" ${role === 'approver' ? 'selected' : ''}>Approver</option>
                <option value="viewer" ${role === 'viewer' ? 'selected' : ''}>Viewer (CC)</option>
            </select>
            <input type="number" min="1" value="${order}" title="Order Priority" placeholder="Order" class="signer-order" style="padding:10px 8px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;background:#fff;text-align:center" />
            <button type="button" onclick="document.getElementById('${id}').remove()" style="background:#fee2e2;color:#dc2626;border:0;width:34px;height:34px;border-radius:8px;cursor:pointer;font-weight:700">&times;</button>
        `;
        document.getElementById('signersContainer').appendChild(div);
    }

    function useSuggestedSigners() {
        document.getElementById('signersContainer').innerHTML = '';
        signerRowCount = 0;
        suggestedSignersData.forEach((s, idx) => {
            addSignerRow(s.name, s.email, s.role || 'signer', idx + 1);
        });
    }

    let fieldRowCount = 0;
    function addFieldRow(page = 1, posX = 15, posY = 75, width = 30, height = 8, type = 'signature') {
        fieldRowCount++;
        const id = 'field_' + fieldRowCount;
        const div = document.createElement('div');
        div.id = id;
        div.className = 'field-row';
        div.style.cssText = 'display:grid;grid-template-columns:1fr 0.8fr 0.8fr 0.8fr 0.8fr 40px;gap:8px;align-items:center;background:#fff;padding:10px;border-radius:10px;border:1px solid #e2e8f0;font-size:12px';
        div.innerHTML = `
            <select class="field-type" style="padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px">
                <option value="signature" ${type === 'signature' ? 'selected' : ''}>Signature Box</option>
                <option value="initial" ${type === 'initial' ? 'selected' : ''}>Initials Box</option>
                <option value="date" ${type === 'date' ? 'selected' : ''}>Signing Date</option>
                <option value="text" ${type === 'text' ? 'selected' : ''}>Text Input</option>
                <option value="checkbox" ${type === 'checkbox' ? 'selected' : ''}>Checkbox</option>
            </select>
            <input type="number" min="1" placeholder="Page #" value="${page}" class="field-page" style="padding:8px;border:1px solid #cbd5e1;border-radius:6px;text-align:center" />
            <input type="number" min="0" max="100" placeholder="X %" value="${posX}" class="field-posx" style="padding:8px;border:1px solid #cbd5e1;border-radius:6px;text-align:center" />
            <input type="number" min="0" max="100" placeholder="Y %" value="${posY}" class="field-posy" style="padding:8px;border:1px solid #cbd5e1;border-radius:6px;text-align:center" />
            <span style="font-size:11px;color:#64748b;text-align:center">Signer 1</span>
            <button type="button" onclick="document.getElementById('${id}').remove()" style="background:#fee2e2;color:#dc2626;border:0;width:30px;height:30px;border-radius:6px;cursor:pointer;font-weight:700">&times;</button>
        `;
        document.getElementById('fieldsContainer').appendChild(div);
    }

    function submitCreateForm() {
        // Collect signers
        const signerRows = document.querySelectorAll('.signer-row');
        if (signerRows.length === 0) {
            alert('Please add at least one authorized signer.');
            return;
        }

        const signers = [];
        let valid = true;
        signerRows.forEach((row, idx) => {
            const name = row.querySelector('.signer-name').value.trim();
            const email = row.querySelector('.signer-email').value.trim();
            const role = row.querySelector('.signer-role').value;
            const order = parseInt(row.querySelector('.signer-order').value) || (idx + 1);

            if (!name || !email) {
                valid = false;
            }
            signers.push({
                name: name,
                email: email,
                role: role,
                signing_order: order
            });
        });

        if (!valid) {
            alert('Please fill out all signer names and email addresses.');
            return;
        }

        // Collect custom fields (if any)
        const fieldRows = document.querySelectorAll('.field-row');
        const fields = [];
        fieldRows.forEach((row) => {
            fields.push({
                field_type: row.querySelector('.field-type').value,
                page_number: parseInt(row.querySelector('.field-page').value) || 1,
                position_x: parseFloat(row.querySelector('.field-posx').value) || 10,
                position_y: parseFloat(row.querySelector('.field-posy').value) || 80,
                width: 30,
                height: 8,
                signer_email: signers[0].email // assign to first signer by default
            });
        });

        document.getElementById('signersJsonInput').value = JSON.stringify(signers);
        document.getElementById('fieldsJsonInput').value = JSON.stringify(fields);

        document.getElementById('createSigForm').submit();
    }

    // Default initialization: populate initial signer row if none
    if (suggestedSignersData.length > 0) {
        useSuggestedSigners();
    } else {
        addSignerRow();
    }
</script>
