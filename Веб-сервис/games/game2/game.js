(function () {
    'use strict';

    const GAME_ID = 2;

    const NOVEL_FRAMES = [1, 2, 3];

    const SUCCESS_IMAGES_COUNT = 4;
    const SUCCESS_IMAGES_PATH = '../success_img/';

    const els = {
        startScreen: document.getElementById('startScreen'),
        novelScreen: document.getElementById('novelScreen'),
        gameScreen: document.getElementById('gameScreen'),

        startBtn: document.getElementById('startBtn'),
        novelFrame: document.getElementById('novelFrame'),
        novelImage: document.getElementById('novelImage'),
        novelSource: document.getElementById('novelSource'),
        novelCounter: document.getElementById('novelCounter'),

        levelPill: document.getElementById('levelPill'),

        magicCard: document.getElementById('magicCard'),
        hintText: document.getElementById('hintText'),
        imageWrap: document.getElementById('imageWrap'),

        slots: document.getElementById('slots'),
        pool: document.getElementById('pool'),

        btnRestart: document.getElementById('btnRestart'),

        finishCard: document.getElementById('finishCard'),
        finishText: document.getElementById('finishText'),

        successPop: document.getElementById('successPop'),
        successPopImage: document.getElementById('successPopImage'),

        toastContainer: document.getElementById('toastContainer')
    };

    const state = {
        words: [],
        order: [],
        index: 0,
        current: null,
        filled: [],

        correctAnswers: 0,
        incorrectAnswers: 0,
        startTime: 0,
        resultSaved: false,

        novelFrameIndex: 1,
        availableNovelFrames: [],
        activeAudio: null,
        activeAnimalAudio: null,

        drag: null,
        locked: false,
        finished: false,

        lastSuccessImageIndex: 0
    };

    document.addEventListener('DOMContentLoaded', init);

    async function init() {
        lockViewport();

        if (els.startBtn) {
            els.startBtn.addEventListener('click', startIntro);
        }

        if (els.novelFrame) {
            els.novelFrame.addEventListener('click', nextNovelFrame);
        }

        if (els.btnRestart) {
            els.btnRestart.addEventListener('click', startGame);
        }

        if (els.magicCard) {
            els.magicCard.addEventListener('click', playAnimalSound);
        }

        window.addEventListener('resize', function () {
            updateNovelSources();
        });

        window.addEventListener('message', function (event) {
            const data = event.data;

            if (!data) {
                return;
            }

            if (data.type === 'LOGOPA_START_GAME') {
                startIntro();
                return;
            }

            if (data.type === 'LOGOPA_STOP_GAME') {
                stopAllGameActivity();
            }
        });

        showScreen(els.startScreen);

        await preloadGameAssets();

        notifyParentGameReady();
    }

    function lockViewport() {
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
    }

    function showScreen(activeScreen) {
        [els.startScreen, els.novelScreen, els.gameScreen].forEach(function (screen) {
            if (!screen) {
                return;
            }

            screen.classList.toggle('screen--active', screen === activeScreen);
        });
    }

    async function startIntro() {
        stopAllGameActivity();

        state.finished = false;
        state.locked = false;

        showScreen(els.novelScreen);

        if (!state.availableNovelFrames.length) {
            state.availableNovelFrames = await detectNovelFrames();
        }

        state.novelFrameIndex = 1;

        if (!state.availableNovelFrames.length) {
            startGame();
            return;
        }

        renderNovelFrame();
    }

    function getOrientationFolder() {
        return window.matchMedia('(orientation: portrait)').matches ? 'vertical' : 'horizontal';
    }

    async function detectNovelFrames() {
        return NOVEL_FRAMES;
    }

    function renderNovelFrame() {
        const frameNumber = state.availableNovelFrames[state.novelFrameIndex - 1];

        if (!frameNumber) {
            startGame();
            return;
        }

        updateNovelSources();

        if (els.novelCounter) {
            els.novelCounter.textContent = `${state.novelFrameIndex}/${state.availableNovelFrames.length}`;
        }

        playFrameAudio(frameNumber);
    }

    function updateNovelSources() {
        if (!els.novelScreen || !els.novelImage || !els.novelSource) {
            return;
        }

        if (!els.novelScreen.classList.contains('screen--active')) {
            return;
        }

        if (!state.availableNovelFrames.length) {
            return;
        }

        const frameNumber = state.availableNovelFrames[state.novelFrameIndex - 1];

        if (!frameNumber) {
            return;
        }

        const folder = getOrientationFolder();
        const fallbackFolder = folder === 'vertical' ? 'horizontal' : 'vertical';

        els.novelImage.onerror = function () {
            els.novelImage.onerror = null;
            els.novelImage.src = `img/${fallbackFolder}/${frameNumber}.webp`;
        };

        els.novelSource.srcset = `img/vertical/${frameNumber}.webp`;
        els.novelImage.src = `img/${folder}/${frameNumber}.webp`;
    }

    function getAudioCandidates(frameNumber) {
        return [
            `audio/${frameNumber}.mp3`
        ];
    }

    function preloadImage(src) {
        return new Promise(function (resolve) {
            const img = new Image();

            img.onload = function () {
                resolve(true);
            };

            img.onerror = function () {
                resolve(false);
            };

            img.src = src;
        });
    }

    function preloadAudio(src) {
        return new Promise(function (resolve) {
            const audio = new Audio();

            let done = false;

            function finish(result) {
                if (done) {
                    return;
                }

                done = true;

                audio.removeEventListener('canplaythrough', onReady);
                audio.removeEventListener('loadeddata', onReady);
                audio.removeEventListener('error', onError);

                resolve(result);
            }

            function onReady() {
                finish(true);
            }

            function onError() {
                finish(false);
            }

            audio.preload = 'auto';

            audio.addEventListener('canplaythrough', onReady, { once: true });
            audio.addEventListener('loadeddata', onReady, { once: true });
            audio.addEventListener('error', onError, { once: true });

            audio.src = src;
            audio.load();

            setTimeout(function () {
                finish(false);
            }, 5000);
        });
    }

    async function preloadGameAssets() {
        state.availableNovelFrames = await detectNovelFrames();

        const tasks = [];

        state.availableNovelFrames.forEach(function (frameNumber) {
            tasks.push(preloadImage(`img/horizontal/${frameNumber}.webp`));
            tasks.push(preloadImage(`img/vertical/${frameNumber}.webp`));

            getAudioCandidates(frameNumber).forEach(function (audioSrc) {
                tasks.push(preloadAudio(audioSrc));
            });
        });

        for (let i = 1; i <= SUCCESS_IMAGES_COUNT; i++) {
            tasks.push(preloadImage(`${SUCCESS_IMAGES_PATH}${i}.png`));
        }

        await Promise.allSettled(tasks);
    }

    function notifyParentGameReady() {
        try {
            window.parent.postMessage({
                type: 'LOGOPA_GAME_READY'
            }, '*');
        } catch (err) {}
    }

    function playFrameAudio(frameNumber) {
        stopFrameAudio();

        const candidates = getAudioCandidates(frameNumber);
        let index = 0;

        function tryNext() {
            if (index >= candidates.length) {
                return;
            }

            const audio = new Audio(candidates[index]);

            state.activeAudio = audio;
            index += 1;

            audio.addEventListener('error', tryNext, { once: true });
            audio.play().catch(function () {});
        }

        tryNext();
    }

    function stopFrameAudio() {
        if (!state.activeAudio) {
            return;
        }

        try {
            state.activeAudio.pause();
            state.activeAudio.currentTime = 0;
        } catch (err) {}

        state.activeAudio = null;
    }

    function nextNovelFrame() {
        if (!state.availableNovelFrames.length) {
            startGame();
            return;
        }

        state.novelFrameIndex += 1;

        if (state.novelFrameIndex > state.availableNovelFrames.length) {
            stopFrameAudio();
            startGame();
            return;
        }

        renderNovelFrame();
    }

    async function startGame() {
        stopAllGameActivity();

        state.words = [];
        state.order = [];
        state.index = 0;
        state.current = null;
        state.filled = [];

        state.correctAnswers = 0;
        state.incorrectAnswers = 0;
        state.startTime = Date.now();
        state.resultSaved = false;

        state.locked = false;
        state.finished = false;

        if (els.finishCard) {
            els.finishCard.classList.remove('is-visible');
            els.finishCard.setAttribute('aria-hidden', 'true');
        }

        clearGameField();

        showScreen(els.gameScreen);

        try {
            const response = await fetch('data.php', {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`Ошибка загрузки data.php: ${response.status}`);
            }

            const data = await response.json();

            state.words = normalizeWords(data);
            state.order = shuffle(Array.from({ length: state.words.length }, function (_, index) {
                return index;
            }));

            if (!state.words.length) {
                showToast('Список слов пустой.', 'error');
                return;
            }

            loadWord(0);
        } catch (error) {
            showToast('Не удалось загрузить слова.', 'error');
        }
    }

    function normalizeWords(data) {
        if (!Array.isArray(data)) {
            return [];
        }

        return data
            .map(function (item) {
                const word = toUpper(item.word || '');
                const letters = Array.isArray(item.letters)
                    ? item.letters.map(function (letter) {
                        return toUpper(letter);
                    }).filter(Boolean)
                    : splitWord(word);

                return {
                    word: word,
                    letters: letters,
                    hint: item.hint || 'Послушай звук и собери слово',
                    image: item.image || '',
                    sound: item.sound || ''
                };
            })
            .filter(function (item) {
                return item.word && item.letters.length;
            });
    }

    function splitWord(word) {
        return String(word || '').split('');
    }

    function clearGameField() {
        if (els.slots) {
            els.slots.innerHTML = '';
        }

        if (els.pool) {
            els.pool.innerHTML = '';
        }

        if (els.hintText) {
            els.hintText.textContent = '—';
        }

        if (els.imageWrap) {
            els.imageWrap.innerHTML = '<span>Нет изображения</span>';
        }

        if (els.levelPill) {
            els.levelPill.textContent = '0/0';
        }
    }

    function loadWord(index) {
        stopAnimalSound();
        clearDrag();

        if (index >= state.order.length) {
            finishGame();
            return;
        }

        state.index = index;
        state.current = state.words[state.order[index]];
        state.filled = Array(state.current.letters.length).fill(null);
        state.locked = false;

        if (els.levelPill) {
            els.levelPill.textContent = `${index + 1}/${state.words.length}`;
        }

        renderMagicCard();
        renderSlots();
        renderPool(shuffle(state.current.letters));
    }

    function renderMagicCard() {
        if (els.hintText) {
            els.hintText.textContent = state.current?.hint || 'Послушай звук и собери слово';
        }

        if (!els.imageWrap) {
            return;
        }

        els.imageWrap.innerHTML = '';

        if (!state.current || !state.current.image) {
            els.imageWrap.innerHTML = '<span>Нет изображения</span>';
            return;
        }

        const img = document.createElement('img');

        img.alt = state.current.word || '';
        img.src = state.current.image;
        img.loading = 'lazy';

        img.onerror = function () {
            if (els.imageWrap) {
                els.imageWrap.innerHTML = '<span>Нет изображения</span>';
            }
        };

        els.imageWrap.appendChild(img);
    }

    function playAnimalSound() {
        if (!state.current || !state.current.sound) {
            showToast('Звук не найден.', 'error');
            return;
        }

        stopAnimalSound();

        state.activeAnimalAudio = new Audio(state.current.sound);

        state.activeAnimalAudio.play().catch(function () {
            showToast('Не удалось воспроизвести звук.', 'error');
        });
    }

    function stopAnimalSound() {
        if (!state.activeAnimalAudio) {
            return;
        }

        try {
            state.activeAnimalAudio.pause();
            state.activeAnimalAudio.currentTime = 0;
        } catch (err) {}

        state.activeAnimalAudio = null;
    }

    function renderSlots() {
        if (!els.slots) {
            return;
        }

        els.slots.innerHTML = '';

        state.filled.forEach(function (letter, index) {
            const slot = document.createElement('button');

            slot.type = 'button';
            slot.className = letter ? 'slot filled' : 'slot';
            slot.textContent = letter || '';
            slot.dataset.index = String(index);
            slot.setAttribute('aria-label', `Место ${index + 1}`);

            slot.addEventListener('click', function () {
                returnLetterFromSlot(index);
            });

            slot.addEventListener('pointerdown', function (event) {
                if (!letter || state.locked) {
                    return;
                }

                startSlotDrag(event, index);
            });

            els.slots.appendChild(slot);
        });
    }

    function renderPool(letters) {
        if (!els.pool) {
            return;
        }

        els.pool.innerHTML = '';

        letters.forEach(function (letter) {
            addTileToPool(letter);
        });
    }

    function addTileToPool(letter) {
        if (!els.pool) {
            return;
        }

        const tile = document.createElement('button');

        tile.type = 'button';
        tile.className = 'tile';
        tile.textContent = letter;
        tile.dataset.dragType = 'tile';
        tile.dataset.letter = letter;
        tile.dataset.tileId = `tile-${Math.random().toString(36).slice(2)}`;
        tile.setAttribute('aria-label', `Буква ${letter}`);

        tile.addEventListener('click', function () {
            placeTileByClick(tile);
        });

        tile.addEventListener('pointerdown', startTileDrag);

        els.pool.appendChild(tile);
    }

    function placeTileByClick(tile) {
        if (state.locked || state.finished || tile.classList.contains('is-hidden')) {
            return;
        }

        const emptyIndex = state.filled.findIndex(function (value) {
            return value === null;
        });

        if (emptyIndex === -1) {
            showToast('Все места уже заполнены.', 'error');
            return;
        }

        state.filled[emptyIndex] = tile.dataset.letter;
        tile.classList.add('is-hidden');

        renderSlots();
        checkAutomatically();
    }

    function returnLetterFromSlot(index) {
        if (state.locked || state.finished || !state.filled[index]) {
            return;
        }

        addTileToPool(state.filled[index]);
        state.filled[index] = null;

        renderSlots();
    }

    function startTileDrag(event) {
        const source = event.currentTarget;

        if (state.locked || state.finished || source.classList.contains('is-hidden')) {
            return;
        }

        event.preventDefault();

        createDrag({
            source: source,
            type: 'tile',
            letter: source.dataset.letter,
            slotIndex: null,
            pointerId: event.pointerId,
            x: event.clientX,
            y: event.clientY
        });
    }

    function startSlotDrag(event, slotIndex) {
        if (state.locked || state.finished || !state.filled[slotIndex]) {
            return;
        }

        event.preventDefault();

        createDrag({
            source: event.currentTarget,
            type: 'slot',
            letter: state.filled[slotIndex],
            slotIndex: slotIndex,
            pointerId: event.pointerId,
            x: event.clientX,
            y: event.clientY
        });
    }

    function createDrag(options) {
        clearDrag();

        const ghost = document.createElement('div');

        ghost.className = 'drag-ghost';
        ghost.textContent = options.letter;

        document.body.appendChild(ghost);

        state.drag = {
            source: options.source,
            ghost: ghost,
            type: options.type,
            letter: options.letter,
            slotIndex: options.slotIndex,
            lastTarget: null,
            pointerId: options.pointerId
        };

        options.source.classList.add('is-dragging');

        moveGhost(options.x, options.y);

        window.addEventListener('pointermove', onPointerMove, { passive: false });
        window.addEventListener('pointerup', onPointerUp, { passive: false });
        window.addEventListener('pointercancel', cancelDrag, { passive: false });
    }

    function onPointerMove(event) {
        if (!state.drag) {
            return;
        }

        event.preventDefault();

        moveGhost(event.clientX, event.clientY);
        markDropTarget(event.clientX, event.clientY);
    }

    function onPointerUp(event) {
        if (!state.drag) {
            return;
        }

        event.preventDefault();

        const target = getDropTarget(event.clientX, event.clientY);

        clearDropTarget();

        if (target) {
            dropToSlot(target);
        }

        clearDrag();
    }

    function cancelDrag(event) {
        if (event) {
            event.preventDefault();
        }

        clearDropTarget();
        clearDrag();
    }

    function moveGhost(x, y) {
        if (!state.drag || !state.drag.ghost) {
            return;
        }

        state.drag.ghost.style.left = `${x}px`;
        state.drag.ghost.style.top = `${y}px`;
    }

    function getDropTarget(x, y) {
        if (!state.drag || !state.drag.ghost) {
            return null;
        }

        state.drag.ghost.style.visibility = 'hidden';

        const element = document.elementFromPoint(x, y);
        const target = element ? element.closest('.slot') : null;

        state.drag.ghost.style.visibility = 'visible';

        return target;
    }

    function markDropTarget(x, y) {
        if (!state.drag) {
            return;
        }

        const target = getDropTarget(x, y);

        if (target === state.drag.lastTarget) {
            return;
        }

        clearDropTarget();

        if (!target) {
            state.drag.lastTarget = null;
            return;
        }

        const targetIndex = Number(target.dataset.index);
        const isSameSlot = state.drag.type === 'slot' && state.drag.slotIndex === targetIndex;

        if (!state.filled[targetIndex] || isSameSlot) {
            target.classList.add('drop-target');
            state.drag.lastTarget = target;
        }
    }

    function clearDropTarget() {
        document.querySelectorAll('.drop-target').forEach(function (el) {
            el.classList.remove('drop-target');
        });

        if (state.drag) {
            state.drag.lastTarget = null;
        }
    }

    function dropToSlot(target) {
        if (!state.drag) {
            return;
        }

        const targetIndex = Number(target.dataset.index);

        if (!Number.isInteger(targetIndex)) {
            return;
        }

        const isSameSlot = state.drag.type === 'slot' && state.drag.slotIndex === targetIndex;

        if (isSameSlot) {
            return;
        }

        if (state.filled[targetIndex]) {
            showToast('Это место уже занято.', 'error');
            return;
        }

        if (state.drag.type === 'slot' && state.drag.slotIndex !== null) {
            state.filled[state.drag.slotIndex] = null;
        }

        state.filled[targetIndex] = state.drag.letter;

        if (state.drag.type === 'tile' && state.drag.source) {
            state.drag.source.classList.add('is-hidden');
        }

        renderSlots();
        checkAutomatically();
    }

    function clearDrag() {
        if (!state.drag) {
            return;
        }

        window.removeEventListener('pointermove', onPointerMove);
        window.removeEventListener('pointerup', onPointerUp);
        window.removeEventListener('pointercancel', cancelDrag);

        if (state.drag.source) {
            state.drag.source.classList.remove('is-dragging');
        }

        if (state.drag.ghost) {
            state.drag.ghost.remove();
        }

        state.drag = null;
    }

    function checkAutomatically() {
        if (!state.current || state.locked || state.finished) {
            return;
        }

        if (state.filled.some(function (letter) {
            return !letter;
        })) {
            return;
        }

        const attempt = toUpper(state.filled.join(''));
        const correct = toUpper(state.current.word);

        if (attempt === correct) {
            state.correctAnswers += 1;
            state.locked = true;

            showSuccessPop();
            showToast('Правильно!');

            setTimeout(function () {
                loadWord(state.index + 1);
            }, 1200);

            return;
        }

        state.incorrectAnswers += 1;

        markMistakes(correct);
        showToast('Попробуй ещё раз.', 'error');

        setTimeout(function () {
            resetCurrentWord();
        }, 850);
    }

    function resetCurrentWord() {
        if (!state.current) {
            return;
        }

        const letters = [];

        state.filled.forEach(function (letter) {
            if (letter) {
                letters.push(letter);
            }
        });

        document.querySelectorAll('.tile:not(.is-hidden)').forEach(function (tile) {
            if (tile.dataset.letter) {
                letters.push(tile.dataset.letter);
            }
        });

        state.filled = Array(state.current.letters.length).fill(null);

        renderSlots();
        renderPool(shuffle(letters));
    }

    function markMistakes(correct) {
        if (!els.slots) {
            return;
        }

        const slots = Array.from(els.slots.querySelectorAll('.slot'));

        slots.forEach(function (slot, index) {
            const isGood = toUpper(slot.textContent) === toUpper(correct[index]);

            slot.classList.add(isGood ? 'is-good' : 'is-bad');

            setTimeout(function () {
                slot.classList.remove('is-good', 'is-bad');
            }, 700);
        });
    }

    async function finishGame() {
        if (state.finished) {
            return;
        }

        state.finished = true;
        state.locked = true;

        const timeSpent = getTimeSpent();

        clearDrag();
        stopAnimalSound();

        if (els.slots) {
            els.slots.innerHTML = '';
        }

        if (els.pool) {
            els.pool.innerHTML = '';
        }

        if (els.hintText) {
            els.hintText.textContent = 'Игра завершена';
        }

        if (els.imageWrap) {
            els.imageWrap.innerHTML = '<span>Все животные угаданы</span>';
        }

        if (els.finishText) {
            els.finishText.textContent = `Правильно: ${state.correctAnswers}. Ошибок: ${state.incorrectAnswers}.`;
        }

        if (els.finishCard) {
            els.finishCard.classList.add('is-visible');
            els.finishCard.setAttribute('aria-hidden', 'false');
        }

        await saveGameResult(timeSpent);
    }

    function getTimeSpent() {
        if (!state.startTime) {
            return 0;
        }

        return Math.max(0, Math.floor((Date.now() - state.startTime) / 1000));
    }

    async function saveGameResult(timeSpent) {
        if (state.resultSaved) {
            return;
        }

        state.resultSaved = true;

        try {
            const response = await fetch('../../save_result.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    game_id: GAME_ID,
                    correct_answers: state.correctAnswers,
                    incorrect_answers: state.incorrectAnswers,
                    time_spent: timeSpent
                })
            });

            const data = await response.json();

            if (data.success) {
                showToast('Результат игры сохранён.');
            } else {
                showToast(data.message || 'Результат не сохранён.', 'error');
            }
        } catch (error) {
            showToast('Не удалось сохранить результат.', 'error');
        }
    }

    function stopAllGameActivity() {
        clearDrag();
        stopFrameAudio();
        stopAnimalSound();

        state.locked = true;
    }

    function getRandomSuccessImageIndex() {
        let randomIndex = Math.floor(Math.random() * SUCCESS_IMAGES_COUNT) + 1;

        if (SUCCESS_IMAGES_COUNT > 1) {
            while (randomIndex === state.lastSuccessImageIndex) {
                randomIndex = Math.floor(Math.random() * SUCCESS_IMAGES_COUNT) + 1;
            }
        }

        state.lastSuccessImageIndex = randomIndex;

        return randomIndex;
    }

    function showSuccessPop() {
        if (!els.successPop || !els.successPopImage) {
            return;
        }

        const randomIndex = getRandomSuccessImageIndex();

        els.successPopImage.src = `${SUCCESS_IMAGES_PATH}${randomIndex}.png`;

        els.successPop.classList.remove('is-visible');

        void els.successPop.offsetWidth;

        els.successPop.classList.add('is-visible');

        setTimeout(function () {
            els.successPop.classList.remove('is-visible');
        }, 1100);
    }

    function showToast(message, type = 'success') {
        if (!els.toastContainer) {
            return;
        }

        const toast = document.createElement('div');

        toast.className = `toast toast--${type}`;
        toast.textContent = message;

        els.toastContainer.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });

        setTimeout(function () {
            toast.classList.remove('is-visible');

            setTimeout(function () {
                toast.remove();
            }, 240);
        }, 3000);
    }

    function shuffle(arr) {
        const copy = Array.isArray(arr) ? [...arr] : [];

        for (let i = copy.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));

            [copy[i], copy[j]] = [copy[j], copy[i]];
        }

        return copy;
    }

    function toUpper(value) {
        return String(value || '').toLocaleUpperCase('ru-RU');
    }
})();