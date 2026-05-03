(function () {
    'use strict';

    const GAME_ID = 1;
    const MAX_ERRORS = 3;

    const NOVEL_FRAMES = [1, 2, 3];

    const SUCCESS_IMAGES_COUNT = 4;
    const SUCCESS_IMAGES_PATH = '../success_img/';

    const VOWELS = ['А', 'Е', 'Ё', 'И', 'О', 'У', 'Ы', 'Э', 'Ю', 'Я'];

    const LEVELS = [
        {
            word: 'КАША',
            extraLetters: ['А', 'Е', 'И', 'О']
        },
        {
            word: 'ТОРТ',
            extraLetters: ['А', 'Е', 'И', 'У']
        },
        {
            word: 'СУП',
            extraLetters: ['А', 'Е', 'О', 'Я']
        },
        {
            word: 'ДОМ',
            extraLetters: ['А', 'Е', 'У']
        },
        {
            word: 'МЯЧ',
            extraLetters: ['И', 'О', 'А']
        },
        {
            word: 'МИР',
            extraLetters: ['А', 'Е', 'У', 'Ы']
        }
    ];

    const els = {
        startScreen: document.getElementById('startScreen'),
        novelScreen: document.getElementById('novelScreen'),
        gameScreen: document.getElementById('gameScreen'),

        startBtn: document.getElementById('startBtn'),
        novelFrame: document.getElementById('novelFrame'),
        novelImage: document.getElementById('novelImage'),
        novelSource: document.getElementById('novelSource'),
        novelCounter: document.getElementById('novelCounter'),

        wordsContainer: document.getElementById('wordsContainer'),
        lettersContainer: document.getElementById('lettersContainer'),

        paintPanel: document.getElementById('paintPanel'),
        redPaint: document.getElementById('redPaint'),

        resetBtn: document.getElementById('resetBtn'),
        restartBtn: document.getElementById('restartBtn'),

        levelPill: document.getElementById('levelPill'),

        finishCard: document.getElementById('finishCard'),
        finishText: document.getElementById('finishText'),

        successPop: document.getElementById('successPop'),
        successPopImage: document.getElementById('successPopImage'),

        toastContainer: document.getElementById('toastContainer')
    };

    const state = {
        levelIndex: 0,
        errors: 0,
        correctAnswers: 0,
        incorrectAnswers: 0,
        startTime: 0,
        resultSaved: false,

        novelFrameIndex: 1,
        availableNovelFrames: [],
        activeAudio: null,

        drag: null,
        paintMode: false,
        levelCompleted: false,
        gameStarted: false,
        gameFinished: false,

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

        if (els.resetBtn) {
            els.resetBtn.addEventListener('click', resetCurrentLevel);
        }

        if (els.restartBtn) {
            els.restartBtn.addEventListener('click', startGame);
        }

        if (els.redPaint) {
            els.redPaint.dataset.dragType = 'paint';
            els.redPaint.disabled = true;
            els.redPaint.addEventListener('pointerdown', startDrag);
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

        state.gameFinished = false;
        state.gameStarted = false;

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

    function startGame() {
        stopFrameAudio();
        clearDrag();

        state.levelIndex = 0;
        state.errors = 0;
        state.correctAnswers = 0;
        state.incorrectAnswers = 0;
        state.resultSaved = false;
        state.startTime = Date.now();

        state.paintMode = false;
        state.levelCompleted = false;
        state.gameStarted = true;
        state.gameFinished = false;

        if (els.finishCard) {
            els.finishCard.classList.remove('is-visible');
            els.finishCard.setAttribute('aria-hidden', 'true');
        }

        showScreen(els.gameScreen);
        loadLevel(0);
    }

    function loadLevel(levelIndex) {
        clearDrag();

        if (levelIndex >= LEVELS.length) {
            finishGame();
            return;
        }

        state.levelIndex = levelIndex;
        state.errors = 0;
        state.paintMode = false;
        state.levelCompleted = false;

        if (els.levelPill) {
            els.levelPill.textContent = `${levelIndex + 1}/${LEVELS.length}`;
        }

        if (els.wordsContainer) {
            els.wordsContainer.innerHTML = '';
        }

        if (els.lettersContainer) {
            els.lettersContainer.innerHTML = '';
        }

        if (els.finishCard) {
            els.finishCard.classList.remove('is-visible');
            els.finishCard.setAttribute('aria-hidden', 'true');
        }

        createWord(LEVELS[levelIndex].word);
        createLetters(prepareLetters(LEVELS[levelIndex]));

        if (els.resetBtn) {
            els.resetBtn.textContent = 'Сбросить';
        }

        hidePaintTool();
    }

    function prepareLetters(level) {
        const required = getRequiredVowels(level.word);
        const letters = required.concat(level.extraLetters);

        return shuffle(letters);
    }

    function getRequiredVowels(word) {
        return word
            .toUpperCase()
            .split('')
            .filter(function (char) {
                return VOWELS.includes(char);
            });
    }

    function createWord(word) {
        if (!els.wordsContainer) {
            return;
        }

        const wordEl = document.createElement('div');

        wordEl.className = 'word';
        wordEl.id = 'currentWord';

        word.toUpperCase().split('').forEach(function (char) {
            const charEl = document.createElement('div');

            charEl.className = 'char';

            if (VOWELS.includes(char)) {
                charEl.classList.add('placeholder');
                charEl.textContent = '_';
                charEl.dataset.correctChar = char;
            } else {
                charEl.classList.add('static');
                charEl.textContent = char;
            }

            wordEl.appendChild(charEl);
        });

        els.wordsContainer.appendChild(wordEl);
    }

    function createLetters(letters) {
        if (!els.lettersContainer) {
            return;
        }

        letters.forEach(function (char, index) {
            const letter = document.createElement('button');

            letter.type = 'button';
            letter.className = 'letter';
            letter.textContent = char;
            letter.dataset.dragType = 'letter';
            letter.dataset.letter = char;
            letter.dataset.letterId = `letter-${index}-${Math.random().toString(36).slice(2)}`;
            letter.setAttribute('aria-label', `Буква ${char}`);

            letter.addEventListener('pointerdown', startDrag);

            els.lettersContainer.appendChild(letter);
        });
    }

    function startDrag(event) {
        if (!state.gameStarted || state.gameFinished) {
            return;
        }

        const source = event.currentTarget;
        const dragType = source.dataset.dragType;

        if (source.disabled || source.classList.contains('is-hidden') || state.levelCompleted) {
            return;
        }

        if (dragType === 'paint' && !state.paintMode) {
            return;
        }

        event.preventDefault();

        const ghost = document.createElement('div');

        ghost.className = dragType === 'paint'
            ? 'drag-ghost drag-ghost--paint'
            : 'drag-ghost';

        ghost.textContent = dragType === 'paint' ? '🖌️' : source.dataset.letter;

        document.body.appendChild(ghost);

        state.drag = {
            source: source,
            ghost: ghost,
            type: dragType,
            letter: source.dataset.letter || '',
            sourceId: source.dataset.letterId || '',
            lastTarget: null,
            pointerId: event.pointerId
        };

        source.classList.add('is-dragging');

        moveGhost(event.clientX, event.clientY);

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

        if (state.drag.type === 'letter') {
            dropLetter(target);
        }

        if (state.drag && state.drag.type === 'paint') {
            dropPaint(target);
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
        const target = element ? element.closest('.char') : null;

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

        if (
            target &&
            state.drag.type === 'letter' &&
            target.classList.contains('placeholder')
        ) {
            target.classList.add('drop-target');
            state.drag.lastTarget = target;
            return;
        }

        if (
            target &&
            state.drag.type === 'paint' &&
            target.classList.contains('filled') &&
            !target.classList.contains('red-vowel')
        ) {
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

    function dropLetter(target) {
        if (state.paintMode || state.levelCompleted || state.gameFinished) {
            return;
        }

        if (!target) {
            showToast('Перетащи букву в пустое место.', 'error');
            return;
        }

        if (!target.classList.contains('placeholder')) {
            showToast('Место занято.', 'error');
            return;
        }

        target.textContent = state.drag.letter;
        target.classList.remove('placeholder', 'error');
        target.classList.add('filled');
        target.dataset.originalId = state.drag.sourceId;

        if (state.drag.source) {
            state.drag.source.classList.add('is-hidden');
        }

        checkWordAutomatically();
    }

    function checkWordAutomatically() {
        const level = LEVELS[state.levelIndex];

        if (!level) {
            return;
        }

        const requiredCount = getRequiredVowels(level.word).length;
        const filled = Array.from(document.querySelectorAll('#currentWord .filled'));

        if (filled.length < requiredCount) {
            return;
        }

        let isCorrect = true;

        filled.forEach(function (char) {
            if (char.textContent !== char.dataset.correctChar) {
                isCorrect = false;
                char.classList.add('error');
            } else {
                char.classList.remove('error');
            }
        });

        if (isCorrect) {
            openPaintMode();
            return;
        }

        state.errors += 1;
        state.incorrectAnswers += 1;

        if (state.errors >= MAX_ERRORS) {
            showToast('Попытки закончились.', 'error');

            setTimeout(function () {
                if (els.resetBtn) {
                    els.resetBtn.textContent = state.levelIndex + 1 >= LEVELS.length ? 'Завершить' : 'Дальше';
                }
            }, 450);

            return;
        }

        showToast('Попробуй ещё раз.', 'error');

        setTimeout(function () {
            resetWordOnly();
        }, 450);
    }

    function openPaintMode() {
        state.paintMode = true;

        showSuccessPop();
        showPaintTool();
    }

    function showPaintTool() {
        if (els.redPaint) {
            els.redPaint.disabled = false;
        }

        if (els.paintPanel) {
            els.paintPanel.classList.add('is-active');
            els.paintPanel.setAttribute('aria-hidden', 'false');
        }
    }

    function hidePaintTool() {
        state.paintMode = false;

        if (els.redPaint) {
            els.redPaint.disabled = true;
        }

        if (els.paintPanel) {
            els.paintPanel.classList.remove('is-active');
            els.paintPanel.setAttribute('aria-hidden', 'true');
        }
    }

    function dropPaint(target) {
        if (!target || !state.paintMode || state.levelCompleted || state.gameFinished) {
            return;
        }

        if (target.classList.contains('filled') && VOWELS.includes(target.textContent.toUpperCase())) {
            target.classList.add('red-vowel');
            checkAllPainted();
        }
    }

    function checkAllPainted() {
        const level = LEVELS[state.levelIndex];

        if (!level) {
            return;
        }

        const requiredCount = getRequiredVowels(level.word).length;
        const painted = document.querySelectorAll('#currentWord .filled.red-vowel').length;

        if (painted < requiredCount) {
            return;
        }

        state.correctAnswers += 1;
        state.levelCompleted = true;

        hidePaintTool();

        if (els.resetBtn) {
            els.resetBtn.textContent = state.levelIndex + 1 >= LEVELS.length ? 'Завершить' : 'Дальше';
        }

        showToast('Готово!');
    }

    function resetCurrentLevel() {
        if (!state.gameStarted || state.gameFinished) {
            return;
        }

        if (!els.resetBtn) {
            return;
        }

        const text = els.resetBtn.textContent.trim();

        if (text === 'Дальше') {
            loadLevel(state.levelIndex + 1);
            return;
        }

        if (text === 'Завершить') {
            finishGame();
            return;
        }

        loadLevel(state.levelIndex);
    }

    function resetWordOnly() {
        document.querySelectorAll('#currentWord .char:not(.static)').forEach(function (char) {
            const originalId = char.dataset.originalId;

            const originalLetter = originalId
                ? Array.from(document.querySelectorAll('.letter')).find(function (letter) {
                    return letter.dataset.letterId === originalId;
                })
                : null;

            if (originalLetter) {
                originalLetter.classList.remove('is-hidden');
            }

            char.textContent = '_';
            char.classList.remove('filled', 'red-vowel', 'error');
            char.classList.add('placeholder');

            delete char.dataset.originalId;
        });

        hidePaintTool();
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

    async function finishGame() {
        if (state.gameFinished) {
            return;
        }

        state.gameFinished = true;
        state.gameStarted = false;
        state.paintMode = false;
        state.levelCompleted = true;

        const timeSpent = getTimeSpent();

        clearDrag();
        stopFrameAudio();

        if (els.wordsContainer) {
            els.wordsContainer.innerHTML = '';
        }

        if (els.lettersContainer) {
            els.lettersContainer.innerHTML = '';
        }

        hidePaintTool();

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

    function stopAllGameActivity() {
        clearDrag();
        stopFrameAudio();

        state.paintMode = false;
        state.levelCompleted = false;

        hidePaintTool();
    }

    function shuffle(arr) {
        const copy = arr.slice();

        for (let i = copy.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));

            const temp = copy[i];
            copy[i] = copy[j];
            copy[j] = temp;
        }

        return copy;
    }
})();