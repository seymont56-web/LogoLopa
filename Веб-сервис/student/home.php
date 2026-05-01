<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_role('student');

$uid = (int) $_SESSION['uid'];

$stmt = $pdo->prepare("
  SELECT display_name, login, must_change_pass
  FROM users
  WHERE id = ? AND role = 'student'
  LIMIT 1
");
$stmt->execute([$uid]);
$student = $stmt->fetch();

if (!$student) {
  header('Location: ../logout.php');
  exit;
}

$stmt = $pdo->prepare("
  SELECT g.title, g.path
  FROM student_game_access a
  JOIN games g ON g.id = a.game_id
  WHERE a.student_id = ? AND a.is_enabled = 1
  ORDER BY g.title
");
$stmt->execute([$uid]);
$games = $stmt->fetchAll();

$mustChange = (int) ($student['must_change_pass'] ?? 0);

function game_preview_path(string $gamePath): string
{
  $gamePath = trim(str_replace('\\', '/', $gamePath));

  if (preg_match('~/index\.html?$~i', $gamePath)) {
    $gameDir = rtrim(dirname($gamePath), '/');
  } else {
    $gameDir = rtrim($gamePath, '/');
  }

  return $gameDir . '/preview.jpg';
}
?>
<!doctype html>
<html lang="ru">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ученик — игры</title>
  <link rel="stylesheet" href="../assets/style.css?v=3">
  <style>
    /* =========================================================
   Страница ученика — доступные игры
   ========================================================= */

    html,
    body {
      margin: 0;
      width: 100%;
      min-height: 100%;
    }

    * {
      box-sizing: border-box;
    }

    body.student-home-page {
      min-height: 100vh;

      background-color: #29209D;
      background-image: url("../assets/img/login_bg.svg");
      background-repeat: no-repeat;
      background-position: center center;
      background-size: cover;

      overflow-x: hidden;
    }

    /* ===== Шапка ===== */

    .student-header {
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

    .student-logo {
      grid-column: 1 / 2;
      justify-self: start;

      width: clamp(40px, 2.875vw, 46px);
      height: clamp(40px, 2.875vw, 46px);

      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
    }

    .student-logo img {
      width: 100%;
      height: 100%;

      display: block;
      object-fit: contain;
    }

    .student-nav {
      grid-column: 4 / 10;

      display: flex;
      align-items: center;
      justify-content: center;
      gap: clamp(26px, 2.2vw, 52px);
    }

    .student-nav a {
      font-family: 'Montserrat', sans-serif;
      color: rgba(255, 255, 255, .62);
      text-decoration: none;
      font-size: clamp(13px, .95vw, 16px);
      white-space: nowrap;
      transition: color 0.3s ease;
    }

    .student-nav a:hover,
    .student-nav a.active {
      color: rgba(255, 255, 255, 1);
    }

    .student-header-btn {
      grid-column: 10 / 13;
      justify-self: end;

      min-width: clamp(120px, 9.375vw, 150px);
      height: clamp(36px, 2.5vw, 40px);

      display: flex;
      align-items: center;
      justify-content: center;

      border-radius: 999px;

      background: #ff9f0a;
      color: #fff;

      font-family: Borsok;
      font-size: clamp(17px, 1.25vw, 20px);
      text-decoration: none;
      text-transform: uppercase;

      transition: box-shadow 0.2s ease;
    }

    .student-header-btn:hover {
      box-shadow: 0 0 18px rgba(255, 159, 10, .35);
    }

    /* ===== Бургер-кнопка ===== */

    .mobile-menu-btn {
      display: none;
    }

    /* ===== Контейнер страницы ===== */

    .student-home {
      min-height: 100vh;

      display: flex;
      align-items: center;
      justify-content: center;

      padding: clamp(120px, 12vh, 150px) 0 clamp(40px, 6vh, 70px);
    }

    .student-home-wrap {
      width: calc(100% - clamp(80px, 30vw, 480px));
      max-width: none;

      margin: 0 auto;
      padding: 0;

      position: relative;
      z-index: 1;
    }

    .student-home-grid {
      width: 100%;

      display: grid;
      grid-template-columns: repeat(12, minmax(0, 1fr));
      gap: 20px;
      align-items: start;
    }

    /* ===== Блок игр ===== */

    .student-games-section {
      grid-column: 2 / 12;
      width: 100%;
    }

    .student-games-title {
      margin: 0 0 clamp(22px, 2vw, 32px);

      color: #fff;

      font-family: Borsok;
      font-size: clamp(34px, 3vw, 48px);
      line-height: 1;
      text-transform: uppercase;
    }

    .student-games-card {
      width: 100%;
      min-height: clamp(390px, 30vw, 478px);

      padding: clamp(26px, 2vw, 34px) clamp(28px, 3.25vw, 52px) clamp(38px, 3vw, 52px);
      border-radius: 22px;

      background: #eeeeff;
      box-shadow: 0 18px 35px rgba(0, 0, 0, .22);

      transition: transform 0.3s ease;
    }

    .student-games-card:hover {
      transform: scale(1.01);
    }

    /* ===== Информация об ученике ===== */

    .student-info-row {
      margin-bottom: clamp(24px, 2vw, 34px);

      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }

    .student-info {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;

      color: #22213a;

      font-family: 'Montserrat', sans-serif;
      font-size: clamp(15px, 1.125vw, 18px);
      font-weight: 700;
    }

    .student-info strong {
      padding: 3px 12px;
      border-radius: 999px;

      background: #e4e5f7;
      border: 1px solid rgba(171, 172, 194, .45);

      color: #8f90a8;

      font-size: clamp(12px, .875vw, 14px);
      font-weight: 500;
    }

    .student-info small {
      color: #8f90a8;

      font-size: clamp(12px, .875vw, 14px);
      font-weight: 500;
    }

    .student-pass-reminder {
      max-width: 520px;

      padding: 8px 14px;
      border-radius: 999px;

      background: rgba(41, 32, 157, .08);
      color: #6f708a;

      font-family: 'Montserrat', sans-serif;
      font-size: clamp(12px, .875vw, 14px);
      font-weight: 600;
      line-height: 1.25;
    }

    /* ===== Сетка карточек игр ===== */

    .student-games-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: clamp(12px, 1vw, 16px);
    }

    .student-game {
      position: relative;

      min-height: 200px;
      overflow: hidden;

      display: flex;
      align-items: center;
      justify-content: center;

      border-radius: 14px;

      background-color: #49645f;
      background-image:
        linear-gradient(rgba(18, 54, 47, .58), rgba(18, 54, 47, .58)),
        var(--game-preview);
      background-repeat: no-repeat;
      background-position: center;
      background-size: cover;

      box-shadow: 0 8px 16px rgba(25, 21, 85, .14);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .student-game:hover {
      transform: scale(1.02);
      box-shadow: 0 12px 22px rgba(25, 21, 85, .2);
    }

    .student-game-content {
      position: relative;
      z-index: 2;

      width: 100%;
      padding: 18px;

      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: clamp(14px, 1.25vw, 20px);

      text-align: center;
    }

    .student-game h2 {
      max-width: 220px;
      margin: 0;

      color: #fff;

      font-family: Borsok;
      font-size: clamp(15px, 1.125vw, 18px);
      line-height: 1.05;
      text-transform: uppercase;
      text-shadow: 0 3px 0 rgba(0, 0, 0, .16);
    }

    .student-game-btn {
      min-width: clamp(128px, 9.375vw, 150px);
      height: clamp(34px, 2.5vw, 40px);

      border: 0;
      border-radius: 999px;

      display: flex;
      align-items: center;
      justify-content: center;

      background: #ff9f0a;
      color: #fff;

      font-family: Borsok;
      font-size: clamp(13px, .9375vw, 15px);
      text-transform: uppercase;

      cursor: pointer;

      box-shadow: 0 8px 18px rgba(255, 159, 10, .38);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .student-game-btn:hover {
      box-shadow: 0 0 18px rgba(255, 159, 10, .5);
      transform: scale(1.04);
    }

    /* ===== Пустое состояние ===== */

    .student-empty-games {
      min-height: 250px;

      display: flex;
      align-items: center;
      justify-content: center;

      text-align: center;

      color: #8f90a8;

      font-family: 'Montserrat', sans-serif;
      font-size: clamp(15px, 1.125vw, 18px);
      font-weight: 600;
    }

    /* ===== Модальное окно игры ===== */

    .modal {
      position: fixed;
      inset: 0;
      z-index: 10000;

      padding: 24px;

      display: flex;
      align-items: center;
      justify-content: center;

      background: rgba(15, 10, 65, .72);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
    }

    .modal.hidden {
      display: none;
    }

    .modal__window {
      width: min(100%, 1180px);
      height: min(100%, 760px);
      overflow: hidden;

      display: flex;
      flex-direction: column;

      border-radius: 22px;

      box-shadow: 0 18px 45px rgba(0, 0, 0, .42);
    }

    .modal__top {
      min-height: 70px;
      padding: 16px 22px;

      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;

      background: #29209D;
    }

    #gameTitle {
      color: #fff;

      font-family: Borsok;
      font-size: 20px;
      text-transform: uppercase;
    }

    #closeGame {
      min-width: 120px;
      height: 40px;

      border: 0;
      border-radius: 999px;

      background: #ff9f0a;
      color: #fff;

      font-family: Borsok;
      font-size: 14px;
      cursor: pointer;

      text-transform: uppercase;
    }

    .modal__body {
      flex: 1;
      min-height: 0;
    }

    .modal__body iframe {
      width: 100%;
      height: 100%;

      display: block;
      border: 0;
    }

    /* ===== Ноутбуки ===== */

    @media (max-width: 1366px) {
      .student-header {
        width: calc(100% - 96px);
        height: 64px;

        gap: 16px;
        padding: 0 28px;
      }

      .student-logo {
        width: 42px;
        height: 42px;
      }

      .student-nav {
        grid-column: 3 / 10;
        gap: 30px;
      }

      .student-nav a {
        font-size: 14px;
      }

      .student-header-btn {
        grid-column: 10 / 13;

        min-width: 126px;
        height: 38px;

        font-size: 17px;
      }

      .student-home-wrap {
        width: calc(100% - 96px);
      }

      .student-games-section {
        grid-column: 1 / 13;
      }

      .student-games-card {
        min-height: 390px;
      }
    }

    /* ===== Планшеты ===== */

    @media (max-width: 1024px) {
      .student-header {
        top: 18px;

        width: calc(100% - 40px);
        height: 64px;
        min-height: 64px;

        gap: 16px;
        padding: 0 24px;
      }

      .student-logo {
        width: 42px;
        height: 42px;
      }

      .student-nav {
        grid-column: 3 / 10;
        gap: 24px;
      }

      .student-nav a {
        font-size: 14px;
      }

      .student-header-btn {
        grid-column: 10 / 13;

        min-width: 112px;
        height: 36px;

        font-size: 15px;
      }

      .student-home {
        align-items: flex-start;
        padding-top: 112px;
        padding-bottom: 44px;
      }

      .student-home-wrap {
        width: calc(100% - 40px);
      }

      .student-games-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .student-game {
        min-height: 190px;
      }
    }

    /* ===== 768–521px ===== */

    @media (max-width: 768px) and (min-width: 521px) {
      .student-header {
        top: 16px;

        width: calc(100% - 32px);
        height: 62px;
        min-height: 62px;

        gap: 12px;
        padding: 0 18px;
      }

      .student-logo {
        width: 40px;
        height: 40px;
      }

      .student-nav {
        grid-column: 3 / 10;
        gap: 18px;
      }

      .student-nav a {
        font-size: 13px;
      }

      .student-header-btn {
        grid-column: 10 / 13;

        min-width: 96px;
        height: 34px;

        font-size: 14px;
      }

      .student-home {
        padding-top: 104px;
        padding-bottom: 44px;
      }

      .student-home-wrap {
        width: calc(100% - 32px);
      }

      .student-games-title {
        font-size: 36px;
        text-align: center;
      }

      .student-games-card {
        padding: 28px 22px 34px;
        border-radius: 28px;
      }

      .student-info-row {
        justify-content: center;
        text-align: center;
      }

      .student-info {
        justify-content: center;
      }

      .student-pass-reminder {
        max-width: 100%;
        border-radius: 22px;
      }

      .student-games-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
      }

      .student-game {
        min-height: 180px;
        border-radius: 18px;
      }

      .modal {
        padding: 16px;
      }

      .modal__window {
        height: min(100%, 720px);
        border-radius: 20px;
      }
    }

    /* ===== Мобильная версия ===== */

    @media (max-width: 520px) {
      body.student-home-page {
        padding-bottom: 104px;
      }

      .student-header {
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

      .student-logo {
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

      .student-header-btn {
        grid-column: 2 / 3;
        justify-self: end;

        min-width: 96px;
        height: 34px;

        font-size: 14px;
      }

      /* ===== Mobile dropdown menu ===== */

      .student-nav {
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

      .student-nav.is-open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
      }

      .student-nav a {
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

      .student-nav a.active {
        color: #fff;
        background: rgba(255, 159, 10, .95);
      }

      .student-home {
        min-height: auto;

        display: block;

        padding: 26px 0 110px;
      }

      .student-home-wrap {
        width: calc(100% - 28px);
      }

      .student-home-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
      }

      .student-games-section {
        grid-column: 1 / 3;
      }

      .student-games-title {
        margin-bottom: 22px;

        font-size: 32px;
        line-height: .98;
        text-align: center;
      }

      .student-games-card {
        min-height: auto;

        padding: 26px 16px 28px;
        border-radius: 30px;

        transform: none;
      }

      .student-games-card:hover {
        transform: none;
      }

      .student-info-row {
        margin-bottom: 26px;

        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 12px;

        text-align: center;
      }

      .student-info {
        justify-content: center;
        gap: 8px;

        font-size: 15px;
        line-height: 1.3;
      }

      .student-info strong {
        padding: 5px 12px;
        font-size: 13px;
      }

      .student-info small {
        width: 100%;
        font-size: 12.5px;
      }

      .student-pass-reminder {
        width: 100%;
        max-width: 100%;

        padding: 12px 14px;
        border-radius: 20px;

        font-size: 13px;
        line-height: 1.3;
      }

      .student-games-grid {
        grid-template-columns: 1fr;
        gap: 16px;
      }

      .student-game {
        min-height: 188px;
        border-radius: 22px;
      }

      .student-game:hover {
        transform: none;
      }

      .student-game-content {
        padding: 20px 16px;
        gap: 18px;
      }

      .student-game h2 {
        max-width: 260px;
        font-size: 20px;
        line-height: 1.02;
      }

      .student-game-btn {
        width: 100%;
        max-width: 220px;
        height: 46px;

        font-size: 15px;
      }

      .student-game-btn:hover {
        transform: none;
      }

      .student-empty-games {
        min-height: 220px;
        padding: 0 10px;

        font-size: 15px;
        line-height: 1.3;
      }

      /* ===== Модалка игры mobile ===== */

      .modal {
        padding: 10px;
        align-items: stretch;
      }

      .modal__window {
        width: 100%;
        height: calc(100dvh - 20px);

        border-radius: 24px;
      }

      .modal__top {
        min-height: 64px;
        padding: 12px 14px;

        gap: 10px;
      }

      #gameTitle {
        font-size: 15px;
        line-height: 1;
      }

      #closeGame {
        min-width: 92px;
        height: 36px;

        font-size: 12px;
      }
    }

    /* ===== Очень маленькие телефоны ===== */

    @media (max-width: 420px) {
      body.student-home-page {
        padding-bottom: 100px;
      }

      .student-header {
        bottom: 12px;

        width: calc(100% - 22px);
        height: 58px;
        min-height: 58px;

        padding: 0 12px;
      }

      .student-nav {
        left: 11px;
        right: 11px;
        bottom: 82px;
      }

      .student-header-btn {
        min-width: 96px;
        height: 34px;

        font-size: 14px;
      }

      .student-home {
        padding-top: 22px;
        padding-bottom: 104px;
      }

      .student-home-wrap {
        width: calc(100% - 24px);
      }

      .student-games-title {
        margin-bottom: 20px;
        font-size: 29px;
      }

      .student-games-card {
        padding: 24px 14px 26px;
        border-radius: 28px;
      }

      .student-info {
        font-size: 14px;
      }

      .student-pass-reminder {
        font-size: 12.5px;
      }

      .student-game {
        min-height: 176px;
        border-radius: 20px;
      }

      .student-game h2 {
        font-size: 18px;
      }

      .student-game-btn {
        height: 44px;
        font-size: 14px;
      }

      .modal {
        padding: 8px;
      }

      .modal__window {
        height: calc(100dvh - 16px);
        border-radius: 22px;
      }

      .modal__top {
        min-height: 60px;
        padding: 10px 12px;
      }

      #gameTitle {
        font-size: 14px;
      }

      #closeGame {
        min-width: 86px;
        height: 34px;
        font-size: 11px;
      }
    }

    /* ===== Низкие экраны ноутбуков ===== */

    @media (max-height: 760px) and (min-width: 769px) {
      .student-home {
        align-items: flex-start;
        padding-top: 112px;
        padding-bottom: 32px;
      }

      .student-games-card {
        min-height: 390px;
      }

      .student-games-title {
        margin-bottom: 22px;
      }

      .student-game {
        min-height: 160px;
      }

      .modal__window {
        height: min(100%, 680px);
      }
    }
  </style>
