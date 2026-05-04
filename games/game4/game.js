(function () {
  'use strict';

  const GAME_ID = 4;

  const NOVEL_FRAMES = [1, 2];

  const SUCCESS_IMAGES_COUNT = 4;
  const SUCCESS_IMAGES_PATH = '../success_img/';

  const BASE_LETTERS = ['А', 'О', 'У', 'Э', 'Ы'];
  const PAIR_LETTERS = ['Я', 'Ё', 'Ю', 'Е', 'И'];

  const MAP = {
    'А': 'Я',
    'О': 'Ё',
    'У': 'Ю',
    'Э': 'Е',
    'Ы': 'И'
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

    scorePill: document.getElementById('scorePill'),
    pairs: document.getElementById('pairs'),
    tray: document.getElementById('tray'),

    finishCard: document.getElementById('finishCard'),
    finishText: document.getElementById('finishText'),
    restartBtn: document.getElementById('restartBtn'),

    successPop: document.getElementById('successPop'),
    successPopImage: document.getElementById('successPopImage'),

    toastContainer: document.getElementById('toastContainer')
  };

  const state = {
    started: false,
    finished: false,

    score: 0,
    correctAnswers: 0,
    incorrectAnswers: 0,
    startTime: 0,
    resultSaved: false,

    drag: null,

    novelFrameIndex: 1,
    availableNovelFrames: [],
    activeAudio: null,

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
        stopFrameAudio();
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

    if (!els.novelScreen.classList.contains('screen--active') || !state.availableNovelFrames.length) {
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

    state.started = true;
    state.finished = false;

    state.score = 0;
    state.correctAnswers = 0;
    state.incorrectAnswers = 0;
    state.startTime = Date.now();
    state.resultSaved = false;

    if (els.finishCard) {
      els.finishCard.classList.remove('is-visible');
      els.finishCard.setAttribute('aria-hidden', 'true');
    }

    buildPairs();
    buildCards();
    updateScore();

    showScreen(els.gameScreen);
  }

  function buildPairs() {
    if (!els.pairs) {
      return;
    }

    els.pairs.innerHTML = '';

    BASE_LETTERS.forEach(function (base) {
      const pair = document.createElement('div');
      pair.className = 'pair';

      const top = document.createElement('div');
      top.className = 'token token--top';
      top.textContent = base;
      top.setAttribute('aria-label', `Буква ${base}`);

      const bottom = document.createElement('div');
      bottom.className = 'token token--bottom drop-zone';
      bottom.dataset.base = base;
      bottom.dataset.expected = MAP[base];
      bottom.setAttribute('aria-label', `Место для буквы ${MAP[base]}`);

      pair.appendChild(top);
      pair.appendChild(bottom);

      els.pairs.appendChild(pair);
    });
  }

  function buildCards() {
    if (!els.tray) {
      return;
    }

    els.tray.innerHTML = '';

    shuffle(PAIR_LETTERS).forEach(function (letter) {
      const card = document.createElement('button');

      card.type = 'button';
      card.className = 'letter-token';
      card.textContent = letter;
      card.dataset.letter = letter;
      card.setAttribute('aria-label', `Буква ${letter}`);

      card.addEventListener('pointerdown', startDrag);

      els.tray.appendChild(card);
    });
  }

  function startDrag(event) {
    if (!state.started || state.finished) {
      return;
    }

    const source = event.currentTarget;

    if (source.classList.contains('is-disabled')) {
      return;
    }

    event.preventDefault();

    const ghost = document.createElement('div');

    ghost.className = 'drag-ghost';
    ghost.textContent = source.dataset.letter;

    document.body.appendChild(ghost);

    state.drag = {
      source: source,
      ghost: ghost,
      letter: source.dataset.letter,
      lastTarget: null
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

    clearDropHints();

    if (target) {
      dropToPair(target);
    }

    clearDrag();
  }

  function cancelDrag(event) {
    if (event) {
      event.preventDefault();
    }

    clearDropHints();
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
    const target = element ? element.closest('.token--bottom') : null;

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

    clearDropHints();

    if (!target) {
      state.drag.lastTarget = null;
      return;
    }

    if (target.querySelector('.letter-token--locked')) {
      target.classList.add('drop-bad');
      state.drag.lastTarget = target;
      return;
    }

    const isCorrect = target.dataset.expected === state.drag.letter;

    target.classList.add(isCorrect ? 'drop-ok' : 'drop-bad');
    state.drag.lastTarget = target;
  }

  function clearDropHints() {
    document.querySelectorAll('.token--bottom.drop-ok, .token--bottom.drop-bad').forEach(function (el) {
      el.classList.remove('drop-ok', 'drop-bad');
    });

    if (state.drag) {
      state.drag.lastTarget = null;
    }
  }

  function dropToPair(target) {
    if (state.finished || !state.drag) {
      return;
    }

    if (target.querySelector('.letter-token--locked')) {
      state.incorrectAnswers += 1;
      updateScore();
      showToast('Тут уже занято.', 'error');
      return;
    }

    const expected = target.dataset.expected;
    const isCorrect = expected === state.drag.letter;

    if (!isCorrect) {
      state.incorrectAnswers += 1;
      updateScore();
      showToast('Попробуй другую пару.', 'error');
      return;
    }

    const fixedCard = document.createElement('div');

    fixedCard.className = 'letter-token letter-token--locked';
    fixedCard.textContent = state.drag.letter;

    target.appendChild(fixedCard);

    if (state.drag.source) {
      state.drag.source.remove();
    }

    state.score += 1;
    state.correctAnswers += 1;

    updateScore();
    showSuccessPop();
    showToast('Верно!');

    if (state.score >= BASE_LETTERS.length) {
      setTimeout(finishGame, 700);
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

  function updateScore() {
    if (!els.scorePill) {
      return;
    }

    els.scorePill.textContent = `${state.score}/${BASE_LETTERS.length}`;
  }

  async function finishGame() {
    if (state.finished) {
      return;
    }

    state.finished = true;
    state.started = false;

    document.querySelectorAll('.letter-token').forEach(function (card) {
      card.classList.add('is-disabled');
    });

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
    }, 2600);
  }

  function shuffle(arr) {
    const copy = [...arr];

    for (let i = copy.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));

      [copy[i], copy[j]] = [copy[j], copy[i]];
    }

    return copy;
  }
})();