/**
 * Client-side PDF normalizer (2-tier):
 *   Tier 1: Copy all pages into a brand-new pdf-lib document with { useObjectStreams: false }
 *           and %PDF-1.4 header (preserves crisp vector text & small file size).
 *   Tier 2: If pdf-lib fails to load/copy (e.g. encrypted or malformed SMSS PDF),
 *           render each page with pdf.js to a high-DPI JPEG canvas and rebuild a
 *           100% pure PDF 1.4 via pdf-lib.
 *
 * Guarantees FPDI (free parser) can read and stamp PDFs from Windows, macOS, and mobile.
 */
(function () {
    'use strict';

    function getStatusEl(input) {
        let el = input.parentElement ? input.parentElement.querySelector('.pdf-normalize-status') : null;
        if (!el && input.parentElement) {
            el = document.createElement('div');
            el.className = 'pdf-normalize-status small mt-1';
            input.insertAdjacentElement('afterend', el);
        }
        return el;
    }

    function setStatus(input, html, cls) {
        const el = getStatusEl(input);
        if (!el) return;
        el.className = 'pdf-normalize-status small mt-1 ' + (cls || 'text-muted');
        el.innerHTML = html;
    }

    function forcePdf14Header(bytes) {
        if (
            bytes.length >= 8 &&
            bytes[0] === 0x25 && // %
            bytes[1] === 0x50 && // P
            bytes[2] === 0x44 && // D
            bytes[3] === 0x46 && // F
            bytes[4] === 0x2d    // -
        ) {
            bytes[5] = 0x31; // 1
            bytes[6] = 0x2e; // .
            bytes[7] = 0x34; // 4
        }
        return bytes;
    }

    function containsUnsupportedXrefStream(bytes) {
        // Quick ASCII scan of tail (last 4KB) for /Type /XRef or /ObjStm
        const tailStart = Math.max(0, bytes.length - 4096);
        let tail = '';
        for (let i = tailStart; i < bytes.length; i++) {
            tail += String.fromCharCode(bytes[i]);
        }
        return /\/Type\s*\/XRef\b/.test(tail);
    }

    async function normalizeViaPdfLibCopy(buffer) {
        if (!window.PDFLib || !window.PDFLib.PDFDocument) {
            throw new Error('PDFLib not loaded');
        }
        const srcDoc = await window.PDFLib.PDFDocument.load(buffer, {
            ignoreEncryption: true,
            updateMetadata: false,
        });
        const newDoc = await window.PDFLib.PDFDocument.create();
        const pageIndices = srcDoc.getPageIndices();
        if (!pageIndices || pageIndices.length === 0) {
            throw new Error('PDF has no pages');
        }
        const copiedPages = await newDoc.copyPages(srcDoc, pageIndices);
        copiedPages.forEach(function (page) {
            newDoc.addPage(page);
        });

        const bytes = forcePdf14Header(await newDoc.save({
            useObjectStreams: false,
            addDefaultPage: false,
        }));

        if (containsUnsupportedXrefStream(bytes)) {
            throw new Error('Normalized output still contains XRef stream');
        }
        return bytes;
    }

    async function normalizeViaPdfJsRaster(buffer, workerSrc) {
        if (!window.pdfjsLib || !window.PDFLib || !window.PDFLib.PDFDocument) {
            throw new Error('pdfjsLib or PDFLib not loaded');
        }
        if (workerSrc && window.pdfjsLib.GlobalWorkerOptions) {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = workerSrc;
        }

        const loadingTask = window.pdfjsLib.getDocument({ data: new Uint8Array(buffer) });
        const pdf = await loadingTask.promise;
        const newDoc = await window.PDFLib.PDFDocument.create();

        for (let pageNo = 1; pageNo <= pdf.numPages; pageNo++) {
            const page = await pdf.getPage(pageNo);
            const baseViewport = page.getViewport({ scale: 1.0 });
            const scale = 2.0; // ~144-150 DPI, sharp and compact
            const viewport = page.getViewport({ scale: scale });

            const canvas = document.createElement('canvas');
            canvas.width = Math.round(viewport.width);
            canvas.height = Math.round(viewport.height);
            const ctx = canvas.getContext('2d', { alpha: false });
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            await page.render({ canvasContext: ctx, viewport: viewport }).promise;

            const jpegDataUrl = canvas.toDataURL('image/jpeg', 0.90);
            const jpegBytes = await fetch(jpegDataUrl).then(function (r) { return r.arrayBuffer(); });
            const jpgImage = await newDoc.embedJpg(jpegBytes);

            const outPage = newDoc.addPage([baseViewport.width, baseViewport.height]);
            outPage.drawImage(jpgImage, {
                x: 0,
                y: 0,
                width: baseViewport.width,
                height: baseViewport.height,
            });
        }

        return forcePdf14Header(await newDoc.save({
            useObjectStreams: false,
            addDefaultPage: false,
        }));
    }

    async function normalizePdfFile(file, workerSrc) {
        if (!file || (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf'))) {
            return { file: file, normalized: false, method: 'skip' };
        }

        const buffer = await file.arrayBuffer();

        // Tier 1: Vector copy via pdf-lib
        try {
            const bytes = await normalizeViaPdfLibCopy(buffer.slice(0));
            return {
                file: new File([bytes], file.name, { type: 'application/pdf', lastModified: Date.now() }),
                normalized: true,
                method: 'pdf-lib-copy',
            };
        } catch (err1) {
            console.warn('[pdf-normalizer] Tier 1 (pdf-lib) failed, trying Tier 2 (pdf.js raster):', err1);
        }

        // Tier 2: High-DPI raster rebuild via pdf.js + pdf-lib
        const rasterBytes = await normalizeViaPdfJsRaster(buffer.slice(0), workerSrc);
        return {
            file: new File([rasterBytes], file.name, { type: 'application/pdf', lastModified: Date.now() }),
            normalized: true,
            method: 'pdfjs-raster',
        };
    }

    function setFormSubmitButtonsDisabled(form, disabled) {
        if (!form) return;
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) {
            btn.disabled = disabled;
        });
    }

    function attachNormalizer(input) {
        if (!input || input.dataset.pdfNormalizerBound === '1') {
            return;
        }
        input.dataset.pdfNormalizerBound = '1';

        const form = input.closest('form');
        const workerSrc = input.dataset.pdfWorkerSrc || '';
        let pendingPromise = null;

        input.addEventListener('change', function () {
            const files = input.files;
            if (!files || files.length === 0) {
                setStatus(input, '', '');
                return;
            }

            const originalFile = files[0];
            if (originalFile.type !== 'application/pdf' && !originalFile.name.toLowerCase().endsWith('.pdf')) {
                setStatus(input, '', '');
                return;
            }

            setFormSubmitButtonsDisabled(form, true);
            setStatus(
                input,
                '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>กำลังเตรียมไฟล์ PDF ให้รองรับตราประทับ...',
                'text-primary'
            );

            pendingPromise = normalizePdfFile(originalFile, workerSrc)
                .then(function (result) {
                    if (result.normalized && typeof DataTransfer !== 'undefined') {
                        const dt = new DataTransfer();
                        dt.items.add(result.file);
                        input.files = dt.files;
                        const modeLabel = result.method === 'pdfjs-raster' ? ' (โหมดเข้ากันได้สูงสุด)' : '';
                        setStatus(
                            input,
                            '<i class="bi bi-check-circle-fill me-1"></i>ปรับรูปแบบไฟล์ PDF พร้อมสำหรับประทับตราแล้ว' + modeLabel,
                            'text-success'
                        );
                    } else {
                        setStatus(input, '', '');
                    }
                })
                .catch(function (err) {
                    console.error('[pdf-normalizer] Both Tier 1 and Tier 2 failed:', err);
                    setStatus(
                        input,
                        '<i class="bi bi-exclamation-triangle-fill me-1"></i>ไม่สามารถปรับรูปแบบไฟล์ PDF อัตโนมัติได้ ระบบจะใช้ไฟล์ต้นฉบับ',
                        'text-warning'
                    );
                })
                .finally(function () {
                    pendingPromise = null;
                    setFormSubmitButtonsDisabled(form, false);
                });
        });

        if (form && form.dataset.pdfNormalizerSubmitBound !== '1') {
            form.dataset.pdfNormalizerSubmitBound = '1';
            form.addEventListener('submit', function (e) {
                if (pendingPromise) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                }
            }, true);
        }
    }

    function initAll() {
        document.querySelectorAll('input[type="file"][data-pdf-normalize="1"]').forEach(attachNormalizer);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
