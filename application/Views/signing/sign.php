<?php
/**
 * TriNova Digital Signature Execution Screen
 */
$requiredFieldsCount = count(array_filter($myFields, fn($f) => (int)($f['required'] ?? $f['is_required'] ?? 1) === 1));
?>

<?php if (!$isTurn): ?>
    <!-- Sequential Signing: Not Signer's Turn -->
    <div class="max-w-xl mx-auto my-14 px-4 text-center animate-fade-in">
        <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 p-8 sm:p-12">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mb-6 ring-8 ring-amber-50/50">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-100 text-amber-800 mb-3">
                Sequential Signing In Progress
            </span>

            <h1 class="text-2xl font-extrabold text-slate-900 mb-3">
                It's Not Your Turn Yet
            </h1>
            <p class="text-slate-600 text-sm mb-6">
                Hello <strong class="text-slate-800"><?= htmlspecialchars($signer['name']) ?></strong>, this document requires sequential signatures. Prior signatories are currently reviewing and executing the agreement.
            </p>

            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 text-left text-xs text-slate-600 space-y-2 mb-6">
                <div class="flex justify-between">
                    <span class="text-slate-400">Document:</span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($request['title']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Your Turn Order:</span>
                    <span class="font-bold text-slate-800">Position <?= (int)$signer['signing_order'] ?></span>
                </div>
            </div>

            <p class="text-xs text-slate-400">
                You will automatically receive an email invitation as soon as the previous party completes their signature.
            </p>
        </div>
    </div>
<?php else: ?>
    <!-- Active Signing Session -->
    <div id="signingApp" class="flex flex-col flex-1 pb-16">
        <!-- Sticky Action Bar -->
        <div class="sticky top-16 z-30 bg-white/95 backdrop-blur border-b border-slate-200 shadow-sm px-4 sm:px-8 py-3.5 transition-all">
            <div class="max-w-6xl mx-auto flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-teal-50 border border-teal-200/70 text-teal-700 flex items-center justify-center font-bold text-sm shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                    </div>
                    <div class="truncate">
                        <h1 class="text-sm sm:text-base font-extrabold text-slate-900 truncate">
                            <?= htmlspecialchars($request['title']) ?>
                        </h1>
                        <div class="flex items-center gap-2 text-xs text-slate-500">
                            <span>Signing as: <strong class="text-slate-700"><?= htmlspecialchars($signer['name']) ?></strong></span>
                            <span>&bull;</span>
                            <span id="counterBadge" class="font-semibold text-teal-700 bg-teal-50 px-2 py-0.5 rounded-full border border-teal-200/50">
                                <span id="completedCount">0</span> of <?= count($myFields) ?> completed
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 shrink-0 self-end md:self-auto">
                    <button type="button" onclick="openDeclineModal()" class="px-3.5 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 font-semibold text-xs transition-all">
                        Decline
                    </button>
                    
                    <button type="button" id="nextFieldBtn" onclick="focusNextField()" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs flex items-center gap-1.5 transition-all">
                        Next Field &darr;
                    </button>

                    <button type="button" id="finishBtn" disabled onclick="submitSigningForm()" class="px-5 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-xs sm:text-sm shadow-md shadow-teal-600/20 flex items-center gap-2 transition-all">
                        <span id="finishBtnText">Finish & Sign Document</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Document Viewer Container -->
        <div class="max-w-4xl w-full mx-auto px-2 sm:px-4 py-6 flex-1 flex flex-col items-center">
            <!-- Loading Indicator -->
            <div id="pdfLoading" class="py-20 flex flex-col items-center gap-3 text-slate-400">
                <div class="w-8 h-8 border-3 border-teal-600 border-t-transparent rounded-full animate-spin"></div>
                <span class="text-xs font-semibold">Loading secure PDF document...</span>
            </div>

            <!-- PDF Pages Container -->
            <div id="pdfPagesContainer" class="w-full flex flex-col items-center gap-6"></div>
        </div>
    </div>

    <!-- SIGNATURE PAD MODAL -->
    <div id="signatureModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 max-w-lg w-full p-6 animate-fade-in">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                <h3 class="text-lg font-extrabold text-slate-900" id="modalTitle">Adopt Your Signature</h3>
                <button type="button" onclick="closeSignatureModal()" class="text-slate-400 hover:text-slate-600 text-xl font-bold p-1">&times;</button>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-slate-200 mb-5">
                <button type="button" onclick="switchSigTab('draw')" id="tabBtnDraw" class="flex-1 py-2.5 text-xs sm:text-sm font-bold border-b-2 border-teal-600 text-teal-600 transition-all">Draw</button>
                <button type="button" onclick="switchSigTab('type')" id="tabBtnType" class="flex-1 py-2.5 text-xs sm:text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition-all">Type</button>
                <button type="button" onclick="switchSigTab('upload')" id="tabBtnUpload" class="flex-1 py-2.5 text-xs sm:text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition-all">Upload</button>
            </div>

            <!-- Tab 1: Draw -->
            <div id="tabContentDraw" class="space-y-3">
                <div class="border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50/50 p-2 relative overflow-hidden">
                    <canvas id="signatureCanvas" width="460" height="170" class="w-full h-40 bg-white rounded-xl shadow-inner cursor-crosshair touch-none"></canvas>
                    <button type="button" onclick="clearDrawCanvas()" class="absolute top-4 right-4 text-xs font-bold text-slate-400 hover:text-rose-600 bg-white/80 px-2 py-1 rounded-md border border-slate-200">
                        Clear
                    </button>
                </div>
                <p class="text-xs text-slate-400 text-center">Draw your signature or initials above using mouse, trackpad, or touch screen.</p>
            </div>

            <!-- Tab 2: Type -->
            <div id="tabContentType" class="space-y-4 hidden">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Your Full Legal Name</label>
                    <input type="text" id="typedNameInput" value="<?= htmlspecialchars($signer['name']) ?>" oninput="updateTypedPreview()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-teal-600 focus:outline-none" />
                </div>
                <div class="border border-slate-200 rounded-2xl p-6 bg-slate-50 text-center min-h-[120px] flex items-center justify-center">
                    <div id="typedPreview" class="text-3xl sm:text-4xl text-slate-900 font-signature select-none">
                        <?= htmlspecialchars($signer['name']) ?>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-2">
                    <button type="button" onclick="setTypedStyle('font-signature')" class="px-3 py-1 rounded-lg border border-slate-200 text-xs font-semibold active:bg-slate-100">Style 1</button>
                    <button type="button" onclick="setTypedStyle('italic font-serif')" class="px-3 py-1 rounded-lg border border-slate-200 text-xs font-semibold active:bg-slate-100">Style 2</button>
                </div>
            </div>

            <!-- Tab 3: Upload -->
            <div id="tabContentUpload" class="space-y-4 hidden">
                <div class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center bg-slate-50">
                    <input type="file" id="uploadInput" accept="image/png,image/jpeg,image/svg+xml" onchange="handleSignatureUpload(event)" class="hidden" />
                    <button type="button" onclick="document.getElementById('uploadInput').click()" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                        Select Signature File
                    </button>
                    <p class="text-xs text-slate-400 mt-2">PNG or JPEG with white/transparent background recommended.</p>
                </div>
                <div id="uploadPreviewContainer" class="hidden text-center border border-slate-200 rounded-xl p-3 bg-white">
                    <img id="uploadPreviewImg" class="max-h-24 mx-auto" alt="Uploaded signature" />
                </div>
            </div>

            <!-- Modal Actions -->
            <div class="flex items-center justify-between border-t border-slate-100 pt-4 mt-5">
                <p class="text-xs text-slate-400 max-w-[200px]">By clicking Adopt, I certify this is my legal electronic signature.</p>
                <div class="flex gap-2">
                    <button type="button" onclick="closeSignatureModal()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                    <button type="button" onclick="saveSignature()" class="px-5 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold shadow-md shadow-teal-600/20">Adopt & Insert</button>
                </div>
            </div>
        </div>
    </div>

    <!-- DECLINE MODAL -->
    <div id="declineModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 max-w-md w-full p-6 animate-fade-in">
            <h3 class="text-lg font-extrabold text-slate-900 mb-2">Decline to Sign Document</h3>
            <p class="text-xs text-slate-500 mb-4">Please specify the reason why you are declining to execute this document. TriNova accounting will be notified immediately.</p>
            <form id="declineForm" onsubmit="submitDeclineForm(event)">
                <textarea id="declineReason" rows="3" required placeholder="e.g. Terms require revision, incorrect entity name..." class="w-full p-3 text-sm rounded-xl border border-slate-200 focus:outline-none focus:border-rose-500 mb-4"></textarea>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeDeclineModal()" class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">Confirm Decline</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden Form for Submission -->
    <form id="submissionForm" method="POST" action="/sign/<?= htmlspecialchars($token) ?>/submit" class="hidden">
        <input type="hidden" name="fields_json" id="submissionFieldsJson" />
        <input type="hidden" name="fields" id="submissionFields" />
    </form>

    <!-- PDF.js library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <script>
        // Data passed from backend
        const signingToken = <?= json_encode($token) ?>;
        const myFields = <?= json_encode($myFields) ?>;
        const allFields = <?= json_encode($allFields) ?>;
        const signerInfo = <?= json_encode($signer) ?>;
        const pdfUrl = '/sign/' + signingToken + '/pdf';

        // State tracking
        const fieldValues = {};
        const signatureTypes = {};
        myFields.forEach(f => {
            fieldValues[f.id] = f.signature_data || f.custom_text || f.field_value || '';
            signatureTypes[f.id] = f.signature_type || 'drawn';
        });

        let activeFieldForModal = null;
        let activeTab = 'draw';
        let typedStyleClass = 'font-signature';
        let uploadedDataUri = null;

        // Signature Canvas setup
        const sigCanvas = document.getElementById('signatureCanvas');
        const sigCtx = sigCanvas.getContext('2d');
        let isDrawing = false;
        let hasDrawn = false;

        function initDrawCanvas() {
            sigCtx.lineWidth = 2.5;
            sigCtx.lineCap = 'round';
            sigCtx.lineJoin = 'round';
            sigCtx.strokeStyle = '#0f172a';

            function getPos(e) {
                const rect = sigCanvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: (clientX - rect.left) * (sigCanvas.width / rect.width),
                    y: (clientY - rect.top) * (sigCanvas.height / rect.height)
                };
            }

            function start(e) {
                e.preventDefault();
                isDrawing = true;
                hasDrawn = true;
                const p = getPos(e);
                sigCtx.beginPath();
                sigCtx.moveTo(p.x, p.y);
            }

            function move(e) {
                if (!isDrawing) return;
                e.preventDefault();
                const p = getPos(e);
                sigCtx.lineTo(p.x, p.y);
                sigCtx.stroke();
            }

            function stop() {
                isDrawing = false;
            }

            sigCanvas.addEventListener('mousedown', start);
            sigCanvas.addEventListener('mousemove', move);
            window.addEventListener('mouseup', stop);

            sigCanvas.addEventListener('touchstart', start, { passive: false });
            sigCanvas.addEventListener('touchmove', move, { passive: false });
            window.addEventListener('touchend', stop);
        }

        function clearDrawCanvas() {
            sigCtx.clearRect(0, 0, sigCanvas.width, sigCanvas.height);
            hasDrawn = false;
        }

        function switchSigTab(tab) {
            activeTab = tab;
            ['Draw', 'Type', 'Upload'].forEach(t => {
                const isCurrent = t.toLowerCase() === tab;
                document.getElementById('tabContent' + t).classList.toggle('hidden', !isCurrent);
                const btn = document.getElementById('tabBtn' + t);
                btn.className = isCurrent 
                    ? 'flex-1 py-2.5 text-xs sm:text-sm font-bold border-b-2 border-teal-600 text-teal-600 transition-all'
                    : 'flex-1 py-2.5 text-xs sm:text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition-all';
            });
        }

        function updateTypedPreview() {
            const val = document.getElementById('typedNameInput').value || signerInfo.name;
            document.getElementById('typedPreview').textContent = val;
        }

        function setTypedStyle(styleClass) {
            typedStyleClass = styleClass;
            document.getElementById('typedPreview').className = 'text-3xl sm:text-4xl text-slate-900 select-none ' + styleClass;
        }

        function handleSignatureUpload(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(evt) {
                uploadedDataUri = evt.target.result;
                document.getElementById('uploadPreviewImg').src = uploadedDataUri;
                document.getElementById('uploadPreviewContainer').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }

        function openSignatureModal(fieldId, fieldType) {
            activeFieldForModal = fieldId;
            document.getElementById('modalTitle').textContent = (fieldType === 'initial') ? 'Adopt Your Initials' : 'Adopt Your Signature';
            clearDrawCanvas();
            document.getElementById('signatureModal').style.display = 'flex';
        }

        function closeSignatureModal() {
            document.getElementById('signatureModal').style.display = 'none';
            activeFieldForModal = null;
        }

        function saveSignature() {
            if (!activeFieldForModal) return;
            let resultDataUri = '';

            if (activeTab === 'draw') {
                if (!hasDrawn) {
                    alert('Please draw your signature before adopting.');
                    return;
                }
                resultDataUri = sigCanvas.toDataURL('image/png');
            } else if (activeTab === 'type') {
                // Convert typed font to canvas image
                const text = document.getElementById('typedNameInput').value || signerInfo.name;
                const tempCanvas = document.createElement('canvas');
                tempCanvas.width = 460;
                tempCanvas.height = 140;
                const tCtx = tempCanvas.getContext('2d');
                tCtx.fillStyle = '#0f172a';
                tCtx.font = (typedStyleClass.includes('font-signature')) ? '54px Caveat, cursive' : 'italic 46px serif';
                tCtx.textBaseline = 'middle';
                tCtx.fillText(text, 20, 70);
                resultDataUri = tempCanvas.toDataURL('image/png');
            } else if (activeTab === 'upload') {
                if (!uploadedDataUri) {
                    alert('Please choose an image file first.');
                    return;
                }
                resultDataUri = uploadedDataUri;
            }

            fieldValues[activeFieldForModal] = resultDataUri;
            signatureTypes[activeFieldForModal] = (activeTab === 'type' ? 'typed' : (activeTab === 'upload' ? 'uploaded' : 'drawn'));
            updateFieldUI(activeFieldForModal, resultDataUri);
            closeSignatureModal();
            updateProgress();
            focusNextField();
        }

        function updateFieldUI(fieldId, value) {
            const fieldEl = document.getElementById('field-widget-' + fieldId);
            if (!fieldEl) return;

            if (value && value.startsWith('data:image/')) {
                fieldEl.innerHTML = `
                    <div class="w-full h-full bg-teal-50/90 border-2 border-teal-500 rounded-lg p-1 relative flex items-center justify-center overflow-hidden shadow-sm group">
                        <img src="${value}" class="max-w-full max-h-full object-contain pointer-events-none" />
                        <span class="absolute inset-0 bg-teal-900/40 text-white font-bold text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            Change
                        </span>
                    </div>
                `;
            }
        }

        function updateProgress() {
            let completed = 0;
            myFields.forEach(f => {
                const val = fieldValues[f.id];
                if (val && String(val).trim() !== '') {
                    completed++;
                }
            });

            document.getElementById('completedCount').textContent = completed;
            const allCompleted = (completed >= myFields.length);
            const finishBtn = document.getElementById('finishBtn');
            finishBtn.disabled = !allCompleted;
        }

        function focusNextField() {
            for (const f of myFields) {
                const val = fieldValues[f.id];
                if (!val || String(val).trim() === '') {
                    const el = document.getElementById('field-widget-' + f.id);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        el.classList.add('ring-4', 'ring-teal-400');
                        setTimeout(() => el.classList.remove('ring-4', 'ring-teal-400'), 1500);
                        return;
                    }
                }
            }
        }

        function openDeclineModal() {
            document.getElementById('declineModal').style.display = 'flex';
        }

        function closeDeclineModal() {
            document.getElementById('declineModal').style.display = 'none';
        }

        function submitDeclineForm(e) {
            e.preventDefault();
            const reason = document.getElementById('declineReason').value;
            fetch('/sign/' + signingToken + '/decline', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ reason: reason })
            }).then(() => {
                window.location.reload();
            });
        }

        function submitSigningForm() {
            // Guard: verify all required fields are filled before submitting
            let missingCount = 0;
            for (const f of myFields) {
                const val = fieldValues[f.id];
                if (!val || String(val).trim() === '') {
                    missingCount++;
                }
            }

            if (missingCount > 0) {
                alert('Please sign the document by clicking the "Sign Here" box before finishing.');
                focusNextField();
                return;
            }

            const payload = [];
            for (const f of myFields) {
                payload.push({
                    field_id: f.id,
                    value: fieldValues[f.id] || '',
                    type: (f.type || f.field_type || 'signature'),
                    sig_type: (signatureTypes[f.id] || 'drawn')
                });
            }

            const jsonStr = JSON.stringify(payload);
            document.getElementById('submissionFieldsJson').value = jsonStr;
            const fieldsInput = document.getElementById('submissionFields');
            if (fieldsInput) fieldsInput.value = jsonStr;
            document.getElementById('finishBtn').disabled = true;
            document.getElementById('finishBtnText').textContent = 'Sealing Document...';
            document.getElementById('submissionForm').submit();
        }

        // PDF.js rendering pipeline
        if (typeof pdfjsLib !== 'undefined') {
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            loadPdfDocument();
        }

        async function loadPdfDocument() {
            try {
                const loadingTask = pdfjsLib.getDocument(pdfUrl);
                const pdf = await loadingTask.promise;
                document.getElementById('pdfLoading').style.display = 'none';

                const container = document.getElementById('pdfPagesContainer');
                container.innerHTML = '';

                for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                    const page = await pdf.getPage(pageNum);
                    const viewport = page.getViewport({ scale: 1.5 });

                    // Page wrapper
                    const pageWrapper = document.createElement('div');
                    pageWrapper.className = 'relative mx-auto my-3 shadow-xl bg-white border border-slate-200 rounded-xl overflow-hidden';
                    pageWrapper.style.width = viewport.width + 'px';
                    pageWrapper.style.maxWidth = '100%';

                    // Canvas
                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    canvas.className = 'w-full h-auto block';
                    pageWrapper.appendChild(canvas);

                    // Overlay layer
                    const overlay = document.createElement('div');
                    overlay.className = 'absolute inset-0 pointer-events-none';
                    pageWrapper.appendChild(overlay);

                    container.appendChild(pageWrapper);

                    const renderContext = {
                        canvasContext: canvas.getContext('2d'),
                        viewport: viewport
                    };
                    await page.render(renderContext).promise;

                    // Place fields for this page
                    renderPageFields(pageNum, overlay);
                }

                updateProgress();

                // Automatically scroll and draw attention to the first signature field
                setTimeout(() => focusNextField(), 500);
            } catch (err) {
                console.error('PDF load error:', err);
                document.getElementById('pdfLoading').innerHTML = `
                    <div class="text-center p-8 bg-rose-50 border border-rose-200 rounded-2xl max-w-md">
                        <p class="text-sm font-bold text-rose-700 mb-2">Unable to render PDF preview</p>
                        <p class="text-xs text-rose-600 mb-4">You can download and review the document directly.</p>
                        <a href="/sign/${signingToken}/download" class="inline-block px-4 py-2 bg-slate-800 text-white text-xs font-bold rounded-lg">Download PDF</a>
                    </div>
                `;
            }
        }

        function renderPageFields(pageNum, overlayContainer) {
            const pageFields = myFields.filter(f => parseInt(f.page || f.page_number || 1) === pageNum);

            pageFields.forEach(f => {
                const widget = document.createElement('div');
                widget.id = 'field-widget-' + f.id;
                widget.className = 'absolute pointer-events-auto cursor-pointer transition-all';
                widget.style.left = (f.position_x || 10) + '%';
                widget.style.top = (f.position_y || 80) + '%';
                widget.style.width = (f.width || 24) + '%';
                widget.style.height = (f.height || 8) + '%';

                const fieldType = f.type || f.field_type || 'signature';
                const isSigType = (fieldType === 'signature' || fieldType === 'initial' || fieldType === 'initials');

                if (isSigType) {
                    const label = (fieldType === 'initial' || fieldType === 'initials') ? 'Initial' : 'Sign';
                    widget.innerHTML = `
                        <div class="w-full h-full bg-teal-500/10 hover:bg-teal-500/20 border-2 border-teal-600 border-dashed rounded-lg flex items-center justify-center text-teal-800 gap-1.5 p-1 shadow-sm transition-all group animate-pulse">
                            <svg class="w-4 h-4 text-teal-600 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                            <span class="text-xs font-extrabold uppercase tracking-wider">${label} Here</span>
                        </div>
                    `;
                    widget.onclick = () => openSignatureModal(f.id, fieldType);
                } else if (fieldType === 'date') {
                    const today = new Date().toISOString().split('T')[0];
                    if (!fieldValues[f.id]) {
                        fieldValues[f.id] = today;
                    }
                    widget.innerHTML = `
                        <input type="date" value="${fieldValues[f.id]}" onchange="fieldValues[${f.id}] = this.value; updateProgress()" class="w-full h-full text-xs font-bold text-slate-800 bg-white/95 border border-slate-300 rounded px-2 shadow-sm focus:outline-none focus:border-teal-600" />
                    `;
                } else if (fieldType === 'text') {
                    widget.innerHTML = `
                        <input type="text" value="${fieldValues[f.id] || ''}" placeholder="${f.custom_text || f.custom_label || 'Enter text...'}" oninput="fieldValues[${f.id}] = this.value; updateProgress()" class="w-full h-full text-xs text-slate-800 bg-white/95 border border-slate-300 rounded px-2 shadow-sm focus:outline-none focus:border-teal-600" />
                    `;
                } else if (fieldType === 'checkbox') {
                    const isChecked = fieldValues[f.id] ? 'checked' : '';
                    widget.innerHTML = `
                        <div class="w-full h-full flex items-center justify-center bg-white/80 border border-slate-300 rounded">
                            <input type="checkbox" ${isChecked} onchange="fieldValues[${f.id}] = this.checked ? '1' : ''; updateProgress()" class="w-5 h-5 text-teal-600 rounded cursor-pointer" />
                        </div>
                    `;
                }

                overlayContainer.appendChild(widget);

                // If existing value already present, render it
                if (fieldValues[f.id]) {
                    updateFieldUI(f.id, fieldValues[f.id]);
                }
            });

            // Display placeholder boxes for other signers
            const otherFields = allFields.filter(f => parseInt(f.page || f.page_number || 1) === pageNum && parseInt(f.signer_id) !== parseInt(signerInfo.id));
            otherFields.forEach(f => {
                const widget = document.createElement('div');
                widget.className = 'absolute pointer-events-none opacity-60';
                widget.style.left = (f.position_x || 10) + '%';
                widget.style.top = (f.position_y || 80) + '%';
                widget.style.width = (f.width || 24) + '%';
                widget.style.height = (f.height || 8) + '%';
                widget.innerHTML = `
                    <div class="w-full h-full bg-slate-200/50 border border-slate-300 border-dashed rounded-lg flex items-center justify-center text-slate-500 text-[10px] font-semibold">
                        Awaiting Co-Signer
                    </div>
                `;
                overlayContainer.appendChild(widget);
            });
        }

        // Init draw canvas listeners
        initDrawCanvas();
    </script>
<?php endif; ?>
