/**
 * admin-arasaac.js
 * ARASAAC pictogram search and selection in block edit form.
 * Requires: admin-blocks edit page with #arasaac-search-input,
 *           #btn-arasaac-search, #arasaac-results, #arasaac-selected,
 *           #input-image-base64, #input-arasaac-id, #image-preview,
 *           #image-preview-wrap
 */
(function () {
    'use strict';

    const searchInput = document.getElementById('arasaac-search-input');
    const searchBtn = document.getElementById('btn-arasaac-search');
    const resultsEl = document.getElementById('arasaac-results');
    const selectedEl = document.getElementById('arasaac-selected');
    const inputBase64 = document.querySelector('input[name="image_base64"]');
    const inputArasaacId = document.querySelector('input[name="arasaac_id"]');
    const previewImg = document.getElementById('image-preview');
    const previewWrap = document.getElementById('image-preview-wrap');

    if (!searchBtn) return; // not on edit page

    let selectedArasaacId = null;

    // ---------------------------------------------------------------
    // Search
    // ---------------------------------------------------------------
    function doSearch() {
        const q = (searchInput.value || '').trim();
        if (!q) return;

        resultsEl.innerHTML = '<div class="text-muted small p-2"><i class="bi bi-hourglass-split"></i> Hledám…</div>';

        fetch('?do=arasaacSearch&q=' + encodeURIComponent(q), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json(); })
            .then(function (results) {
                renderResults(results);
            })
            .catch(function () {
                resultsEl.innerHTML = '<div class="text-danger small p-2">Chyba při hledání.</div>';
            });
    }

    searchBtn.addEventListener('click', doSearch);
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
    });

    // ---------------------------------------------------------------
    // Render results
    // ---------------------------------------------------------------
    function renderResults(results) {
        resultsEl.innerHTML = '';

        if (!results || results.length === 0) {
            resultsEl.innerHTML = '<div class="text-muted small p-2">Nic nenalezeno.</div>';
            return;
        }

        results.forEach(function (item) {
            const div = document.createElement('div');
            div.className = 'arasaac-result';
            div.dataset.id = item.id;
            div.title = item.label;

            const img = document.createElement('img');
            img.src = item.preview_url;
            img.alt = item.label;
            img.loading = 'lazy';

            const label = document.createElement('div');
            label.className = 'arasaac-label';
            label.textContent = item.label;

            div.appendChild(img);
            div.appendChild(label);
            div.addEventListener('click', function () { selectPictogram(item.id, item.label, this); });
            resultsEl.appendChild(div);
        });
    }

    // ---------------------------------------------------------------
    // Select pictogram → download server-side
    // ---------------------------------------------------------------
    function selectPictogram(id, label, el) {
        // Visual selection
        resultsEl.querySelectorAll('.arasaac-result').forEach(function (r) {
            r.classList.remove('selected');
        });
        el.classList.add('selected');
        selectedArasaacId = id;

        selectedEl.textContent = 'Vybráno: ' + label + ' (stahování…)';

        // Download server-side
        fetch('?do=downloadArasaac&arasaac_id=' + encodeURIComponent(id), { 
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) {
                    selectedEl.textContent = 'Chyba: ' + data.error;
                    return;
                }
                // Set preview
                if (previewImg) {
                    previewImg.src = data.previewUrl;
                    if (previewWrap) previewWrap.classList.remove('d-none');
                }
                // Set hidden fields
                if (inputBase64) inputBase64.value = data.imagePath;
                if (inputArasaacId) inputArasaacId.value = id;

                selectedEl.textContent = 'Vybráno: ' + label;
            })
            .catch(function () {
                selectedEl.textContent = 'Chyba při stahování piktogramu.';
            });
    }
})();
