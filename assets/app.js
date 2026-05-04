(function () {
  'use strict';

  const modal = document.getElementById('gameModal');

  if (!modal) {
    return;
  }

  const body = modal.querySelector('.modal__body');
  const title = document.getElementById('gameTitle');
  const closeBtn = document.getElementById('closeGame');

  const loader = document.getElementById('gameLoader');
  const loaderTitle = document.querySelector('.game-loader__title');
  const loaderText = document.querySelector('.game-loader__text');
  const loaderBar = document.getElementById('gameLoaderBar');
  const loaderPercent = document.getElementById('gameLoaderPercent');
  const startLoadedGameBtn = document.getElementById('startLoadedGame');

  let frame = document.getElementById('gameFrame');
  let currentGameReady = false;
  let fakeProgressTimer = null;
  let readyFallbackTimer = null;
  let currentProgress = 0;

  function createFrame() {
    const iframe = document.createElement('iframe');

    iframe.id = 'gameFrame';
    iframe.src = '';
    iframe.loading = 'eager';
    iframe.sandbox = 'allow-scripts allow-same-origin';
    iframe.allow = 'autoplay';
    iframe.title = 'Окно игры';

    return iframe;
  }

  function setProgress(value) {
    currentProgress = Math.max(0, Math.min(100, value));

    if (loaderBar) {
      loaderBar.style.width = `${currentProgress}%`;
    }

    if (loaderPercent) {
      loaderPercent.textContent = `${Math.round(currentProgress)}%`;
    }
  }

  function startFakeProgress() {
    stopFakeProgress();

    currentProgress = 0;
    setProgress(0);

    fakeProgressTimer = setInterval(function () {
      if (currentProgress < 35) {
        setProgress(currentProgress + 6);
        return;
      }

      if (currentProgress < 70) {
        setProgress(currentProgress + 3);
        return;
      }

      if (currentProgress < 90) {
        setProgress(currentProgress + 1);
      }
    }, 180);
  }

  function stopFakeProgress() {
    if (fakeProgressTimer) {
      clearInterval(fakeProgressTimer);
      fakeProgressTimer = null;
    }
  }

  function clearReadyFallback() {
    if (readyFallbackTimer) {
      clearTimeout(readyFallbackTimer);
      readyFallbackTimer = null;
    }
  }

  function showLoader() {
    currentGameReady = false;

    modal.classList.remove('is-ready', 'is-playing');
    modal.classList.add('is-loading');

    if (loader) {
      loader.classList.remove('hidden', 'is-ready', 'is-error');
    }

    if (loaderTitle) {
      loaderTitle.textContent = 'Загрузка игры';
    }

    if (loaderText) {
      loaderText.textContent = 'Подготавливаем картинки и звук...';
    }

    if (startLoadedGameBtn) {
      startLoadedGameBtn.classList.remove('is-visible');
      startLoadedGameBtn.disabled = true;
    }

    setProgress(0);
    startFakeProgress();
  }

  function showReady() {
    if (currentGameReady) {
      return;
    }

    currentGameReady = true;

    stopFakeProgress();
    clearReadyFallback();

    setProgress(100);

    modal.classList.remove('is-loading', 'is-playing');
    modal.classList.add('is-ready');

    if (loader) {
      loader.classList.add('is-ready');
      loader.classList.remove('is-error');
    }

    if (loaderTitle) {
      loaderTitle.textContent = 'Игра готова';
    }

    if (loaderText) {
      loaderText.textContent = 'Все картинки и звуки загружены. Можно начинать!';
    }

    if (startLoadedGameBtn) {
      startLoadedGameBtn.disabled = false;
      startLoadedGameBtn.classList.add('is-visible');
    }
  }

  function showError() {
    stopFakeProgress();
    clearReadyFallback();

    modal.classList.remove('is-ready', 'is-playing');
    modal.classList.add('is-loading');

    if (loader) {
      loader.classList.remove('hidden', 'is-ready');
      loader.classList.add('is-error');
    }

    if (loaderTitle) {
      loaderTitle.textContent = 'Ошибка загрузки';
    }

    if (loaderText) {
      loaderText.textContent = 'Игра не смогла полностью загрузиться. Попробуй закрыть окно и открыть игру снова.';
    }

    if (startLoadedGameBtn) {
      startLoadedGameBtn.disabled = false;
      startLoadedGameBtn.classList.add('is-visible');
      startLoadedGameBtn.textContent = 'Попробовать';
    }
  }

  function startLoadedGame() {
    if (!frame || !currentGameReady) {
      return;
    }

    modal.classList.remove('is-loading', 'is-ready');
    modal.classList.add('is-playing');

    if (loader) {
      loader.classList.add('hidden');
    }

    try {
      frame.contentWindow.postMessage({
        type: 'LOGOPA_START_GAME'
      }, '*');
    } catch (err) { }
  }

  function stopFrameMedia() {
    if (!frame) {
      return;
    }

    try {
      const doc = frame.contentDocument || frame.contentWindow?.document;

      if (!doc) {
        return;
      }

      const mediaList = doc.querySelectorAll('audio, video');

      mediaList.forEach(function (media) {
        try {
          media.pause();
          media.currentTime = 0;
          media.removeAttribute('src');
          media.load();
        } catch (err) { }
      });
    } catch (err) { }
  }

  function resetFrame() {
    if (!frame) {
      return;
    }

    stopFrameMedia();

    try {
      frame.src = 'about:blank';
    } catch (err) { }

    frame.remove();

    frame = createFrame();

    if (body) {
      body.appendChild(frame);
    }
  }

  function openGame(path, gameTitle) {
    if (!path) {
      return;
    }

    if (!frame) {
      frame = createFrame();

      if (body) {
        body.appendChild(frame);
      }
    }

    if (title) {
      title.textContent = gameTitle || 'Игра';
    }

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');

    showLoader();

    frame.src = 'about:blank';

    setTimeout(function () {
      frame.src = path;
    }, 50);

    clearReadyFallback();

    readyFallbackTimer = setTimeout(function () {
      if (!currentGameReady) {
        showError();
      }
    }, 30000);
  }

  function closeGame() {
    modal.classList.add('hidden');
    modal.classList.remove('is-loading', 'is-ready', 'is-playing');
    modal.setAttribute('aria-hidden', 'true');

    document.body.classList.remove('modal-open');

    stopFakeProgress();
    clearReadyFallback();

    currentGameReady = false;
    currentProgress = 0;

    if (loader) {
      loader.classList.add('hidden');
      loader.classList.remove('is-ready', 'is-error');
    }

    if (startLoadedGameBtn) {
      startLoadedGameBtn.disabled = true;
      startLoadedGameBtn.classList.remove('is-visible');
      startLoadedGameBtn.textContent = 'Играть';
    }

    resetFrame();

    if (title) {
      title.textContent = 'Игра';
    }
  }

  window.addEventListener('message', function (event) {
    const data = event.data;

    if (!data || data.type !== 'LOGOPA_GAME_READY') {
      return;
    }

    showReady();
  });

  document.addEventListener('click', function (event) {
    const btn = event.target.closest('[data-game-path]');

    if (!btn) {
      return;
    }

    openGame(btn.dataset.gamePath, btn.dataset.gameTitle);
  });

  if (startLoadedGameBtn) {
    startLoadedGameBtn.addEventListener('click', startLoadedGame);
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', closeGame);
  }

  modal.addEventListener('click', function (event) {
    if (event.target === modal) {
      closeGame();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
      closeGame();
    }
  });

  window.addEventListener('beforeunload', function () {
    resetFrame();
  });
})();