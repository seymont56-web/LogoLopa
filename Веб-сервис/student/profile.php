<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';

require_role('student');

$uid = (int) $_SESSION['uid'];
$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $old = $_POST['old_pass'] ?? '';
  $new = $_POST['new_pass'] ?? '';
  $new2 = $_POST['new_pass2'] ?? '';

  if ($new === '' || $new2 === '' || $old === '') {
    $err = 'Заполни все поля';
  } elseif ($new !== $new2) {
    $err = 'Новые пароли не совпадают';
  } elseif (mb_strlen($new) < 4) {
    $err = 'Пароль слишком короткий. Минимум 4 символа';
  } else {
    $stmt = $pdo->prepare("SELECT pass_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$uid]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($old, $u['pass_hash'])) {
      $err = 'Старый пароль неверный';
    } else {
      $hash = password_hash($new, PASSWORD_DEFAULT);

      $upd = $pdo->prepare("UPDATE users SET pass_hash = ?, must_change_pass = 0 WHERE id = ?");
      $upd->execute([$hash, $uid]);

      $_SESSION['must_change_pass'] = 0;
      $ok = 'Пароль обновлён';
    }
  }
}
?>
<!doctype html>
<html lang="ru">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Профиль</title>
  <link rel="stylesheet" href="../assets/style.css?v=4">

  <style>
    /* ===== Страница профиля ученика ===== */

    html,
    body {
      margin: 0;
      width: 100%;
      min-height: 100%;
    }

    * {
      box-sizing: border-box;
    }

    body {
      background: #29209D;
      overflow-x: hidden;
    }

    .profile-page {
      position: relative;
      min-height: 100vh;
      display: flex;
      align-items: stretch;
      isolation: isolate;

      background-color: #29209D;
      background-image: url("../assets/img/login_bg.svg");
      background-repeat: no-repeat;
      background-position: center center;
      background-size: cover;
    }

    /* ===== Шапка ===== */

    .profile-header {
      position: fixed;
      top: clamp(16px, 1.5vw, 24px);
      left: 50%;
      z-index: 9999;

      width: calc(100% - clamp(80px, 30vw, 480px));
      height: clamp(60px, 4.5vw, 72px);
      transform: translateX(-50%);

      display: grid;
      grid-template-columns: repeat(12, minmax(0, 1fr));
      align-items: center;
      gap: 20px;

      padding: 0 clamp(24px, 2.5vw, 40px);
      border-radius: 999px;

      background: rgba(27, 21, 102, .5);
      backdrop-filter: blur(15px);
      -webkit-backdrop-filter: blur(15px);

      box-shadow:
        0 10px 28px rgba(0, 0, 0, .45),
        0 0 28px rgba(255, 255, 255, .12),
        inset 0 1px 10px rgba(255, 255, 255, .1),
        inset 0 -1px 10px rgba(255, 255, 255, .1);
    }

    .profile-logo {
      grid-column: 1 / 2;
      justify-self: start;

      width: clamp(40px, 2.875vw, 46px);
      height: clamp(40px, 2.875vw, 46px);

      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
    }

    .profile-logo img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: contain;
    }

    .profile-nav {
      grid-column: 4 / 10;

      display: flex;
      align-items: center;
      justify-content: center;
      gap: clamp(26px, 2.2vw, 52px);
    }

    .profile-nav a {
      font-family: 'Montserrat', sans-serif;
      color: rgba(255, 255, 255, .62);
      text-decoration: none;
      font-size: clamp(13px, .95vw, 16px);
      white-space: nowrap;
      transition: color 0.3s ease;
    }

    .profile-nav a:hover,
    .profile-nav a.active {
      color: rgba(255, 255, 255, 1);
    }

    .profile-header-btn {
      grid-column: 10 / 13;
      justify-self: end;

      font-family: Borsok;
      font-size: clamp(17px, 1.25vw, 20px);

      min-width: clamp(120px, 9.375vw, 150px);
      height: clamp(36px, 2.5vw, 40px);
      border-radius: 999px;

      display: flex;
      align-items: center;
      justify-content: center;

      background: #ff9f0a;
      color: #fff;
      text-decoration: none;
      text-transform: uppercase;

      transition: box-shadow 0.2s ease;
    }

    .profile-header-btn:hover {
      box-shadow: 0 0 18px rgba(255, 159, 10, .35);
    }

    /* ===== Бургер-кнопка ===== */

    .mobile-menu-btn {
      display: none;
    }

    /* ===== Контейнер карточки ===== */

    .profile-wrap {
      width: calc(100% - clamp(80px, 30vw, 480px));
      max-width: none;

      margin: auto;
      padding: clamp(96px, 9vh, 130px) 0 clamp(32px, 5vh, 64px);

      position: relative;
      z-index: 1;
    }

    .profile-grid {
      width: 100%;

      display: grid;
      grid-template-columns: repeat(12, minmax(0, 1fr));
      gap: 20px;
      align-items: center;
    }

    .profile-card {
      grid-column: 2 / 12;
      width: 100%;

      min-height: clamp(430px, 30.5vw, 488px);

      padding: 8px;
      border-radius: 22px;
      background: #eeeeff;

      box-shadow: 0 18px 35px rgba(0, 0, 0, .22);
      transition: transform 0.3s ease;
    }

    .profile-card:hover {
      transform: scale(1.01);
    }

    .profile-form-area {
      min-height: calc(clamp(430px, 30.5vw, 488px) - 16px);
      height: 100%;

      display: grid;
      grid-template-columns: repeat(10, minmax(0, 1fr));
      gap: 20px;
      align-items: stretch;
    }

    .profile-illustration {
      grid-column: 1 / 5;

      min-height: calc(clamp(430px, 30.5vw, 488px) - 16px);
      height: 100%;

      border-radius: 16px;
      background: #30239d;
      overflow: hidden;
    }

    .profile-illustration img {
      width: 100%;
      height: 100%;

      display: block;
      object-fit: cover;
    }

    .profile-content {
      grid-column: 6 / 11;

      display: flex;
      flex-direction: column;
      justify-content: center;

      padding-right: clamp(20px, 2.625vw, 42px);
    }

    .profile-title {
      margin-bottom: clamp(32px, 3vw, 48px);
    }

    .profile-title h1 {
      font-family: Borsok;
      margin: 0 0 14px;

      font-size: clamp(30px, 2.375vw, 38px);
      line-height: 1;
      text-transform: uppercase;
      color: #22213a;
      font-weight: 900;
    }

    .profile-title p {
      font-family: 'Montserrat', sans-serif;
      margin: 0;

      font-size: clamp(16px, 1.25vw, 20px);
      line-height: 1.25;
      color: #7b7b92;
    }

    .profile-form {
      width: 100%;
    }

    .profile-form p {
      margin: clamp(12px, 1.125vw, 18px) 0;
    }

    .profile-form input {
      width: 100%;
      height: clamp(48px, 3.5vw, 56px);
      padding: 0 18px;

      border-radius: 999px;
      border: 2px solid #d7d7eb;
      background: #eeeeff;
      color: #22213a;

      font-family: 'Montserrat', sans-serif;
      font-size: clamp(14px, 1vw, 16px);

      box-shadow: inset 0 2px 5px rgba(0, 0, 0, .06);
      outline: none;

      transition: transform 0.2s ease, border-color 0.2s ease;
    }

    .profile-form input::placeholder {
      color: #ABACC2;
    }

    .profile-form input:hover,
    .profile-form input:focus {
      transform: scale(1.03);
      border-color: #c7c7e8;
    }

    .profile-btn {
      width: 100%;
      height: clamp(48px, 3.5vw, 56px);
      margin-top: 8px;

      border: 0;
      border-radius: 999px;

      background: #ff9f0a;
      color: #fff;

      font-family: Borsok;
      font-size: clamp(17px, 1.25vw, 20px);
      text-transform: uppercase;
      cursor: pointer;

      box-shadow: 0 8px 18px rgba(255, 159, 10, .38);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .profile-btn:hover {
      box-shadow: 0 0 18px rgba(255, 159, 10, .5);
      transform: scale(1.03);
    }

    /* ===== Сообщения справа снизу ===== */

    .profile-toast {
      position: fixed;
      right: 28px;
      bottom: 28px;
      z-index: 10000;

      max-width: 360px;
      padding: 14px 18px;

      border-radius: 16px;

      color: #fff;
      font-family: 'Montserrat', sans-serif;
      font-size: 15px;
      font-weight: 700;

      box-shadow: 0 14px 32px rgba(0, 0, 0, .25);

      animation: profile-toast-hide 4s ease forwards;
    }

    .profile-toast.ok {
      background: #25b86b;
    }

    .profile-toast.err {
      background: #e24b4b;
    }

    @keyframes profile-toast-hide {
      0% {
        opacity: 0;
        transform: translateY(12px);
      }

      12% {
        opacity: 1;
        transform: translateY(0);
      }

      82% {
        opacity: 1;
        transform: translateY(0);
      }

      100% {
        opacity: 0;
        transform: translateY(12px);
        pointer-events: none;
      }
    }

    /* ===== Ноутбуки ===== */

    @media (max-width: 1366px) {
      .profile-header {
        width: calc(100% - 96px);
        height: 64px;

        gap: 16px;
        padding: 0 28px;
      }

      .profile-logo {
        width: 42px;
        height: 42px;
      }

      .profile-nav {
        grid-column: 3 / 10;
        gap: 30px;
      }

      .profile-nav a {
        font-size: 14px;
      }

      .profile-header-btn {
        grid-column: 10 / 13;

        min-width: 126px;
        height: 38px;
        font-size: 17px;
      }

      .profile-wrap {
        width: calc(100% - 96px);
      }

      .profile-card {
        grid-column: 1 / 13;
      }
    }

    /* ===== Планшеты ===== */

    @media (max-width: 1024px) {
      .profile-header {
        top: 18px;

        width: calc(100% - 40px);
        height: 64px;
        min-height: 64px;

        gap: 16px;
        padding: 0 24px;
      }

      .profile-logo {
        width: 42px;
        height: 42px;
      }

      .profile-nav {
        grid-column: 3 / 10;
        gap: 24px;
      }

      .profile-nav a {
        font-size: 14px;
      }

      .profile-header-btn {
        grid-column: 10 / 13;

        min-width: 112px;
        height: 36px;
        font-size: 15px;
      }

      .profile-wrap {
        width: calc(100% - 40px);

        margin: 0 auto;
        padding-top: 112px;
        padding-bottom: 44px;
      }

      .profile-grid {
        align-items: start;
      }

      .profile-card {
        min-height: auto;
      }

      .profile-form-area {
        min-height: auto;
      }

      .profile-illustration {
        min-height: 430px;
      }
    }

    /* ===== 768–521px ===== */

    @media (max-width: 768px) and (min-width: 521px) {
      .profile-header {
        top: 16px;

        width: calc(100% - 32px);
        height: 62px;
        min-height: 62px;

        gap: 12px;
        padding: 0 18px;
      }

      .profile-logo {
        width: 40px;
        height: 40px;
      }

      .profile-nav {
        grid-column: 3 / 10;
        gap: 18px;
      }

      .profile-nav a {
        font-size: 13px;
      }

      .profile-header-btn {
        grid-column: 10 / 13;

        min-width: 96px;
        height: 34px;
        font-size: 14px;
      }

      .profile-wrap {
        width: calc(100% - 32px);

        padding-top: 104px;
        padding-bottom: 44px;
      }

      .profile-card {
        border-radius: 28px;
      }

      .profile-form-area {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0;
      }

      .profile-illustration {
        grid-column: 1 / 3;

        height: 240px;
        min-height: 240px;

        border-radius: 22px;
      }

      .profile-content {
        grid-column: 1 / 3;

        padding: 30px 24px 26px;
      }

      .profile-title {
        margin-bottom: 28px;
        text-align: center;
      }

      .profile-title h1 {
        font-size: 34px;
      }

      .profile-title p {
        font-size: 15px;
        line-height: 1.3;
      }

      .profile-title p br {
        display: none;
      }

      .profile-form input:hover,
      .profile-form input:focus,
      .profile-btn:hover,
      .profile-card:hover {
        transform: none;
      }

      .profile-toast {
        left: 18px;
        right: 18px;
        bottom: 18px;

        max-width: none;
        text-align: center;
      }
    }

    /* ===== Мобильная версия как login + бургер ===== */

    @media (max-width: 520px) {

      html,
      body {
        height: 100%;
      }

      body {
        padding-bottom: 0;
      }

      .profile-page {
        min-height: 100dvh;

        display: flex;
        align-items: center;

        padding: 22px 0 84px;
        overflow: hidden;
      }

      /* ===== Header mobile bottom ===== */

      .profile-header {
        position: fixed;
        top: auto;
        bottom: 16px;
        left: 50%;
        z-index: 9999;

        width: calc(100% - 28px);
        height: 58px;
        min-height: 58px;

        transform: translateX(-50%);

        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: center;
        gap: 12px;

        margin: 0;
        padding: 0 14px;
        border-radius: 999px;
      }

      .profile-logo {
        display: none;
      }

      .mobile-menu-btn {
        grid-column: 1 / 2;
        justify-self: start;

        width: 38px;
        height: 38px;
        padding: 0;

        border: 0;
        border-radius: 50%;

        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;

        background: rgba(255, 255, 255, .16);
        cursor: pointer;
      }

      .mobile-menu-btn span {
        width: 17px;
        height: 2px;

        display: block;

        border-radius: 999px;
        background: #fff;

        transition: transform .2s ease, opacity .2s ease;
      }

      .mobile-menu-btn.is-open span:nth-child(1) {
        transform: translateY(6px) rotate(45deg);
      }

      .mobile-menu-btn.is-open span:nth-child(2) {
        opacity: 0;
      }

      .mobile-menu-btn.is-open span:nth-child(3) {
        transform: translateY(-6px) rotate(-45deg);
      }

      .profile-header-btn {
        grid-column: 2 / 3;
        justify-self: end;

        min-width: 96px;
        height: 34px;

        font-size: 14px;
      }

      /* ===== Mobile dropdown menu ===== */

      .profile-nav {
        position: fixed;
        left: 14px;
        right: 14px;
        bottom: 86px;
        z-index: 9998;

        grid-column: auto;

        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;

        padding: 12px;
        border-radius: 26px;

        background: rgba(27, 21, 102, .72);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);

        box-shadow:
          0 14px 32px rgba(0, 0, 0, .35),
          inset 0 1px 10px rgba(255, 255, 255, .1);

        opacity: 0;
        transform: translateY(12px) scale(.98);
        pointer-events: none;

        transition: opacity .2s ease, transform .2s ease;
      }

      .profile-nav.is-open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
      }

      .profile-nav a {
        width: 100%;
        min-height: 44px;

        display: flex;
        align-items: center;
        justify-content: center;

        border-radius: 999px;

        color: rgba(255, 255, 255, .72);
        background: rgba(255, 255, 255, .08);

        font-family: 'Montserrat', sans-serif;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
      }

      .profile-nav a.active {
        color: #fff;
        background: rgba(255, 159, 10, .95);
      }

      /* ===== Profile card mobile ===== */

      .profile-wrap {
        width: calc(100% - 32px);
        max-width: 430px;

        margin: auto;
        padding: 0;

        position: relative;
        z-index: 2;
      }

      .profile-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
      }

      .profile-card {
        grid-column: 1 / 3;

        width: 100%;
        min-height: auto;
        max-height: none;

        padding: 10px;
        border-radius: 32px;

        background: #eeeeff;
        overflow: visible;

        box-shadow: 0 18px 35px rgba(0, 0, 0, .24);
      }

      .profile-card:hover {
        transform: none;
      }

      .profile-form-area {
        min-height: auto;
        height: auto;

        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0;
      }

      .profile-illustration {
        display: none;
      }

      .profile-content {
        grid-column: 1 / 3;

        padding: 34px 22px 28px;

        display: flex;
        flex-direction: column;
        justify-content: center;
      }

      .profile-title {
        margin-bottom: 30px;
        text-align: center;
      }

      .profile-title h1 {
        margin: 0 0 14px;

        font-size: 34px;
        line-height: .98;
      }

      .profile-title p {
        max-width: 330px;
        margin: 0 auto;

        font-size: 15px;
        line-height: 1.32;
      }

      .profile-title p br {
        display: none;
      }

      .profile-form {
        width: 100%;
      }

      .profile-form p {
        margin: 14px 0;
      }

      .profile-form input {
        height: 54px;
        padding: 0 20px;

        font-size: 16px;
      }

      .profile-form input:hover,
      .profile-form input:focus {
        transform: none;
      }

      .profile-btn {
        height: 54px;
        margin-top: 10px;

        font-size: 17px;
      }

      .profile-btn:hover {
        transform: none;
      }

      .profile-toast {
        left: 14px;
        right: 14px;
        bottom: 86px;

        max-width: none;

        padding: 13px 16px;
        border-radius: 18px;

        font-size: 13px;
        text-align: center;
      }
    }

    /* ===== Очень маленькие телефоны ===== */

    @media (max-width: 420px) {
      .profile-page {
        padding: 18px 0 80px;
      }

      .profile-header {
        bottom: 12px;

        width: calc(100% - 22px);
        height: 58px;
        min-height: 58px;

        padding: 0 12px;
      }

      .profile-header-btn {
        min-width: 96px;
        height: 34px;

        font-size: 14px;
      }

      .profile-nav {
        left: 11px;
        right: 11px;
        bottom: 82px;
      }

      .profile-wrap {
        width: calc(100% - 24px);
        max-width: 390px;
      }

      .profile-card {
        padding: 8px;
        border-radius: 30px;
      }

      .profile-content {
        padding: 32px 18px 26px;
      }

      .profile-title {
        margin-bottom: 28px;
      }

      .profile-title h1 {
        font-size: 31px;
      }

      .profile-title p {
        max-width: 300px;
        font-size: 14px;
        line-height: 1.28;
      }

      .profile-form p {
        margin: 13px 0;
      }

      .profile-form input {
        height: 52px;
        font-size: 15px;
      }

      .profile-btn {
        height: 52px;
        font-size: 16px;
      }

      .profile-toast {
        left: 12px;
        right: 12px;
        bottom: 82px;
      }
    }

    /* ===== Низкие экраны ноутбуков ===== */

    @media (max-height: 760px) and (min-width: 769px) {
      .profile-wrap {
        padding-top: 112px;
        padding-bottom: 32px;
      }

      .profile-card {
        min-height: 430px;
      }

      .profile-form-area,
      .profile-illustration {
        min-height: 414px;
      }

      .profile-title {
        margin-bottom: 28px;
      }

      .profile-form p {
        margin: 12px 0;
      }
    }

    /* ===== Низкие телефоны ===== */

    @media (max-width: 520px) and (max-height: 720px) {
      .profile-page {
        padding-top: 14px;
        padding-bottom: 76px;
      }

      .profile-content {
        padding-top: 26px;
        padding-bottom: 22px;
      }

      .profile-title {
        margin-bottom: 22px;
      }

      .profile-title h1 {
        font-size: 29px;
      }

      .profile-title p {
        font-size: 13px;
      }

      .profile-form p {
        margin: 11px 0;
      }

      .profile-form input {
        height: 48px;
      }

      .profile-btn {
        height: 48px;
      }
    }
  </style>
