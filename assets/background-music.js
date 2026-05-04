(function () {
  'use strict';

  // Фоновые треки. Положите свои 6 файлов в папку assets/music/ с такими именами:
  // bg1.mp3, bg2.mp3, bg3.mp3, bg4.mp3, bg5.mp3, bg6.mp3
  const tracks = [
    '../../assets/music/bg1.mp3',
    '../../assets/music/bg2.mp3',
    '../../assets/music/bg3.mp3',
    '../../assets/music/bg4.mp3',
    '../../assets/music/bg5.mp3',
    '../../assets/music/bg6.mp3'
  ];

  const STORAGE_KEY = 'logop_bg_music_muted';
  const VOLUME = 0.12; // тихая громкость: 12%

  let lastTrackIndex = -1;
  let started = false;
  let failedAttempts = 0;

  const audio = new Audio();
  audio.volume = VOLUME;
  audio.preload = 'auto';
  audio.muted = localStorage.getItem(STORAGE_KEY) === '1';

  function getRandomTrackIndex() {
    if (tracks.length === 1) return 0;

    let index;
    do {
      index = Math.floor(Math.random() * tracks.length);
    } while (index === lastTrackIndex);

    lastTrackIndex = index;
    return index;
  }

  function playRandomTrack() {
    if (failedAttempts >= tracks.length) {
      console.warn('Фоновая музыка не найдена. Проверьте файлы в assets/music/.');
      return;
    }

    const index = getRandomTrackIndex();
    audio.src = tracks[index];
    audio.currentTime = 0;

    audio.play().catch(function () {
      // Браузер может запретить автозапуск до первого действия пользователя.
    });
  }

  function startMusic() {
    if (started) return;

    started = true;
    failedAttempts = 0;
    playRandomTrack();

    document.removeEventListener('click', startMusic);
    document.removeEventListener('keydown', startMusic);
    document.removeEventListener('touchstart', startMusic);
  }

  function createMusicButton() {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'bg-music-toggle';

    Object.assign(button.style, {
      position: 'fixed',
      right: '16px',
      bottom: '16px',
      zIndex: '9999',
      border: '0',
      borderRadius: '999px',
      padding: '10px 14px',
      fontSize: '18px',
      cursor: 'pointer',
      background: 'rgba(255, 255, 255, 0.85)',
      boxShadow: '0 6px 18px rgba(0, 0, 0, 0.18)'
    });

    function updateButton() {
      button.textContent = audio.muted ? '🔇' : '🎵';
      button.title = audio.muted ? 'Включить музыку' : 'Выключить музыку';
    }

    button.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();

      audio.muted = !audio.muted;
      localStorage.setItem(STORAGE_KEY, audio.muted ? '1' : '0');
      updateButton();

      if (!started) startMusic();
    });

    updateButton();
    document.body.appendChild(button);
  }

  audio.addEventListener('ended', function () {
    failedAttempts = 0;
    playRandomTrack();
  });

  audio.addEventListener('error', function () {
    failedAttempts += 1;
    playRandomTrack();
  });

  document.addEventListener('click', startMusic);
  document.addEventListener('keydown', startMusic);
  document.addEventListener('touchstart', startMusic, { passive: true });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', createMusicButton);
  } else {
    createMusicButton();
  }
})();
