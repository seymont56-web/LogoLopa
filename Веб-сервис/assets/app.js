(function () {
  const modal = document.getElementById('gameModal');
  if (!modal) return;

  const frame = document.getElementById('gameFrame');
  const title = document.getElementById('gameTitle');
  const closeBtn = document.getElementById('closeGame');

  function openGame(path, t) {
    frame.src = path;
    title.textContent = t || 'Игра';

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');

    document.body.style.overflow = 'hidden';
  }

  function closeGame() {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');

    frame.src = '';
    document.body.style.overflow = '';
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
})();