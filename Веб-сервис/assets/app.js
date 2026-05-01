(function () {
  const modal = document.getElementById('gameModal');
  if (!modal) return;

  const body = modal.querySelector('.modal__body');
  const title = document.getElementById('gameTitle');
  const closeBtn = document.getElementById('closeGame');

  let frame = document.getElementById('gameFrame');

  function createFrame() {
    const iframe = document.createElement('iframe');

    iframe.id = 'gameFrame';
    iframe.src = '';
    iframe.loading = 'lazy';
    iframe.sandbox = 'allow-scripts allow-same-origin';
    iframe.allow = 'autoplay';
    iframe.title = 'Окно игры';

    return iframe;
  }

  function stopFrameMedia() {
    if (!frame) return;

    try {
      const doc = frame.contentDocument || frame.contentWindow?.document;
      if (!doc) return;

      const mediaList = doc.querySelectorAll('audio, video');

      mediaList.forEach((media) => {
        try {
          media.pause();
          media.currentTime = 0;
          media.removeAttribute('src');
          media.load();
        } catch (err) {}
      });
    } catch (err) {}
  }

  function resetFrame() {
    if (!frame) return;

    stopFrameMedia();

    try {
      frame.src = 'about:blank';
    } catch (err) {}

    frame.remove();

    frame = createFrame();

    if (body) {
      body.appendChild(frame);
    }
  }

  function openGame(path, t) {
    if (!path) return;

    if (!frame) {
      frame = createFrame();

      if (body) {
        body.appendChild(frame);
      }
    }

    title.textContent = t || 'Игра';

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');

    document.body.style.overflow = 'hidden';

    frame.src = 'about:blank';

    setTimeout(() => {
      frame.src = path;
    }, 0);
  }

  function closeGame() {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');

    document.body.style.overflow = '';

    resetFrame();

    if (title) {
      title.textContent = 'Игра';
    }
  }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-game-path]');
    if (!btn) return;

    openGame(btn.dataset.gamePath, btn.dataset.gameTitle);
  });

  closeBtn?.addEventListener('click', closeGame);

  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      closeGame();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
      closeGame();
    }
  });

  window.addEventListener('beforeunload', () => {
    resetFrame();
  });
})();