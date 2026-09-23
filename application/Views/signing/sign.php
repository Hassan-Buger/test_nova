<?php
/**
 * TriNova Digital Signature Execution Screen
 */
$actionableFields = array_values(array_filter($myFields, fn($f) => ($f['type'] ?? $f['field_type'] ?? '') !== 'date'));
$requiredFieldsCount = count($actionableFields) ?: 1;
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
                                <span id="completedCount">0</span> of <span id="totalFieldsCount"><?= $requiredFieldsCount ?></span> completed
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

                    <button type="button" id="finishBtn" onclick="submitSigningForm()" class="px-5 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-xs sm:text-sm shadow-md shadow-teal-600/20 flex items-center gap-2 transition-all">
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
        let myFields = <?= json_encode($myFields) ?>;
        const allFields = <?= json_encode($allFields) ?>;
        const signerInfo = <?= json_encode($signer) ?>;
        const pdfUrl = '/sign/' + signingToken + '/pdf';

        // Current date formatted in standard DD/MM/YYYY
        const today = new Date();
        const dd = String(today.getDate()).padStart(2, '0');
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const yyyy = today.getFullYear();
        const presentDateFormatted = `${dd}/${mm}/${yyyy}`;

        // Safeguard: Ensure fields exist and are normalized
        if (!Array.isArray(myFields) || myFields.length === 0) {
            myFields = [{
                id: 'auto_sig_' + (signerInfo.id || '1'),
                type: 'signature',
                page: 1,
                position_x: 9.5,
                position_y: 74.0,
                width: 28.0,
                height: 5.6,
                required: 1,
                is_auto: true
            }, {
                id: 'auto_date_' + (signerInfo.id || '1'),
                type: 'date',
                page: 1,
                position_x: 55.0,
                position_y: 80.0,
                width: 35.0,
                height: 3.5,
                required: 0,
                is_auto: true
            }];
        }

        // Align coordinates directly inside the printed "AUTHORIZED SIGNATURE AREA" on Page 1
        myFields.forEach(f => {
            const fType = f.type || f.field_type || 'signature';
            f.type = fType;
            f.page = 1;

            if (fType === 'signature' || fType === 'initial' || fType === 'initials') {
                f.position_x = 9.5;
                f.position_y = 74.0;
                f.width = 28.0;
                f.height = 5.6;
                f.required = 1;
            } else if (fType === 'date') {
                f.position_x = 55.0;
                f.position_y = 80.0;
                f.width = 35.0;
                f.height = 3.5;
                f.required = 0; // Handled automatically by the system
            } else {
                f.required = (f.required !== undefined) ? parseInt(f.required) : ((f.is_required !== undefined) ? parseInt(f.is_required) : 1);
            }
        });

        // Only count fields requiring user action (signature)
        const actionableFields = myFields.filter(f => (f.type || f.field_type) !== 'date');
        const totalCountEl = document.getElementById('totalFieldsCount');
        if (totalCountEl) totalCountEl.textContent = actionableFields.length || 1;

        // State tracking
        const fieldValues = {};
        const signatureTypes = {};

        myFields.forEach(f => {
            const fType = f.type || 'signature';
            if (fType === 'date') {
                fieldValues[f.id] = presentDateFormatted;
            } else {
                fieldValues[f.id] = f.signature_data || f.field_value || f.custom_text || '';
            }
            if (f.signature_type) {
                signatureTypes[f.id] = f.signature_type;
            }
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

        function resizeCanvas() {
            const rect = sigCanvas.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                const dpr = window.devicePixelRatio || 1;
                sigCanvas.width = rect.width * dpr;
                sigCanvas.height = rect.height * dpr;
                sigCtx.scale(dpr, dpr);
                sigCtx.lineWidth = 2.5;
                sigCtx.lineCap = 'round';
                sigCtx.lineJoin = 'round';
                sigCtx.strokeStyle = '#0f172a';
            }
        }

        function initDrawCanvas() {
            function getPos(e) {
                const rect = sigCanvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: clientX - rect.left,
                    y: clientY - rect.top
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
            if (tab === 'type') {
                updateTypedPreview();
            }
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
            document.getElementById('modalTitle').textContent = (fieldType === 'initial' || fieldType === 'initials') ? 'Adopt Your Initials' : 'Adopt Your Signature';
            document.getElementById('signatureModal').style.display = 'flex';
            
            switchSigTab(activeTab || 'draw');
            requestAnimationFrame(() => {
                resizeCanvas();
                clearDrawCanvas();
            });
        }

        function closeSignatureModal() {
            document.getElementById('signatureModal').style.display = 'none';
            activeFieldForModal = null;
        }

        function cropCanvas(sourceCanvas) {
            const ctx = sourceCanvas.getContext('2d');
            const width = sourceCanvas.width;
            const height = sourceCanvas.height;
            const imgData = ctx.getImageData(0, 0, width, height);
            const data = imgData.data;

            let minX = width, minY = height, maxX = 0, maxY = 0;
            let found = false;

            for (let y = 0; y < height; y++) {
                for (let x = 0; x < width; x++) {
                    const alpha = data[(y * width + x) * 4 + 3];
                    if (alpha > 15) {
                        found = true;
                        if (x < minX) minX = x;
                        if (x > maxX) maxX = x;
                        if (y < minY) minY = y;
                        if (y > maxY) maxY = y;
                    }
                }
            }

            if (!found) return sourceCanvas;

            const pad = 8;
            minX = Math.max(0, minX - pad);
            minY = Math.max(0, minY - pad);
            maxX = Math.min(width, maxX + pad);
            maxY = Math.min(height, maxY + pad);

            const cropW = Math.max(1, maxX - minX);
            const cropH = Math.max(1, maxY - minY);

            const cropped = document.createElement('canvas');
            cropped.width = cropW;
            cropped.height = cropH;
            const cCtx = cropped.getContext('2d');
            cCtx.drawImage(sourceCanvas, minX, minY, cropW, cropH, 0, 0, cropW, cropH);
            return cropped;
        }

        function saveSignature() {
            if (!activeFieldForModal) return;
            let resultDataUri = '';

            if (activeTab === 'draw') {
                if (!hasDrawn) {
                    alert('Please draw your signature before adopting.');
                    return;
                }
                resultDataUri = cropCanvas(sigCanvas).toDataURL('image/png');
                signatureTypes[activeFieldForModal] = 'drawn';
            } else if (activeTab === 'type') {
                const text = document.getElementById('typedNameInput').value.trim() || signerInfo.name;
                const font = (typedStyleClass.includes('font-signature')) ? '32px "Caveat", cursive' : 'italic 26px serif';
                const tempCanvas = document.createElement('canvas');
                const tCtx = tempCanvas.getContext('2d');
                tCtx.font = font;
                const metrics = tCtx.measureText(text);
                const textWidth = Math.max(80, Math.ceil(metrics.width) + 20);
                const textHeight = 52;
                tempCanvas.width = textWidth;
                tempCanvas.height = textHeight;
                tCtx.font = font;
                tCtx.fillStyle = '#0f172a';
                tCtx.textBaseline = 'middle';
                tCtx.fillText(text, 10, textHeight / 2);
                resultDataUri = tempCanvas.toDataURL('image/png');
                signatureTypes[activeFieldForModal] = 'typed';
            } else if (activeTab === 'upload') {
                if (!uploadedDataUri) {
                    alert('Please select a signature image file first.');
                    return;
                }
                resultDataUri = uploadedDataUri;
                signatureTypes[activeFieldForModal] = 'uploaded';
            }

            fieldValues[activeFieldForModal] = resultDataUri;
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
                    <div class="w-full h-full bg-white/95 border-2 border-teal-600 rounded-lg p-0.5 relative flex items-center justify-center overflow-hidden shadow-sm group cursor-pointer hover:border-teal-700 transition-all">
                        <img src="${value}" class="max-w-full max-h-full object-contain pointer-events-none" />
                        <span class="absolute inset-0 bg-teal-950/70 text-white font-bold text-[10px] flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-[1px]">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            Edit
                        </span>
                    </div>
                `;
            }
        }

        function updateProgress() {
            let completed = 0;
            actionableFields.forEach(f => {
                const val = fieldValues[f.id];
                if (val && String(val).trim() !== '') {
                    completed++;
                }
            });

            const countEl = document.getElementById('completedCount');
            if (countEl) countEl.textContent = completed;

            const allCompleted = (completed >= actionableFields.length);
            const finishBtn = document.getElementById('finishBtn');
            if (finishBtn) {
                finishBtn.disabled = !allCompleted;
                if (allCompleted) {
                    finishBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    finishBtn.classList.add('animate-pulse');
                } else {
                    finishBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    finishBtn.classList.remove('animate-pulse');
                }
            }
        }

        function focusNextField() {
            for (const f of actionableFields) {
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
            const payload = [];
            for (const f of myFields) {
                payload.push({
                    field_id: f.id,
                    value: fieldValues[f.id] || ((f.type === 'date') ? presentDateFormatted : ''),
                    type: (f.type || f.field_type || 'signature'),
                    signature_type: (signatureTypes[f.id] || 'drawn'),
                    page: 1,
                    position_x: f.position_x,
                    position_y: f.position_y,
                    width: f.width,
                    height: f.height
                });
            }

            const jsonStr = JSON.stringify(payload);
            document.getElementById('submissionFieldsJson').value = jsonStr;
            const fieldsInput = document.getElementById('submissionFields');
            if (fieldsInput) fieldsInput.value = jsonStr;

            const finishBtn = document.getElementById('finishBtn');
            finishBtn.disabled = true;
            finishBtn.classList.remove('animate-pulse');
            document.getElementById('finishBtnText').innerHTML = '<span class="inline-block animate-spin mr-1">&#9696;</span> Sealing Document...';
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

                // Keep fields strictly inside Authorized Signature Area on Page 1
                myFields.forEach(f => {
                    f.page = 1;
                });

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
                // Focus first pending field
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
                widget.style.left = f.position_x + '%';
                widget.style.top = f.position_y + '%';
                widget.style.width = f.width + '%';
                widget.style.height = f.height + '%';

                const fieldType = f.type || f.field_type || 'signature';
                const isSigType = (fieldType === 'signature' || fieldType === 'initial' || fieldType === 'initials');

                if (isSigType) {
                    const actionLabel = (fieldType === 'initial' || fieldType === 'initials') ? 'Initial' : 'Sign';
                    widget.innerHTML = `
                        <div class="w-full h-full bg-teal-500/15 hover:bg-teal-500/25 border-2 border-teal-600 border-dashed rounded-lg flex items-center justify-center text-teal-950 gap-1 px-1.5 py-0.5 shadow-xs transition-all group animate-pulse hover:border-teal-700 hover:shadow cursor-pointer">
                            <svg class="w-3.5 h-3.5 text-teal-600 group-hover:scale-110 transition-transform shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                            <div class="flex flex-col items-start leading-none">
                                <span class="text-[10px] font-black uppercase tracking-wider text-teal-950">Click to ${actionLabel}</span>
                                <span class="text-[8.5px] text-teal-700 font-semibold mt-0.5">Draw, Type or Upload</span>
                            </div>
                        </div>
                    `;
                    widget.onclick = (e) => {
                        e.stopPropagation();
                        openSignatureModal(f.id, fieldType);
                    };
                } else if (fieldType === 'date') {
                    // Non-manual automated date display matching light grey template styling
                    widget.className = 'absolute pointer-events-none select-none';
                    widget.innerHTML = `
                        <div class="w-full h-full flex items-center justify-end text-[11px] font-normal text-slate-400">
                            Date: ${presentDateFormatted}
                        </div>
                    `;
                } else if (fieldType === 'text') {
                    widget.innerHTML = `
                        <input type="text" value="${fieldValues[f.id] || ''}" placeholder="${f.custom_text || f.custom_label || 'Enter text...'}" oninput="fieldValues['${f.id}'] = this.value; updateProgress()" class="w-full h-full text-xs text-slate-800 bg-white/95 border border-slate-300 rounded px-2 shadow-sm focus:outline-none focus:border-teal-600" />
                    `;
                } else if (fieldType === 'checkbox') {
                    const isChecked = fieldValues[f.id] ? 'checked' : '';
                    widget.innerHTML = `
                        <div class="w-full h-full flex items-center justify-center bg-white/80 border border-slate-300 rounded">
                            <input type="checkbox" ${isChecked} onchange="fieldValues['${f.id}'] = this.checked ? '1' : ''; updateProgress()" class="w-5 h-5 text-teal-600 rounded cursor-pointer" />
                        </div>
                    `;
                }

                overlayContainer.appendChild(widget);

                // If existing value already present, render it
                if (fieldValues[f.id] && String(fieldValues[f.id]).startsWith('data:image/')) {
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
