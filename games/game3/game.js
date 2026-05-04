(function () {
    'use strict';

    const GAME_ID = 3;

    const NOVEL_FRAMES = [1, 2, 3];

    const SUCCESS_IMAGES_COUNT = 4;
    const SUCCESS_IMAGES_PATH = '../success_img/';

    const LETTERS = 'а б у т о к д и ы г л у'.split(' ').map(function (letter) {
        return letter.toUpperCase();
    });

    const VOWELS = new Set(['А', 'Е', 'Ё', 'И', 'О', 'У', 'Ы', 'Э', 'Ю', 'Я']);

    const ROUND_TIME_MS = 3000;
    const NEXT_DELAY_MS = 700;
    const COUNTDOWN_STEP_MS = 780;

    // Папка со звуками букв.
    // Путь считается от index.html игры 3.
    const LETTER_AUDIO_PATH = 'letter_audio/';

    // Новые буквы НЕ добавлены.
    // Здесь только звуки для букв, которые уже есть в LETTERS.
    const LETTER_AUDIO_FILES = {
        'А': 'a.mp3',
        'Б': 'b.mp3',
        'У': 'u.mp3',
        'Т': 't.mp3',
        'О': 'o.mp3',
        'К': 'k.mp3',
        'Д': 'd.mp3',
        'И': 'i.mp3',
        'Ы': 'y.mp3',
        'Г': 'g.mp3',
        'Л': 'l.mp3'
    };

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

        roundTimer: document.getElementById('roundTimer'),
        roundTimerFill: document.getElementById('roundTimerFill'),

        countdownOverlay: document.getElementById('countdownOverlay'),
        countdownNumber: document.getElementById('countdownNumber'),

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
        lastLetter: '',
        roundActive: false,
        finished: false,
        introStarted: false,

        correctAnswers: 0,
        incorrectAnswers: 0,
        startTime: 0,
        resultSaved: false,

        roundTimer: null,
        nextTimer: null,
        countdownTimer: null,
        roundToken: 0,

        novelFrameIndex: 1,
        availableNovelFrames: [],
        activeAudio: null,

        activeLetterAudio: null,

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

        if (els.restartBtn) {
            els.restartBtn.addEventListener('click', startGame);
        }

        if (els.vowelBtn) {
            els.vowelBtn.addEventListener('click', handleButtonClick);
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

        state.introStarted = true;
        state.finished = false;
        state.roundActive = false;
        state.index = 0;
        state.currentLetter = '';

        hideCountdown();
        resetRoundTimerSlider();

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

    function getLetterAudioSrc(letter) {
        const fileName = LETTER_AUDIO_FILES[letter];

        if (!fileName) {
            return '';
        }

        return `${LETTER_AUDIO_PATH}${fileName}`;
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

    function preloadLetterAudio() {
        const tasks = [];

        Object.keys(LETTER_AUDIO_FILES).forEach(function (letter) {
            const src = getLetterAudioSrc(letter);

            if (src) {
                tasks.push(preloadAudio(src));
            }
        });

        return Promise.allSettled(tasks);
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

        await preloadLetterAudio();
    }

    function notifyParentGameReady() {
        try {
            window.parent.postMessage({
                type: 'LOGOPA_GAME_READY'
            }, '*');
        } catch (err) { }
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

            audio.play().catch(function () { });
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
        } catch (err) { }

        state.activeAudio = null;
    }

    function playLetterAudio(letter) {
        stopLetterAudio();

        const src = getLetterAudioSrc(letter);

        if (!src) {
            return;
        }

        try {
            const audio = new Audio(src);

            state.activeLetterAudio = audio;

            audio.volume = 1;

            audio.addEventListener('ended', function () {
                if (state.activeLetterAudio === audio) {
                    state.activeLetterAudio = null;
                }
            }, { once: true });

            audio.play().catch(function () { });
        } catch (err) { }
    }

    function stopLetterAudio() {
        if (!state.activeLetterAudio) {
            return;
        }

        try {
            state.activeLetterAudio.pause();
            state.activeLetterAudio.currentTime = 0;
        } catch (err) { }

        state.activeLetterAudio = null;
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
        stopLetterAudio();
        clearTimers();

        state.index = 0;
        state.currentLetter = '';
        state.roundActive = false;
        state.finished = false;
        state.introStarted = true;

        state.correctAnswers = 0;
        state.incorrectAnswers = 0;
        state.startTime = Date.now();
        state.resultSaved = false;

        state.roundToken += 1;

        hideCountdown();
        resetRoundTimerSlider();

        if (els.finishCard) {
            els.finishCard.classList.remove('is-visible');
            els.finishCard.setAttribute('aria-hidden', 'true');
        }

        if (els.letterDisplay) {
            els.letterDisplay.textContent = '—';
            els.letterDisplay.classList.remove('is-good', 'is-bad');
        }

        clearFeedback();

        if (els.vowelBtn) {
            els.vowelBtn.disabled = true;
        }

        showScreen(els.gameScreen);
        updateRoundPill();

        startFirstRoundCountdown();
    }

    function startFirstRoundCountdown() {
        clearTimers();
        resetRoundTimerSlider();

        state.roundActive = false;
        state.roundToken += 1;

        if (els.vowelBtn) {
            els.vowelBtn.disabled = true;
        }

        if (!els.countdownOverlay || !els.countdownNumber) {
            startRound();
            return;
        }

        els.countdownOverlay.classList.add('is-visible');
        els.countdownOverlay.setAttribute('aria-hidden', 'false');

        const numbers = ['3', '2', '1'];
        let index = 0;

        function showNextNumber() {
            if (state.finished) {
                hideCountdown();
                return;
            }

            if (index >= numbers.length) {
                hideCountdown();
                startRound();
                return;
            }

            els.countdownNumber.textContent = numbers[index];
            els.countdownNumber.classList.remove('is-pop');

            void els.countdownNumber.offsetWidth;

            els.countdownNumber.classList.add('is-pop');

            index += 1;

            state.countdownTimer = setTimeout(showNextNumber, COUNTDOWN_STEP_MS);
        }

        showNextNumber();
    }

    function hideCountdown() {
        if (els.countdownOverlay) {
            els.countdownOverlay.classList.remove('is-visible');
            els.countdownOverlay.setAttribute('aria-hidden', 'true');
        }

        if (els.countdownNumber) {
            els.countdownNumber.classList.remove('is-pop');
        }
    }
    function getRandomLetter() {
        let randomLetter = LETTERS[Math.floor(Math.random() * LETTERS.length)];

        if (LETTERS.length > 1) {
            while (randomLetter === state.lastLetter) {
                randomLetter = LETTERS[Math.floor(Math.random() * LETTERS.length)];
            }
        }

        state.lastLetter = randomLetter;

        return randomLetter;
    }

    function startRound() {
        clearTimers();
        resetRoundTimerSlider();
        stopLetterAudio();

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

        state.currentLetter = getRandomLetter();

        if (els.letterDisplay) {
            els.letterDisplay.textContent = state.currentLetter;
            els.letterDisplay.classList.remove('is-good', 'is-bad');
        }

        // Главная правка:
        // когда буква показана на экране, сразу проигрываем её звук.
        playLetterAudio(state.currentLetter);

        clearFeedback();

        if (els.vowelBtn) {
            els.vowelBtn.disabled = false;
        }

        updateRoundPill();
        startRoundTimerSlider();

        state.roundTimer = setTimeout(function () {
            if (token !== state.roundToken) {
                return;
            }

            if (!state.roundActive || state.finished) {
                return;
            }

            completeRoundByWaiting();
        }, ROUND_TIME_MS);
    }

    function startRoundTimerSlider() {
        if (!els.roundTimerFill) {
            return;
        }

        els.roundTimerFill.classList.remove('is-running');
        els.roundTimerFill.style.animationDuration = '';
        els.roundTimerFill.style.transform = 'scaleX(1)';

        void els.roundTimerFill.offsetWidth;

        els.roundTimerFill.style.animationDuration = `${ROUND_TIME_MS}ms`;
        els.roundTimerFill.classList.add('is-running');
    }

    function stopRoundTimerSlider() {
        if (!els.roundTimerFill) {
            return;
        }

        const computed = window.getComputedStyle(els.roundTimerFill);
        const currentTransform = computed.transform;

        els.roundTimerFill.classList.remove('is-running');
        els.roundTimerFill.style.animationDuration = '';

        if (currentTransform && currentTransform !== 'none') {
            els.roundTimerFill.style.transform = currentTransform;
        }
    }

    function resetRoundTimerSlider() {
        if (!els.roundTimerFill) {
            return;
        }

        els.roundTimerFill.classList.remove('is-running');
        els.roundTimerFill.style.animationDuration = '';
        els.roundTimerFill.style.transform = 'scaleX(1)';
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
        if (state.finished || !state.roundActive) {
            return;
        }

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
        stopRoundTimerSlider();

        state.roundActive = false;

        if (els.vowelBtn) {
            els.vowelBtn.disabled = true;
        }

        if (isCorrect) {
            state.correctAnswers += 1;

            setFeedback('Верно!', 'good');

            if (els.letterDisplay) {
                els.letterDisplay.classList.add('is-good');
            }

            showSuccessPop();
        } else {
            state.incorrectAnswers += 1;

            setFeedback('Ошибка', 'bad');

            if (els.letterDisplay) {
                els.letterDisplay.classList.add('is-bad');
            }
        }

        state.index += 1;
        updateRoundPill();

        state.nextTimer = setTimeout(function () {
            startRound();
        }, NEXT_DELAY_MS);
    }

    function updateRoundPill() {
        if (!els.roundPill) {
            return;
        }

        const shown = Math.min(state.index + 1, LETTERS.length);
        els.roundPill.textContent = `${shown}/${LETTERS.length}`;
    }

    function setFeedback(text, type) {
        if (!els.feedback) {
            return;
        }

        els.feedback.textContent = text;
        els.feedback.className = `feedback feedback--${type}`;
    }

    function clearFeedback() {
        if (!els.feedback) {
            return;
        }

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

        if (state.countdownTimer) {
            clearTimeout(state.countdownTimer);
            state.countdownTimer = null;
        }
    }

    function stopAllGameActivity() {
        clearTimers();
        hideCountdown();
        resetRoundTimerSlider();
        stopFrameAudio();
        stopLetterAudio();

        state.roundActive = false;
        state.finished = true;
        state.roundToken += 1;

        if (els.vowelBtn) {
            els.vowelBtn.disabled = true;
        }
    }

    async function finishGame() {
        if (state.finished) {
            return;
        }

        clearTimers();
        resetRoundTimerSlider();
        hideCountdown();
        stopLetterAudio();

        state.finished = true;
        state.roundActive = false;

        if (els.vowelBtn) {
            els.vowelBtn.disabled = true;
        }

        if (els.letterDisplay) {
            els.letterDisplay.textContent = '✓';
            els.letterDisplay.classList.remove('is-bad');
            els.letterDisplay.classList.add('is-good');
        }

        clearFeedback();

        if (els.roundPill) {
            els.roundPill.textContent = `${LETTERS.length}/${LETTERS.length}`;
        }

        if (els.finishText) {
            els.finishText.textContent = `Правильно: ${state.correctAnswers}. Ошибок: ${state.incorrectAnswers}.`;
        }

        if (els.finishCard) {
            els.finishCard.classList.add('is-visible');
            els.finishCard.setAttribute('aria-hidden', 'false');
        }

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
})();