</head>
<script>
  function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const button = document.querySelector('.mobile-menu-btn');

    if (!menu || !button) return;

    menu.classList.toggle('is-open');
    button.classList.toggle('is-open');
  }

  document.addEventListener('click', function (event) {
    const menu = document.getElementById('mobileMenu');
    const button = document.querySelector('.mobile-menu-btn');

    if (!menu || !button) return;

    const clickedInsideMenu = menu.contains(event.target);
    const clickedButton = button.contains(event.target);

    if (!clickedInsideMenu && !clickedButton) {
      menu.classList.remove('is-open');
      button.classList.remove('is-open');
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      const menu = document.getElementById('mobileMenu');
      const button = document.querySelector('.mobile-menu-btn');

      if (!menu || !button) return;

      menu.classList.remove('is-open');
      button.classList.remove('is-open');
    }
  });
</script>

<body>
  <main class="profile-page">

    <header class="profile-header">
      <button class="mobile-menu-btn" type="button" aria-label="Открыть меню" onclick="toggleMobileMenu()">
        <span></span>
        <span></span>
        <span></span>
      </button>

      <a href="../student/home.php" class="profile-logo" aria-label="Логотип">
        <img src="../assets/img/logo.svg" alt="Логотип">
      </a>

      <nav class="profile-nav" id="mobileMenu">
        <a href="../index.php">Главная</a>
        <a href="home.php">Игры</a>
        <a href="profile.php" class="active">Профиль</a>
      </nav>

      <a href="../logout.php" class="profile-header-btn">Выйти</a>
    </header>

    <div class="wrap profile-wrap">
      <div class="grid profile-grid">

        <section class="card profile-card">
          <div class="profile-form-area">

            <div class="profile-illustration">
              <img src="../assets/img/profile_img.jpg" alt="Иллюстрация профиля">
            </div>

            <div class="profile-content">
              <div class="profile-title">
                <h1>Профиль</h1>
                <p>
                  Привет, <?= h($_SESSION['name'] ?? 'ученик') ?>!<br>
                  Здесь ты можешь поменять свой пароль.
                </p>
              </div>

              <form method="post" class="profile-form">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

                <p>
                  <input type="password" name="old_pass" placeholder="Старый пароль" required>
                </p>

                <p>
                  <input type="password" name="new_pass" placeholder="Новый пароль" required>
                </p>

                <p>
                  <input type="password" name="new_pass2" placeholder="Повтор нового пароля" required>
                </p>

                <button class="profile-btn" type="submit">Сохранить</button>
              </form>
            </div>

          </div>
        </section>

      </div>
    </div>

    <?php if ($ok): ?>
      <div class="profile-toast ok"><?= h($ok) ?></div>
    <?php endif; ?>

    <?php if ($err): ?>
      <div class="profile-toast err"><?= h($err) ?></div>
    <?php endif; ?>

  </main>
</body>

</html>