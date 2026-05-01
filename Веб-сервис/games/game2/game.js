(function () {
    'use strict';

    const GAME_ID = 2;
    const NOVEL_MAX_FRAMES = 20;

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
        lastSuccessImageIndex: 0
    };

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        lockViewport();

        els.startBtn.addEventListener('click', startIntro);
        els.novelFrame.addEventListener('click', nextNovelFrame);
        els.magicCard.addEventListener('click', playAnimalSound);
        els.btnRestart.addEventListener('click', startGame);

        window.addEventListener('resize', updateNovelSources);

        showScreen(els.startScreen);
    }

    function lockViewport() {
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
    }

    function showScreen(activeScreen) {
        [els.startScreen, els.novelScreen, els.gameScreen].forEach(screen => {
            screen.classList.toggle('screen--active', screen === activeScreen);
        });
    }

    async function startIntro() {
        showScreen(els.novelScreen);

        state.availableNovelFrames = await detectNovelFrames();
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

    function imageExists(src) {
        return new Promise(resolve => {
            const img = new Image();
            img.onload = () => resolve(true);
            img.onerror = () => resolve(false);
            img.src = src;
        });
    }

    async function detectNovelFrames() {
        const frames = [];

        for (let i = 1; i <= NOVEL_MAX_FRAMES; i++) {
            const horizontal = `img/horizontal/${i}.png`;
            const vertical = `img/vertical/${i}.png`;

            const existsHorizontal = await imageExists(horizontal).then(Boolean).catch(() => false);
            const existsVertical = await imageExists(vertical).then(Boolean).catch(() => false);

            if (!existsHorizontal && !existsVertical) {
                if (i === 1) continue;
                break;
            }

            frames.push(i);
        }

        return frames;
    }

    function renderNovelFrame() {
        const frameNumber = state.availableNovelFrames[state.novelFrameIndex - 1];

        if (!frameNumber) {
            startGame();
            return;
        }

        updateNovelSources();
        els.novelCounter.textContent = `${state.novelFrameIndex}/${state.availableNovelFrames.length}`;
        playFrameAudio(frameNumber);
    }

    function updateNovelSources() {
        if (!els.novelScreen.classList.contains('screen--active') || !state.availableNovelFrames.length) {
            return;
        }

        const frameNumber = state.availableNovelFrames[state.novelFrameIndex - 1];
        const folder = getOrientationFolder();
        const fallbackFolder = folder === 'vertical' ? 'horizontal' : 'vertical';

        els.novelImage.src = `img/${folder}/${frameNumber}.png`;
        els.novelSource.srcset = `img/vertical/${frameNumber}.png`;

        els.novelImage.onerror = () => {
            els.novelImage.onerror = null;
            els.novelImage.src = `img/${fallbackFolder}/${frameNumber}.png`;
        };
    }

    function getAudioCandidates(frameNumber) {
        return [
            `audio/${frameNumber}.mp3`,
            `audio/${frameNumber}.wav`
        ];
    }

    function playFrameAudio(frameNumber) {
        stopFrameAudio();

        const candidates = getAudioCandidates(frameNumber);
        let index = 0;

        function tryNext() {
            if (index >= candidates.length) return;

            const audio = new Audio(candidates[index]);
            state.activeAudio = audio;
            index += 1;

            audio.addEventListener('error', tryNext, { once: true });
            audio.play().catch(() => { });
        }

        tryNext();
    }

    function stopFrameAudio() {
        if (!state.activeAudio) return;

        state.activeAudio.pause();
        state.activeAudio.currentTime = 0;
        state.activeAudio = null;
    }

    function nextNovelFrame() {
        state.novelFrameIndex += 1;

        if (state.novelFrameIndex > state.availableNovelFrames.length) {
            stopFrameAudio();
            startGame();
            return;
        }

        renderNovelFrame();
    }

    async function startGame() {
        stopFrameAudio();
        stopAnimalSound();

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

        els.finishCard.classList.remove('is-visible');
        els.finishCard.setAttribute('aria-hidden', 'true');

        showScreen(els.gameScreen);

        try {
            const response = await fetch('data.php', {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`Ошибка загрузки data.php: ${response.status}`);
            }

            const data = await response.json();

            state.words = Array.isArray(data) ? data : [];
            state.order = shuffle([...Array(state.words.length)].map((_, i) => i));

            if (!state.words.length) {
                showToast('Список слов пустой.', 'error');
                return;
            }

            loadWord(0);
        } catch (error) {
            showToast('Не удалось загрузить слова.', 'error');
        }
    }

    function loadWord(index) {
        if (index >= state.order.length) {
            finishGame();
            return;
        }

        state.index = index;
        state.current = state.words[state.order[index]];
        state.filled = Array(state.current.letters.length).fill(null);
        state.locked = false;

        els.levelPill.textContent = `${index + 1}/${state.words.length}`;

        renderMagicCard();
        renderSlots();
        renderPool(shuffle([...state.current.letters]));
    }

    function renderMagicCard() {
        els.hintText.textContent = state.current.hint || 'Послушай звук и собери слово';

        els.imageWrap.innerHTML = '';

        if (!state.current.image) {
            els.imageWrap.innerHTML = '<span>Нет изображения</span>';
            return;
        }

        const img = document.createElement('img');

        img.alt = state.current.word || '';
        img.src = state.current.image;
        img.loading = 'lazy';

        img.onerror = () => {
            els.imageWrap.innerHTML = '<span>Нет изображения</span>';
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
        state.activeAnimalAudio.play().catch(() => {
            showToast('Не удалось воспроизвести звук.', 'error');
        });
    }

    function stopAnimalSound() {
        if (!state.activeAnimalAudio) return;

        state.activeAnimalAudio.pause();
        state.activeAnimalAudio.currentTime = 0;
        state.activeAnimalAudio = null;
    }

    function renderSlots() {
        els.slots.innerHTML = '';

        state.filled.forEach((letter, index) => {
            const slot = document.createElement('button');

            slot.type = 'button';
            slot.className = letter ? 'slot filled' : 'slot';
            slot.textContent = letter || '';
            slot.dataset.index = String(index);
            slot.setAttribute('aria-label', `Место ${index + 1}`);

            slot.addEventListener('click', () => returnLetterFromSlot(index));

            slot.addEventListener('pointerdown', event => {
                if (!letter || state.locked) return;
                startSlotDrag(event, index);
            });

            els.slots.appendChild(slot);
        });
    }

    function renderPool(letters) {
        els.pool.innerHTML = '';
        letters.forEach(letter => addTileToPool(letter));
    }

    function addTileToPool(letter) {
        const tile = document.createElement('button');

        tile.type = 'button';
        tile.className = 'tile';
        tile.textContent = letter;
        tile.dataset.dragType = 'tile';
        tile.dataset.letter = letter;
        tile.dataset.tileId = `tile-${Math.random().toString(36).slice(2)}`;
        tile.setAttribute('aria-label', `Буква ${letter}`);

        tile.addEventListener('click', () => placeTileByClick(tile));
        tile.addEventListener('pointerdown', startTileDrag);

        els.pool.appendChild(tile);
    }

    function placeTileByClick(tile) {
        if (state.locked || tile.classList.contains('is-hidden')) {
            return;
        }

        const emptyIndex = state.filled.findIndex(value => value === null);

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
        if (state.locked || !state.filled[index]) {
            return;
        }

        addTileToPool(state.filled[index]);
        state.filled[index] = null;

        renderSlots();
    }

    function startTileDrag(event) {
        const source = event.currentTarget;

        if (state.locked || source.classList.contains('is-hidden')) {
            return;
        }

        event.preventDefault();

        createDrag({
            source,
            type: 'tile',
            letter: source.dataset.letter,
            slotIndex: null,
            pointerId: event.pointerId,
            x: event.clientX,
            y: event.clientY
        });
    }

    function startSlotDrag(event, slotIndex) {
        if (state.locked || !state.filled[slotIndex]) {
            return;
        }

        event.preventDefault();

        createDrag({
            source: event.currentTarget,
            type: 'slot',
            letter: state.filled[slotIndex],
            slotIndex,
            pointerId: event.pointerId,
            x: event.clientX,
            y: event.clientY
        });
    }

    function createDrag(options) {
        const ghost = document.createElement('div');

        ghost.className = 'drag-ghost';
        ghost.textContent = options.letter;

        document.body.appendChild(ghost);

        state.drag = {
            source: options.source,
            ghost,
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
        if (!state.drag) return;

        event.preventDefault();

        moveGhost(event.clientX, event.clientY);
        markDropTarget(event.clientX, event.clientY);
    }

    function onPointerUp(event) {
        if (!state.drag) return;

        event.preventDefault();

        const target = getDropTarget(event.clientX, event.clientY);

        clearDropTarget();

        if (target) {
            dropToSlot(target);
        }

        removeGhost();
    }

    function cancelDrag(event) {
        if (event) event.preventDefault();
        if (!state.drag) return;

        clearDropTarget();
        removeGhost();
    }

    function moveGhost(x, y) {
        if (!state.drag || !state.drag.ghost) return;

        state.drag.ghost.style.left = `${x}px`;
        state.drag.ghost.style.top = `${y}px`;
    }

    function getDropTarget(x, y) {
        if (!state.drag || !state.drag.ghost) return null;

        state.drag.ghost.style.visibility = 'hidden';

        const element = document.elementFromPoint(x, y);
        const target = element ? element.closest('.slot') : null;

        state.drag.ghost.style.visibility = 'visible';

        return target;
    }

    function markDropTarget(x, y) {
        const target = getDropTarget(x, y);

        if (target === state.drag.lastTarget) {
            return;
        }

        clearDropTarget();

        if (target && !target.textContent.trim()) {
            target.classList.add('drop-target');
            state.drag.lastTarget = target;
        }
    }

    function clearDropTarget() {
        document.querySelectorAll('.drop-target').forEach(el => {
            el.classList.remove('drop-target');
        });

        if (state.drag) {
            state.drag.lastTarget = null;
        }
    }

    function dropToSlot(target) {
        const targetIndex = Number(target.dataset.index);

        if (!Number.isInteger(targetIndex) || state.filled[targetIndex]) {
            showToast('Это место уже занято.', 'error');
            return;
        }

        if (state.drag.type === 'slot' && state.drag.slotIndex !== null) {
            state.filled[state.drag.slotIndex] = null;
        }

        state.filled[targetIndex] = state.drag.letter;

        if (state.drag.type === 'tile') {
            state.drag.source.classList.add('is-hidden');
        }

        renderSlots();
        checkAutomatically();
    }

    function removeGhost() {
        if (!state.drag) return;

        window.removeEventListener('pointermove', onPointerMove);
        window.removeEventListener('pointerup', onPointerUp);
        window.removeEventListener('pointercancel', cancelDrag);

        state.drag.source.classList.remove('is-dragging');

        if (state.drag.ghost) {
            state.drag.ghost.remove();
        }

        state.drag = null;
    }

    function checkAutomatically() {
        if (!state.current || state.locked) {
            return;
        }

        if (state.filled.some(letter => !letter)) {
            return;
        }

        const attempt = toUpper(state.filled.join(''));
        const correct = toUpper(state.current.word);

        if (attempt === correct) {
            state.correctAnswers += 1;
            state.locked = true;

            showSuccessPop();
            showToast('Правильно!');

            setTimeout(() => {
                loadWord(state.index + 1);
            }, 1200);

            return;
        }

        state.incorrectAnswers += 1;
        markMistakes(correct);
        showToast('Попробуй ещё раз.', 'error');

        setTimeout(resetCurrentWord, 850);
    }

    function resetCurrentWord() {
        const letters = [];

        state.filled.forEach(letter => {
            if (letter) letters.push(letter);
        });

        document.querySelectorAll('.tile:not(.is-hidden)').forEach(tile => {
            letters.push(tile.dataset.letter);
        });

        state.filled = Array(state.current.letters.length).fill(null);

        renderSlots();
        renderPool(shuffle(letters));
    }

    function markMistakes(correct) {
        const slots = [...els.slots.querySelectorAll('.slot')];

        slots.forEach((slot, index) => {
            const isGood = toUpper(slot.textContent) === toUpper(correct[index]);

            slot.classList.add(isGood ? 'is-good' : 'is-bad');

            setTimeout(() => {
                slot.classList.remove('is-good', 'is-bad');
            }, 700);
        });
    }

    async function finishGame() {
        const timeSpent = getTimeSpent();

        stopAnimalSound();

        els.slots.innerHTML = '';
        els.pool.innerHTML = '';
        els.hintText.textContent = 'Игра завершена';
        els.imageWrap.innerHTML = '<span>Все животные угаданы</span>';

        els.finishText.textContent = `Правильно: ${state.correctAnswers}. Ошибок: ${state.incorrectAnswers}.`;

        els.finishCard.classList.add('is-visible');
        els.finishCard.setAttribute('aria-hidden', 'false');

        await saveGameResult(timeSpent);
    }

    function getTimeSpent() {
        if (!state.startTime) return 0;

        return Math.max(0, Math.floor((Date.now() - state.startTime) / 1000));
    }

    async function saveGameResult(timeSpent) {
        if (state.resultSaved) return;

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

        setTimeout(() => {
            els.successPop.classList.remove('is-visible');
        }, 1100);
    }

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');

        toast.className = `toast toast--${type}`;
        toast.textContent = message;

        els.toastContainer.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.add('is-visible');
        });

        setTimeout(() => {
            toast.classList.remove('is-visible');

            setTimeout(() => {
                toast.remove();
            }, 240);
        }, 3000);
    }

    function shuffle(arr) {
        const copy = [...arr];

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