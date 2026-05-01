(function () {
    'use strict';

    const GAME_ID = 3;
    const NOVEL_MAX_FRAMES = 20;

    const SUCCESS_IMAGES_COUNT = 4;
    const SUCCESS_IMAGES_PATH = '../success_img/';

    const LETTERS = 'а б у т о к д и ы г л у'.split(' ').map(letter => letter.toUpperCase());
    const VOWELS = new Set(['А', 'Е', 'Ё', 'И', 'О', 'У', 'Ы', 'Э', 'Ю', 'Я']);

    const ROUND_TIME_MS = 3000;
    const NEXT_DELAY_MS = 700;

    const els = {
        startScreen: document.getElementById('startScreen'),
        novelScreen: document.getElementById('novelScreen'),
        gameScreen: document.getElementById('gameScreen'),

        startBtn: document.getElementById('startBtn'),
        novelFrame: document.getElementById('novelFrame'),
        novelImage: document.getElementById('novelImage'),
        novelSource: document.getElementById('novelSource'),
        novelCounter: document.getElementById('novelCounter'),

        roundPill: document.getElementById('roundPill'),
        letterDisplay: document.getElementById('letterDisplay'),
        feedback: document.getElementById('feedback'),
        vowelBtn: document.getElementById('vowelBtn'),

        finishCard: document.getElementById('finishCard'),
        finishText: document.getElementById('finishText'),
        restartBtn: document.getElementById('restartBtn'),

        successPop: document.getElementById('successPop'),
        successPopImage: document.getElementById('successPopImage'),

        toastContainer: document.getElementById('toastContainer')
    };

    const state = {
        index: 0,
        currentLetter: '',
        roundActive: false,
        finished: false,

        correctAnswers: 0,
        incorrectAnswers: 0,
        startTime: 0,
        resultSaved: false,

        roundTimer: null,
        nextTimer: null,
        roundToken: 0,

        novelFrameIndex: 1,
        availableNovelFrames: [],
        activeAudio: null,

        lastSuccessImageIndex: 0
    };

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        lockViewport();

        els.startBtn.addEventListener('click', startIntro);
        els.novelFrame.addEventListener('click', nextNovelFrame);
        els.vowelBtn.addEventListener('click', handleButtonClick);
        els.restartBtn.addEventListener('click', startGame);

        window.addEventListener('resize', updateNovelSources);

        els.roundPill.textContent = `0/${LETTERS.length}`;
        els.letterDisplay.textContent = '—';
        clearFeedback();

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
                if (i === 1) {
                    continue;
                }

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
            if (index >= candidates.length) {
                return;
            }

            const audio = new Audio(candidates[index]);
            state.activeAudio = audio;
            index += 1;

            audio.addEventListener('error', tryNext, { once: true });
            audio.play().catch(() => { });
        }

        tryNext();
    }

    function stopFrameAudio() {
        if (!state.activeAudio) {
            return;
        }

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

    function startGame() {
        stopFrameAudio();
        clearTimers();

        state.index = 0;
        state.currentLetter = '';
        state.roundActive = false;
        state.finished = false;

        state.correctAnswers = 0;
        state.incorrectAnswers = 0;
        state.startTime = Date.now();
        state.resultSaved = false;

        els.finishCard.classList.remove('is-visible');
        els.finishCard.setAttribute('aria-hidden', 'true');

        els.letterDisplay.textContent = '—';
        els.letterDisplay.classList.remove('is-good', 'is-bad');

        clearFeedback();

        els.vowelBtn.disabled = false;

        showScreen(els.gameScreen);
        updateRoundPill();
        startRound();
    }

    function startRound() {
        clearTimers();

        if (state.finished) {
            return;
        }

        if (state.index >= LETTERS.length) {
            finishGame();
            return;
        }

        state.roundToken += 1;
        state.roundActive = true;

        const token = state.roundToken;

        state.currentLetter = LETTERS[state.index];

        els.letterDisplay.textContent = state.currentLetter;
        els.letterDisplay.classList.remove('is-good', 'is-bad');

        clearFeedback();

        els.vowelBtn.disabled = false;

        updateRoundPill();

        state.roundTimer = setTimeout(() => {
            if (token !== state.roundToken) {
                return;
            }

            if (!state.roundActive || state.finished) {
                return;
            }

            completeRoundByWaiting();
        }, ROUND_TIME_MS);
    }

    function handleButtonClick() {
        if (state.finished || !state.roundActive) {
            return;
        }

        const isVowel = VOWELS.has(state.currentLetter);

        if (isVowel) {
            completeRound(true);
        } else {
            completeRound(false);
        }
    }

    function completeRoundByWaiting() {
        const isVowel = VOWELS.has(state.currentLetter);

        if (isVowel) {
            completeRound(false);
        } else {
            completeRound(true);
        }
    }

    function completeRound(isCorrect) {
        if (!state.roundActive || state.finished) {
            return;
        }

        clearTimers();

        state.roundActive = false;
        els.vowelBtn.disabled = true;

        if (isCorrect) {
            state.correctAnswers += 1;

            setFeedback('Верно!', 'good');
            els.letterDisplay.classList.add('is-good');

            showSuccessPop();
        } else {
            state.incorrectAnswers += 1;

            setFeedback('Ошибка', 'bad');
            els.letterDisplay.classList.add('is-bad');
        }

        state.index += 1;
        updateRoundPill();

        state.nextTimer = setTimeout(() => {
            startRound();
        }, NEXT_DELAY_MS);
    }

    function updateRoundPill() {
        const shown = Math.min(state.index + 1, LETTERS.length);
        els.roundPill.textContent = `${shown}/${LETTERS.length}`;
    }

    function setFeedback(text, type) {
        els.feedback.textContent = text;
        els.feedback.className = `feedback feedback--${type}`;
    }

    function clearFeedback() {
        els.feedback.textContent = '';
        els.feedback.className = 'feedback';
    }

    function clearTimers() {
        if (state.roundTimer) {
            clearTimeout(state.roundTimer);
            state.roundTimer = null;
        }

        if (state.nextTimer) {
            clearTimeout(state.nextTimer);
            state.nextTimer = null;
        }
    }

    async function finishGame() {
        if (state.finished) {
            return;
        }

        clearTimers();

        state.finished = true;
        state.roundActive = false;

        els.vowelBtn.disabled = true;
        els.letterDisplay.textContent = '✓';
        els.letterDisplay.classList.remove('is-bad');
        els.letterDisplay.classList.add('is-good');

        clearFeedback();

        els.roundPill.textContent = `${LETTERS.length}/${LETTERS.length}`;
        els.finishText.textContent = `Правильно: ${state.correctAnswers}. Ошибок: ${state.incorrectAnswers}.`;

        els.finishCard.classList.add('is-visible');
        els.finishCard.setAttribute('aria-hidden', 'false');

        await saveGameResult(getTimeSpent());
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
})();