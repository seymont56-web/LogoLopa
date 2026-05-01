(function () {
  'use strict';

  const GAME_ID = 5;
  const NOVEL_MAX_FRAMES = 20;

  const SUCCESS_IMAGES_COUNT = 4;
  const SUCCESS_IMAGES_PATH = '../success_img/';

  const SOFT = ['И', 'Ё', 'Е', 'Ю', 'Я'];
  const HARD = ['Э', 'Ы', 'О', 'У', 'А'];
  const ALL = [...SOFT, ...HARD];

  const softSet = new Set(SOFT);
  const hardSet = new Set(HARD);

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

    hardLadder: document.getElementById('hardLadder'),
    softLadder: document.getElementById('softLadder'),
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

    cardQueue: [],
    drag: null,

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
    els.restartBtn.addEventListener('click', startGame);

    window.addEventListener('resize', handleResize);
    window.addEventListener('orientationchange', handleResize);

    rebuildLadders(false);
    updateScore();

    showScreen(els.startScreen);
  }

  function isMobileMode() {
    return window.matchMedia('(orientation: portrait)').matches || window.innerWidth <= 700;
  }

  function handleResize() {
    rebuildLadders(true);

    if (state.started && !state.finished) {
      buildCardsForCurrentMode(true);
    }
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
      audio.play().catch(() => {});
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
    clearDrag();

    state.started = true;
    state.finished = false;

    state.score = 0;
    state.correctAnswers = 0;
    state.incorrectAnswers = 0;
    state.startTime = Date.now();
    state.resultSaved = false;

    state.cardQueue = shuffle(ALL);

    els.finishCard.classList.remove('is-visible');
    els.finishCard.setAttribute('aria-hidden', 'true');

    els.tray.innerHTML = '';

    rebuildLadders(false);
    updateScore();
    buildCardsForCurrentMode(true);

    showScreen(els.gameScreen);
  }

  function expectedLadder(letter) {
    if (softSet.has(letter)) {
      return 'soft';
    }

    if (hardSet.has(letter)) {
      return 'hard';
    }

    return null;
  }

  function buildCardsForCurrentMode(keepExistingQueue) {
    els.tray.innerHTML = '';

    if (!state.started || state.finished) {
      return;
    }

    if (!keepExistingQueue || !state.cardQueue.length) {
      state.cardQueue = shuffle(getRemainingLetters());
    }

    if (isMobileMode()) {
      showOneMobileCard();
    } else {
      showAllDesktopCards();
    }
  }

  function getRemainingLetters() {
    const placed = new Set();

    document.querySelectorAll('.letter-card--locked').forEach(card => {
      if (card.dataset.letter) {
        placed.add(card.dataset.letter);
      }
    });

    return ALL.filter(letter => !placed.has(letter));
  }

  function showOneMobileCard() {
    const nextLetter = state.cardQueue[0];

    if (!nextLetter) {
      return;
    }

    els.tray.innerHTML = '';
    els.tray.appendChild(createLetterCard(nextLetter));
  }

  function showAllDesktopCards() {
    els.tray.innerHTML = '';

    state.cardQueue.forEach(letter => {
      els.tray.appendChild(createLetterCard(letter));
    });
  }

  function createLetterCard(letter) {
    const card = document.createElement('button');

    card.type = 'button';
    card.className = 'letter-card';
    card.textContent = letter;
    card.dataset.letter = letter;
    card.setAttribute('aria-label', `Буква ${letter}`);

    card.addEventListener('pointerdown', startDrag);

    return card;
  }

  function rebuildLadders(keepLockedCards = true) {
    requestAnimationFrame(() => {
      buildLadder(els.hardLadder, 'hard', keepLockedCards);
      buildLadder(els.softLadder, 'soft', keepLockedCards);
    });
  }

  function buildLadder(container, ladderName, keepLockedCards = true) {
    const oldLockedByIndex = new Map();

    if (keepLockedCards) {
      container.querySelectorAll('.step-zone').forEach(zone => {
        const locked = zone.querySelector('.letter-card--locked');

        if (locked) {
          oldLockedByIndex.set(zone.dataset.index, locked.dataset.letter);
        }
      });
    }

    container.innerHTML = '';

    const rect = container.getBoundingClientRect();
    const width = Math.max(1, rect.width);
    const height = Math.max(1, rect.height);

    const mobile = isMobileMode();

    const stepSize = mobile
      ? Math.max(52, Math.min(92, Math.floor(Math.min(width / 5.1, height / 2.15))))
      : Math.max(38, Math.min(86, Math.floor(Math.min(width / 4.8, height / 3.6))));

    const gapX = mobile
      ? Math.round(stepSize * 0.82)
      : Math.round(stepSize * 0.78);

    const gapY = mobile
      ? Math.round(stepSize * 0.34)
      : Math.round(stepSize * 0.56);

    const totalWidth = gapX * 4 + stepSize;
    const totalHeight = gapY * 4 + stepSize;

    const startX = Math.max(0, Math.round((width - totalWidth) / 2));
    const startY = Math.max(0, Math.round((height - totalHeight) / 2));

    const coords = [
      { x: 0, y: 4 },
      { x: 1, y: 3 },
      { x: 2, y: 2 },
      { x: 3, y: 1 },
      { x: 4, y: 0 }
    ];

    coords.forEach((point, index) => {
      const zone = document.createElement('div');

      zone.className = 'step-zone';
      zone.dataset.ladder = ladderName;
      zone.dataset.index = String(index);

      zone.style.setProperty('--step-size', `${stepSize}px`);
      zone.style.left = `${startX + point.x * gapX}px`;
      zone.style.top = `${startY + point.y * gapY}px`;

      const oldLetter = oldLockedByIndex.get(String(index));

      if (keepLockedCards && oldLetter) {
        const locked = document.createElement('div');

        locked.className = 'letter-card letter-card--locked';
        locked.textContent = oldLetter;
        locked.dataset.letter = oldLetter;

        zone.appendChild(locked);
      }

      container.appendChild(zone);
    });
  }

  function startDrag(event) {
    if (!state.started || state.finished) {
      return;
    }

    const source = event.currentTarget;

    if (source.classList.contains('is-disabled') || source.classList.contains('letter-card--locked')) {
      return;
    }

    event.preventDefault();

    const ghost = document.createElement('div');

    ghost.className = 'letter-card drag-ghost';
    ghost.textContent = source.dataset.letter;
    ghost.dataset.letter = source.dataset.letter;

    document.body.appendChild(ghost);

    state.drag = {
      source,
      ghost,
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

    clearHints();

    if (target) {
      dropToLadder(target);
    }

    clearDrag();
  }

  function cancelDrag(event) {
    if (event) {
      event.preventDefault();
    }

    clearHints();
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
    const target = element ? element.closest('.step-zone') : null;

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

    clearHints();

    if (!target) {
      state.drag.lastTarget = null;
      return;
    }

    target.classList.add('is-hover');
    state.drag.lastTarget = target;
  }

  function clearHints() {
    document.querySelectorAll('.step-zone.is-hover').forEach(zone => {
      zone.classList.remove('is-hover');
    });

    if (state.drag) {
      state.drag.lastTarget = null;
    }
  }

  function dropToLadder(target) {
    if (state.finished || !state.drag) {
      return;
    }

    if (target.querySelector('.letter-card--locked')) {
      state.incorrectAnswers += 1;
      updateScore();
      showToast('Эта ступенька уже занята.', 'error');
      return;
    }

    const need = expectedLadder(state.drag.letter);
    const isCorrect = target.dataset.ladder === need;

    if (!isCorrect) {
      state.incorrectAnswers += 1;
      updateScore();
      showToast('Попробуй другую лестницу.', 'error');
      return;
    }

    const fixedCard = document.createElement('div');

    fixedCard.className = 'letter-card letter-card--locked';
    fixedCard.textContent = state.drag.letter;
    fixedCard.dataset.letter = state.drag.letter;

    target.appendChild(fixedCard);

    if (state.drag.source) {
      state.drag.source.remove();
    }

    state.cardQueue = state.cardQueue.filter(letter => letter !== state.drag.letter);

    state.score += 1;
    state.correctAnswers += 1;

    updateScore();
    showSuccessPop();
    showToast('Верно!');

    if (state.score >= ALL.length) {
      els.tray.innerHTML = '';
      setTimeout(finishGame, 700);
      return;
    }

    if (isMobileMode()) {
      setTimeout(() => {
        showOneMobileCard();
      }, 250);
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
    els.scorePill.textContent = `${state.score}/${ALL.length}`;
  }

  async function finishGame() {
    if (state.finished) {
      return;
    }

    state.finished = true;
    state.started = false;

    document.querySelectorAll('.letter-card').forEach(card => {
      card.classList.add('is-disabled');
    });

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