</head>

<body class="student-home-page">

  <header class="student-header">
    <button class="mobile-menu-btn" type="button" aria-label="Открыть меню" onclick="toggleMobileMenu()">
      <span></span>
      <span></span>
      <span></span>
    </button>

    <a class="student-logo" href="home.php" aria-label="Главная">
      <img src="../assets/img/logo.svg" alt="">
    </a>

    <nav class="student-nav" id="mobileMenu" aria-label="Навигация">
      <a href="../index.php">Главная</a>
      <a class="active" href="home.php">Игры</a>
      <a href="profile.php">Профиль</a>
    </nav>

    <a class="student-header-btn" href="../logout.php">Выйти</a>
  </header>

  <main class="student-home">
    <div class="student-home-wrap">
      <div class="student-home-grid">

        <section class="student-games-section">
          <h1 class="student-games-title">Доступные игры</h1>

          <div class="student-games-card">
            <div class="student-info-row">
              <div class="student-info">
                <span>Ученик:</span>

                <strong>
                  <?= h($student['display_name'] ?? 'Ученик') ?>
                </strong>

                <small>
                  (логин: <?= h($student['login'] ?? '') ?>)
                </small>
              </div>

              <?php if ($mustChange === 1): ?>
                <div class="student-pass-reminder">
                  У вас установлен временный пароль. Сменить его можно в профиле.
                </div>
              <?php endif; ?>
            </div>

            <?php if (!$games): ?>
              <div class="student-empty-games">
                Пока нет доступных игр. Попроси учителя открыть доступ.
              </div>
            <?php else: ?>
              <div class="student-games-grid">
                <?php foreach ($games as $g): ?>
                  <?php $preview = game_preview_path($g['path']); ?>

                  <article class="student-game" style="--game-preview: url('<?= h($preview) ?>');">

                    <div class="student-game-content">
                      <h2><?= h($g['title']) ?></h2>

                      <button type="button" class="student-game-btn" data-game-path="<?= h($g['path']) ?>"
                        data-game-title="<?= h($g['title']) ?>">
                        Играть
                      </button>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

      </div>
    </div>
  </main>

  <div id="gameModal" class="modal hidden" aria-hidden="true">
    <div class="modal__window">
      <div class="modal__top">
        <div id="gameTitle">Игра</div>
        <button id="closeGame" class="secondary" type="button">Закрыть ✕</button>
      </div>

      <div class="modal__body">
        <iframe id="gameFrame" src="" loading="lazy" sandbox="allow-scripts allow-same-origin">
        </iframe>
      </div>
    </div>
  </div>

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

  <script src="../assets/app.js?v=3"></script>
</body>

</html>