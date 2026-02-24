/**
 * user-app.js
 * Eliskapp – tablet communication board UI.
 * No external dependencies. Reads JSON from <script id="app-data">.
 */
(function () {
    'use strict';

    // ---------------------------------------------------------------
    // State
    // ---------------------------------------------------------------
    var state = {
        blocks: [],
        groups: [],
        settings: { max_sentence_blocks: 7 },
        sentence: [],
        expandedGroup: null,
    };

    // Audio playback controller
    var currentAudio = null;
    var isSpeaking = false;

    // ---------------------------------------------------------------
    // Init
    // ---------------------------------------------------------------
    function init() {
        var dataEl = document.getElementById('app-data');
        if (!dataEl) return;

        try {
            var data = JSON.parse(dataEl.textContent);
            state.blocks = data.blocks || [];
            state.groups = data.groups || [];
            state.settings = data.settings || { max_sentence_blocks: 7 };
        } catch (e) {
            console.error('Eliskapp: failed to parse app-data', e);
            return;
        }

        renderGrid();
        renderSentenceBar();
    }

    // ---------------------------------------------------------------
    // Render grid
    // ---------------------------------------------------------------
    function renderGrid() {
        var grid = document.getElementById('block-grid');
        if (!grid) return;
        grid.innerHTML = '';

        // Root-level items (groups and root blocks), sorted by sort_order
        var rootGroups = state.groups.filter(function (g) { return g.parent_id === null; });
        var rootBlocks = state.blocks.filter(function (b) { return b.group_id === null; });

        // Merge and sort by sort_order
        var items = [];
        rootGroups.forEach(function (g) { items.push({ type: 'group', data: g, sort_order: g.sort_order }); });
        rootBlocks.forEach(function (b) { items.push({ type: 'block', data: b, sort_order: b.sort_order }); });
        items.sort(function (a, b) { return a.sort_order - b.sort_order; });

        var row = document.createElement('div');
        row.className = 'grid-row';

        items.forEach(function (item) {
            if (item.type === 'group') {
                var groupEl = createGroupTile(item.data);
                row.appendChild(groupEl);

                // If this group is expanded, insert expanded row after
                if (state.expandedGroup === item.data.id) {
                    // We'll append the expanded row after the main row
                    // Use a sentinel to know where to insert
                    groupEl.dataset.expandedAfter = '1';
                }
            } else {
                var blockEl = createBlockTile(item.data, false);
                row.appendChild(blockEl);
            }
        });

        grid.appendChild(row);

        // Insert expanded group rows
        if (state.expandedGroup !== null) {
            var expandedGroup = state.groups.find(function (g) { return g.id === state.expandedGroup; });
            if (expandedGroup) {
                var expandedRow = createExpandedGroupRow(expandedGroup);
                grid.appendChild(expandedRow);
                // Trigger animation after paint
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        expandedRow.classList.add('expanded');
                    });
                });
            }
        }
    }

    // ---------------------------------------------------------------
    // Group tile
    // ---------------------------------------------------------------
    function createGroupTile(group) {
        var el = document.createElement('button');
        el.className = 'tile tile-group';
        el.setAttribute('type', 'button');
        el.setAttribute('aria-label', 'Skupina ' + group.name);
        el.style.setProperty('--group-bg', group.bg_color || '#4A90D9');

        if (state.expandedGroup === group.id) {
            el.classList.add('tile-group--expanded');
        }

        // Mini preview: first 4 blocks in the group
        var groupBlocks = state.blocks
            .filter(function (b) { return b.group_id === group.id; })
            .slice(0, 4);

        var miniGrid = document.createElement('div');
        miniGrid.className = 'group-mini-grid';

        for (var i = 0; i < 4; i++) {
            var cell = document.createElement('div');
            cell.className = 'group-mini-cell';
            if (groupBlocks[i] && groupBlocks[i].image_path) {
                var img = document.createElement('img');
                img.src = '/' + groupBlocks[i].image_path;
                img.alt = groupBlocks[i].text || '';
                img.loading = 'lazy';
                cell.appendChild(img);
            }
            miniGrid.appendChild(cell);
        }

        var label = document.createElement('div');
        label.className = 'tile-label';
        label.textContent = group.name;

        el.appendChild(miniGrid);
        el.appendChild(label);

        el.addEventListener('click', function (e) {
            e.stopPropagation();
            if (state.expandedGroup === group.id) {
                state.expandedGroup = null;
            } else {
                state.expandedGroup = group.id;
            }
            renderGrid();
        });

        return el;
    }

    // ---------------------------------------------------------------
    // Expanded group row
    // ---------------------------------------------------------------
    function createExpandedGroupRow(group) {
        var row = document.createElement('div');
        row.className = 'grid-expanded-row';
        row.style.setProperty('--group-bg', group.bg_color || '#4A90D9');

        var groupBlocks = state.blocks
            .filter(function (b) { return b.group_id === group.id; })
            .sort(function (a, b) { return a.sort_order - b.sort_order; });

        if (groupBlocks.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'text-muted p-3';
            empty.textContent = 'Skupina je prázdná.';
            row.appendChild(empty);
        } else {
            groupBlocks.forEach(function (block) {
                var tile = createBlockTile(block, true);
                row.appendChild(tile);
            });
        }

        return row;
    }

    // ---------------------------------------------------------------
    // Block tile
    // ---------------------------------------------------------------
    function createBlockTile(block, inGroup) {
        var disabled = isBlockDisabled(block);
        var isLastInSentence = state.sentence.length > 0 &&
            state.sentence[state.sentence.length - 1].id === block.id;

        // In sentence bar we never show disabled state; this is for grid
        var el = document.createElement('button');
        el.className = 'tile tile-block';
        el.setAttribute('type', 'button');
        el.setAttribute('aria-label', block.text);
        el.dataset.blockId = block.id;

        if (disabled) {
            el.classList.add('tile-block--disabled');
            el.setAttribute('aria-disabled', 'true');
        }
        if (inGroup) {
            el.classList.add('tile-block--in-group');
        }

        if (block.image_path) {
            var img = document.createElement('img');
            img.src = '/' + block.image_path;
            img.alt = block.text || '';
            img.loading = 'lazy';
            el.appendChild(img);
        } else {
            var placeholder = document.createElement('div');
            placeholder.className = 'tile-no-image';
            placeholder.textContent = block.text ? block.text.charAt(0).toUpperCase() : '?';
            el.appendChild(placeholder);
        }

        var label = document.createElement('div');
        label.className = 'tile-label';
        label.textContent = block.text;
        el.appendChild(label);

        el.addEventListener('click', function (e) {
            e.stopPropagation();
            if (disabled) return;
            addToSentence(block);
        });

        return el;
    }

    // ---------------------------------------------------------------
    // Sentence
    // ---------------------------------------------------------------
    function addToSentence(block) {
        var max = state.settings.max_sentence_blocks || 7;
        if (state.sentence.length >= max) return;

        state.sentence.push(block);
        renderSentenceBar();
        renderGrid(); // Update disabled states
        playBlock(block);
    }

    function playBlock(block) {
        stopAudio();
        isSpeaking = true;
        if (block.audio_path) {
            playAudioFile('/' + block.audio_path, function () { isSpeaking = false; });
        } else {
            speakText(block.text, function () { isSpeaking = false; });
        }
    }

    function removeLastFromSentence() {
        if (state.sentence.length === 0) return;
        state.sentence.pop();
        renderSentenceBar();
        renderGrid();
        stopAudio();
    }

    function resetSentence() {
        state.sentence = [];
        renderSentenceBar();
        renderGrid();
        stopAudio();
    }

    // ---------------------------------------------------------------
    // Sentence bar rendering
    // ---------------------------------------------------------------
    function renderSentenceBar() {
        var blocksContainer = document.getElementById('sentence-blocks');
        var controlsContainer = document.getElementById('sentence-controls');
        if (!blocksContainer || !controlsContainer) return;

        blocksContainer.innerHTML = '';
        controlsContainer.innerHTML = '';

        if (state.sentence.length === 0) return;

        // Calculate visible tiles (based on container width)
        var barWidth = blocksContainer.offsetWidth || window.innerWidth - 120;
        var tileSize = 88; // 80px tile + 8px gap
        var maxVisible = Math.max(1, Math.floor(barWidth / tileSize));
        var visible = state.sentence.slice(-maxVisible);

        visible.forEach(function (block, i) {
            var isLast = (i === visible.length - 1) &&
                (visible[i] === state.sentence[state.sentence.length - 1]);

            var tile = createSentenceTile(block, isLast);
            blocksContainer.appendChild(tile);
        });

        // Controls: PLAY and RESET
        var playBtn = document.createElement('button');
        playBtn.className = 'sentence-btn sentence-btn-play';
        playBtn.setAttribute('type', 'button');
        playBtn.setAttribute('aria-label', 'Přehrát větu');
        playBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" width="32" height="32">' +
            '<path d="M8 5v14l11-7z"/></svg>';
        playBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            playSentence();
        });

        var resetBtn = document.createElement('button');
        resetBtn.className = 'sentence-btn sentence-btn-reset';
        resetBtn.setAttribute('type', 'button');
        resetBtn.setAttribute('aria-label', 'Smazat větu');
        resetBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="currentColor" width="32" height="32">' +
            '<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>';
        resetBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            resetSentence();
        });

        controlsContainer.appendChild(playBtn);
        controlsContainer.appendChild(resetBtn);
    }

    function createSentenceTile(block, isLast) {
        var el = document.createElement('button');
        el.className = 'sentence-tile';
        el.setAttribute('type', 'button');
        el.setAttribute('aria-label', block.text);

        if (isLast) {
            el.classList.add('sentence-tile--last');
            el.setAttribute('title', 'Kliknutím odebrat');
            el.addEventListener('click', function (e) {
                e.stopPropagation();
                removeLastFromSentence();
            });
        }

        if (block.image_path) {
            var img = document.createElement('img');
            img.src = '/' + block.image_path;
            img.alt = block.text || '';
            el.appendChild(img);
        } else {
            var ph = document.createElement('div');
            ph.className = 'tile-no-image tile-no-image--small';
            ph.textContent = block.text ? block.text.charAt(0).toUpperCase() : '?';
            el.appendChild(ph);
        }

        var label = document.createElement('div');
        label.className = 'sentence-tile-label';
        label.textContent = block.text;
        el.appendChild(label);

        return el;
    }

    // ---------------------------------------------------------------
    // Block type consecutive rule
    // ---------------------------------------------------------------
    function isBlockDisabled(block) {
        if (state.sentence.length === 0) return false;
        var last = state.sentence[state.sentence.length - 1];
        if (!last || !last.block_type || !block.block_type) return false;
        if (last.block_type === 'other' || block.block_type === 'other') return false;
        return last.block_type === block.block_type;
    }

    // ---------------------------------------------------------------
    // Audio playback
    // ---------------------------------------------------------------
    function stopAudio() {
        if (currentAudio) {
            currentAudio.pause();
            currentAudio.src = '';
            currentAudio = null;
        }
        if (window.speechSynthesis) {
            window.speechSynthesis.cancel();
        }
        isSpeaking = false;
    }

    function playSentence() {
        stopAudio();
        if (state.sentence.length === 0) return;

        var blocks = state.sentence.slice();
        var index = 0;
        isSpeaking = true;

        function playNext() {
            if (!isSpeaking || index >= blocks.length) {
                isSpeaking = false;
                return;
            }
            var block = blocks[index++];
            if (block.audio_path) {
                playAudioFile('/' + block.audio_path, playNext);
            } else {
                speakText(block.text, playNext);
            }
        }

        playNext();
    }

    function playAudioFile(src, onEnd) {
        var audio = new Audio(src);
        currentAudio = audio;
        audio.onended = function () {
            currentAudio = null;
            onEnd();
        };
        audio.onerror = function () {
            currentAudio = null;
            onEnd();
        };
        audio.play().catch(function () {
            currentAudio = null;
            onEnd();
        });
    }

    function speakText(text, onEnd) {
        if (!window.speechSynthesis || !text) {
            onEnd();
            return;
        }
        // Chrome bug: syntetizér "spolkne" začátek první promluvy pokud byl idle.
        // cancel() + setTimeout zajistí správnou inicializaci před speak().
        window.speechSynthesis.cancel();
        var utt = new SpeechSynthesisUtterance(text);
        utt.lang = 'cs-CZ';
        utt.rate = 0.9;
        utt.onend = onEnd;
        utt.onerror = onEnd;
        setTimeout(function () {
            window.speechSynthesis.speak(utt);
        }, 150);
    }

    // ---------------------------------------------------------------
    // Prevent accidental navigation
    // ---------------------------------------------------------------
    document.addEventListener('click', function (e) {
        // Close expanded group when clicking outside grid
        if (!e.target.closest('#block-grid') && state.expandedGroup !== null) {
            // Don't close if clicking sentence bar
            if (!e.target.closest('#sentence-bar')) {
                // Actually keep group open – only close via group tile click
            }
        }
    });

    // Handle window resize: re-render sentence bar to update overflow
    window.addEventListener('resize', function () {
        renderSentenceBar();
    });

    // ---------------------------------------------------------------
    // Start
    // ---------------------------------------------------------------
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
