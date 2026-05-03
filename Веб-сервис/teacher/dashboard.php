<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';

require_role('teacher');

$ok = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $name = trim($_POST['name'] ?? '');
  $login = trim($_POST['login'] ?? '');
  $tempPass = (string) ($_POST['temp_pass'] ?? '');

  if ($name === '' || $login === '' || $tempPass === '') {
    $err = 'Заполни все поля';
  } else {
    try {
      $pdo->beginTransaction();

      $hash = password_hash($tempPass, PASSWORD_DEFAULT);

      $stmt = $pdo->prepare("
        INSERT INTO users(role, login, pass_hash, display_name, must_change_pass)
        VALUES('student', ?, ?, ?, 1)
      ");
      $stmt->execute([$login, $hash, $name]);

      $studentId = (int) $pdo->lastInsertId();

      $games = $pdo->query("SELECT id FROM games ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

      $ins = $pdo->prepare("
        INSERT INTO student_game_access(student_id, game_id, is_enabled)
        VALUES(?, ?, 0)
      ");

      foreach ($games as $gid) {
        $ins->execute([$studentId, (int) $gid]);
      }

      $pdo->commit();

      $ok = "Ученик создан. Логин: $login, временный пароль: $tempPass";
    } catch (PDOException $e) {
      $pdo->rollBack();

      $sqlState = $e->errorInfo[0] ?? '';
      $driverCode = $e->errorInfo[1] ?? '';
      $driverMsg = $e->errorInfo[2] ?? '';

      $err = "DB error: SQLSTATE=$sqlState CODE=$driverCode MSG=$driverMsg";
    }
  }
}

$students = $pdo->query("
  SELECT id, login, display_name, created_at
  FROM users
  WHERE role = 'student'
  ORDER BY id DESC
")->fetchAll();
?>
<!doctype html>
<html lang="ru">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Панель учителя</title>
  <link rel="stylesheet" href="../assets/style.css">
  <link rel="icon" href="../favicon.svg" type="image/svg+xml">

  <style>
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

    .teacher-page {
      position: relative;
      min-height: 100vh;
      isolation: isolate;

      background-color: #29209D;
      background-image: url("../assets/img/login_bg.svg");
      background-repeat: no-repeat;
      background-position: center center;
      background-size: cover;
    }

    .teacher-header {
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

    .teacher-logo {
      grid-column: 1 / 2;
      justify-self: start;

      width: clamp(40px, 2.875vw, 46px);
      height: clamp(40px, 2.875vw, 46px);

      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
    }

    .teacher-logo img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: contain;
    }

    .teacher-nav {
      grid-column: 4 / 10;

      display: flex;
      align-items: center;
      justify-content: center;
      gap: clamp(26px, 2.2vw, 52px);
    }

    .teacher-nav a {
      font-family: 'Montserrat', sans-serif;
      color: rgba(255, 255, 255, .62);
      text-decoration: none;
      font-size: clamp(13px, .95vw, 16px);
      white-space: nowrap;
      transition: color 0.3s ease;
    }

    .teacher-nav a:hover,
    .teacher-nav a.active {
      color: rgba(255, 255, 255, 1);
    }

    .teacher-header-btn {
      grid-column: 10 / 13;
      justify-self: end;

      min-width: clamp(120px, 9.375vw, 150px);
      height: clamp(36px, 2.5vw, 40px);
      border-radius: 999px;

      display: flex;
      align-items: center;
      justify-content: center;

      background: #ff9f0a;
      color: #fff;

      font-family: Borsok;
      font-size: clamp(17px, 1.25vw, 20px);
      text-decoration: none;
      text-transform: uppercase;

      transition: box-shadow 0.2s ease;
    }

    .teacher-header-btn:hover {
      box-shadow: 0 0 18px rgba(255, 159, 10, .35);
    }


    .mobile-menu-btn {
      display: none;
    }


    .teacher-wrap {
      width: calc(100% - clamp(80px, 30vw, 480px));
      max-width: none;

      margin: 0 auto;
      padding: clamp(130px, 10vw, 150px) 0 clamp(40px, 5vh, 70px);

      position: relative;
      z-index: 1;
    }

    .teacher-title {
      margin: 0 0 clamp(28px, 3vw, 42px);

      color: #fff;

      font-family: Borsok;
      font-size: clamp(34px, 3vw, 48px);
      line-height: 1;
      text-transform: uppercase;
      font-weight: 900;
    }


    .teacher-grid {
      width: 100%;

      display: grid;
      grid-template-columns: repeat(12, minmax(0, 1fr));
      gap: 20px;
      align-items: start;
    }

    .teacher-card {
      background: #eeeeff;
      border-radius: 22px;

      box-shadow: 0 18px 35px rgba(0, 0, 0, .22);
      transition: transform 0.3s ease;
    }

    .teacher-card:hover {
      transform: scale(1.01);
    }

    .teacher-card h2 {
      margin: 0;

      color: #22213a;

      font-family: Borsok;
      font-size: clamp(22px, 1.75vw, 28px);
      line-height: 1;
      text-transform: uppercase;
      font-weight: 900;
    }

    .teacher-create-card {
      grid-column: 1 / 5;

      min-height: clamp(390px, 26.25vw, 420px);
      padding: clamp(24px, 2vw, 32px);
    }

    .teacher-students-card {
      grid-column: 5 / 13;

      min-height: clamp(500px, 37.5vw, 600px);
      padding: clamp(24px, 2vw, 32px);
    }

    .teacher-card-text {
      max-width: 280px;
      margin: 14px 0 clamp(24px, 2vw, 32px);

      color: #7b7b92;

      font-family: 'Montserrat', sans-serif;
      font-size: clamp(14px, 1vw, 16px);
      line-height: 1.25;
    }

    .teacher-form {
      width: 100%;
    }

    .teacher-form p {
      margin: clamp(10px, 1vw, 14px) 0;
    }

    .teacher-form input {
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

    .teacher-form input::placeholder {
      color: #ABACC2;
    }

    .teacher-form input:hover,
    .teacher-form input:focus {
      transform: scale(1.03);
      border-color: #c7c7e8;
    }

    .teacher-btn {
      width: 100%;
      height: clamp(48px, 3.5vw, 56px);
      margin-top: clamp(18px, 1.75vw, 28px);

      border: none;
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

    .teacher-btn:hover {
      transform: scale(1.03);
      box-shadow: 0 10px 24px rgba(255, 159, 10, .45);
    }


    .teacher-message {
      margin-top: 16px;
      padding: 12px 14px;
      border-radius: 14px;

      font-family: 'Montserrat', sans-serif;
      font-size: 14px;
      line-height: 1.35;
    }

    .teacher-message-ok {
      background: rgba(72, 199, 116, .14);
      color: #23824a;
    }

    .teacher-message-err {
      background: rgba(255, 80, 80, .14);
      color: #b52b2b;
    }


    .teacher-table-wrap {
      width: 100%;
      margin-top: clamp(24px, 2vw, 34px);
      overflow-x: auto;
    }

    .teacher-table {
      width: 100%;
      border-collapse: collapse;

      color: #22213a;

      font-family: 'Montserrat', sans-serif;
      font-size: clamp(14px, 1vw, 16px);
    }

    .teacher-table th,
    .teacher-table td {
      padding: 14px 10px;
      text-align: left;
      vertical-align: middle;
      border-bottom: 1px solid rgba(34, 33, 58, .08);
    }

    .teacher-table th {
      color: #22213a;
      font-weight: 800;
    }

    .teacher-table td {
      color: #4c4b63;
    }

    .teacher-table a {
      color: #29209D;
      font-weight: 700;
      text-decoration: none;
      white-space: nowrap;
    }

    .teacher-table a:hover {
      text-decoration: underline;
    }

    .teacher-empty {
      color: #7b7b92;
    }


    @media (max-width: 1366px) {
      .teacher-header {
        width: calc(100% - 96px);
        height: 64px;

        gap: 16px;
        padding: 0 28px;
      }

      .teacher-logo {
        width: 42px;
        height: 42px;
      }

      .teacher-nav {
        grid-column: 3 / 10;
        gap: 30px;
      }

      .teacher-nav a {
        font-size: 14px;
      }

      .teacher-header-btn {
        grid-column: 10 / 13;

        min-width: 126px;
        height: 38px;
        font-size: 17px;
      }

      .teacher-wrap {
        width: calc(100% - 96px);
      }

      .teacher-create-card {
        grid-column: 1 / 5;
      }

      .teacher-students-card {
        grid-column: 5 / 13;
      }
    }


    @media (max-width: 1024px) {
      .teacher-header {
        top: 18px;

        width: calc(100% - 40px);
        height: 64px;
        min-height: 64px;

        gap: 16px;
        padding: 0 24px;
      }

      .teacher-logo {
        width: 42px;
        height: 42px;
      }

      .teacher-nav {
        grid-column: 3 / 10;
        gap: 24px;
      }

      .teacher-nav a {
        font-size: 14px;
      }

      .teacher-header-btn {
        grid-column: 10 / 13;

        min-width: 112px;
        height: 36px;
        font-size: 15px;
      }

      .teacher-wrap {
        width: calc(100% - 40px);
        padding-top: 112px;
      }

      .teacher-create-card,
      .teacher-students-card {
        grid-column: 1 / 13;
      }

      .teacher-students-card {
        min-height: 420px;
      }
    }


    @media (max-width: 768px) and (min-width: 521px) {
      .teacher-header {
        top: 16px;

        width: calc(100% - 32px);
        height: 62px;
        min-height: 62px;

        gap: 12px;
        padding: 0 18px;
      }

      .teacher-logo {
        width: 40px;
        height: 40px;
      }

      .teacher-nav {
        grid-column: 3 / 10;
        gap: 18px;
      }

      .teacher-nav a {
        font-size: 13px;
      }

      .teacher-header-btn {
        grid-column: 10 / 13;

        min-width: 96px;
        height: 34px;
        font-size: 14px;
      }

      .teacher-wrap {
        width: calc(100% - 32px);

        padding-top: 104px;
        padding-bottom: 44px;
      }

      .teacher-title {
        font-size: 34px;
        text-align: center;
      }

      .teacher-grid {
        gap: 18px;
      }

      .teacher-create-card,
      .teacher-students-card {
        padding: 24px;
        border-radius: 28px;
      }

      .teacher-card:hover,
      .teacher-form input:hover,
      .teacher-form input:focus,
      .teacher-btn:hover {
        transform: none;
      }

      .teacher-card h2 {
        text-align: center;
      }

      .teacher-card-text {
        max-width: 100%;
        margin-left: auto;
        margin-right: auto;
        text-align: center;
      }
    }


    @media (max-width: 520px) {

      html,
      body {
        min-height: 100%;
        overflow-x: hidden;
      }

      body {
        background: #29209D;
        padding-bottom: 104px;
      }

      .teacher-page {
        min-height: 100dvh;
        padding-bottom: 22px;

        background-position: center center;
        background-size: cover;
      }


      .teacher-header {
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

      .teacher-logo {
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

      .teacher-header-btn {
        grid-column: 2 / 3;
        justify-self: end;

        min-width: 96px;
        height: 34px;

        font-size: 14px;
      }

      .teacher-nav {
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

      .teacher-nav.is-open {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
      }

      .teacher-nav a {
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

      .teacher-nav a.active {
        color: #fff;
        background: rgba(255, 159, 10, .95);
      }


      .teacher-wrap {
        width: calc(100% - 36px);

        margin: 0 auto;
        padding: 34px 0 116px;

        position: relative;
        z-index: 1;
      }

      .teacher-title {
        margin: 0 0 26px;

        font-size: 32px;
        line-height: .98;
        text-align: center;
      }

      .teacher-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 24px;
      }

      .teacher-card {
        border-radius: 32px;
      }

      .teacher-card:hover {
        transform: none;
      }

      .teacher-card h2 {
        font-size: 25px;
        line-height: .98;
        text-align: center;
      }

      .teacher-create-card,
      .teacher-students-card {
        grid-column: 1 / 3;

        min-height: auto;
        padding: 30px 18px;

        border-radius: 32px;
      }

      .teacher-card-text {
        max-width: 290px;

        margin: 14px auto 26px;

        font-size: 14px;
        line-height: 1.35;
        text-align: center;
      }


      .teacher-form p {
        margin: 14px 0;
      }

      .teacher-form input {
        height: 50px;
        padding: 0 18px;

        font-size: 15px;
      }

      .teacher-form input:hover,
      .teacher-form input:focus {
        transform: none;
      }

      .teacher-btn {
        height: 50px;
        margin-top: 22px;

        font-size: 16px;
      }

      .teacher-btn:hover {
        transform: none;
      }

      .teacher-message {
        margin-top: 16px;
        padding: 12px 14px;

        border-radius: 18px;

        font-size: 13px;
        line-height: 1.3;
        text-align: center;
      }

      .teacher-table-wrap {
        margin-top: 28px;
        overflow: visible;
      }

      .teacher-table {
        width: 100%;
        min-width: 0;

        border-collapse: separate;
        border-spacing: 0 16px;
      }

      .teacher-table thead {
        display: none;
      }

      .teacher-table,
      .teacher-table tbody,
      .teacher-table tr,
      .teacher-table td {
        display: block;
      }

      .teacher-table tr {
        padding: 18px;

        border-radius: 24px;
        background: rgba(255, 255, 255, .58);

        box-shadow: inset 0 0 0 1px rgba(34, 33, 58, .06);
      }

      .teacher-table td {
        padding: 0;
        border-bottom: none;

        font-size: 14px;
        line-height: 1.25;
        color: #4c4b63;
      }

      .teacher-table td:nth-child(1) {
        margin-bottom: 10px;

        font-size: 12px;
        font-weight: 800;
        color: #ABACC2;
      }

      .teacher-table td:nth-child(1)::before {
        content: "ID: ";
      }

      .teacher-table td:nth-child(2) {
        margin-bottom: 6px;

        font-size: 18px;
        font-weight: 800;
        color: #22213a;
      }

      .teacher-table td:nth-child(3) {
        margin-bottom: 18px;

        font-size: 14px;
        color: #7b7b92;
      }

      .teacher-table td:nth-child(3)::before {
        content: "Логин: ";
        font-weight: 700;
        color: #4c4b63;
      }

      .teacher-table td:nth-child(4) {
        margin-top: 12px;
      }

      .teacher-table a {
        width: 100%;
        min-height: 46px;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        border-radius: 999px;
        background: #29209D;
        color: #fff;

        font-family: Borsok;
        font-size: 14px;
        font-weight: 400;
        text-transform: uppercase;
        text-decoration: none;
      }

      .teacher-table a:hover {
        text-decoration: none;
      }

      .teacher-empty {
        text-align: center;
      }
    }


    @media (max-width: 420px) {
      body {
        padding-bottom: 104px;
      }

      .teacher-page {
        padding-bottom: 18px;
      }

      .teacher-header {
        bottom: 12px;

        width: calc(100% - 22px);
        height: 58px;
        min-height: 58px;

        padding: 0 12px;
      }

      .teacher-nav {
        left: 11px;
        right: 11px;
        bottom: 82px;
      }

      .teacher-header-btn {
        min-width: 96px;
        height: 34px;

        font-size: 14px;
      }

      .teacher-wrap {
        width: calc(100% - 28px);

        padding-top: 30px;
        padding-bottom: 112px;
      }

      .teacher-title {
        margin-bottom: 24px;

        font-size: 29px;
      }

      .teacher-grid {
        gap: 22px;
      }

      .teacher-create-card,
      .teacher-students-card {
        padding: 28px 14px;
        border-radius: 30px;
      }

      .teacher-card h2 {
        font-size: 23px;
      }

      .teacher-card-text {
        margin-bottom: 24px;

        font-size: 13px;
      }

      .teacher-form p {
        margin: 13px 0;
      }

      .teacher-form input {
        height: 48px;
        font-size: 14px;
      }

      .teacher-btn {
        height: 48px;
        margin-top: 20px;

        font-size: 15px;
      }

      .teacher-table-wrap {
        margin-top: 26px;
      }

      .teacher-table {
        border-spacing: 0 15px;
      }

      .teacher-table tr {
        padding: 16px;
        border-radius: 22px;
      }

      .teacher-table td:nth-child(2) {
        font-size: 17px;
      }

      .teacher-table a {
        min-height: 44px;
        font-size: 13px;
      }
    }


    @media (max-height: 760px) and (min-width: 769px) {
      .teacher-wrap {
        padding-top: 112px;
        padding-bottom: 32px;
      }

      .teacher-title {
        margin-bottom: 28px;
      }

      .teacher-create-card {
        min-height: 390px;
      }

      .teacher-students-card {
        min-height: 500px;
      }
    }
  </style>
</head>

<body>
  <main class="teacher-page">

    <header class="teacher-header">
      <button class="mobile-menu-btn" type="button" aria-label="Открыть меню" onclick="toggleMobileMenu()">
        <span></span>
        <span></span>
        <span></span>
      </button>

      <a class="teacher-logo" href="../index.php" aria-label="На главную">
        <img src="../assets/img/logo.svg" alt="Логотип">
      </a>

      <nav class="teacher-nav" id="mobileMenu">
        <a href="../index.php">Главная</a>
        <a href="dashboard.php" class="active">Панель учителя</a>
      </nav>

      <a class="teacher-header-btn" href="../logout.php">Выйти</a>
    </header>

    <div class="teacher-wrap">
      <h1 class="teacher-title">Панель учителя</h1>

      <div class="teacher-grid">

        <section class="teacher-card teacher-create-card">
          <h2>Создать ученика</h2>

          <p class="teacher-card-text">
            Ученик может поменять пароль в профиле.
          </p>

          <form class="teacher-form" method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

            <p>
              <input name="name" placeholder="Имя" required>
            </p>

            <p>
              <input name="login" placeholder="Логин" required>
            </p>

            <p>
              <input name="temp_pass" type="password" placeholder="Временный пароль" required>
            </p>

            <button class="teacher-btn" type="submit">Создать</button>
          </form>

          <?php if ($ok): ?>
            <div class="teacher-message teacher-message-ok"><?= h($ok) ?></div>
          <?php endif; ?>

          <?php if ($err): ?>
            <div class="teacher-message teacher-message-err"><?= h($err) ?></div>
          <?php endif; ?>
        </section>

        <section class="teacher-card teacher-students-card">
          <h2>Список учеников</h2>

          <div class="teacher-table-wrap">
            <table class="teacher-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Имя</th>
                  <th>Логин</th>
                  <th></th>
                </tr>
              </thead>

              <tbody>
                <?php foreach ($students as $s): ?>
                  <tr>
                    <td><?= (int) $s['id'] ?></td>
                    <td><?= h($s['display_name']) ?></td>
                    <td><?= h($s['login']) ?></td>
                    <td>
                      <a href="student.php?id=<?= (int) $s['id'] ?>">
                        Карточка ученика
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>

                <?php if (!$students): ?>
                  <tr>
                    <td colspan="4" class="teacher-empty">
                      Пока нет учеников.
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

      </div>
    </div>

  </main>

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
</body>

</html>