<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/csrf.php';

if (is_logged_in()) {
  redirect_by_role();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $login = trim($_POST['login'] ?? '');
  $pass = $_POST['password'] ?? '';

  $stmt = $pdo->prepare("SELECT id, role, pass_hash, display_name, must_change_pass FROM users WHERE login=? LIMIT 1");
  $stmt->execute([$login]);
  $u = $stmt->fetch();

  if ($u && password_verify($pass, $u['pass_hash'])) {
    session_regenerate_id(true);

    $_SESSION['uid'] = (int) $u['id'];
    $_SESSION['role'] = $u['role'];
    $_SESSION['name'] = $u['display_name'];
    $_SESSION['must_change_pass'] = (int) $u['must_change_pass'];
    $_SESSION['remember'] = !empty($_POST['remember']);
    $_SESSION['last_activity'] = time();

    if (!empty($_POST['remember'])) {
      setcookie(session_name(), session_id(), [
        'expires' => time() + REMEMBER_TIMEOUT,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS'])
      ]);
    } else {
      setcookie(session_name(), session_id(), [
        'expires' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS'])
      ]);
    }

    redirect_by_role();
  } else {
    $error = 'Неверный логин или пароль';
  }
}
?>
<!doctype html>
<html lang="ru">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Вход</title>
  <link rel="stylesheet" href="assets/style.css?v=3">
  <style>
    /* ===== Страница входа ===== */

    body {
      background: #29209D;
      overflow-x: hidden;
    }

    /* Страница занимает всю высоту.
   Шапка fixed и не влияет на положение карточки. */
    .login-page {
      position: relative;
      min-height: 100vh;
      display: flex;
      align-items: stretch;
      isolation: isolate;

      background-color: #29209D;
      background-image: url("assets/img/login_bg.svg");
      background-repeat: no-repeat;
      background-position: center center;
      background-size: cover;
    }

    /* ===== Фиксированная шапка ===== */

    .login-header {
      position: fixed;
      top: clamp(16px, 1.5vw, 24px);
      left: 50%;
      z-index: 50;

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

    /* Логотип */
    .login-logo {
      grid-column: 1 / 2;
      justify-self: start;

      width: clamp(40px, 2.875vw, 46px);
      height: clamp(40px, 2.875vw, 46px);

      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
    }

    .login-logo img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: contain;
    }

    /* Центральные ссылки */
    .login-nav {
      grid-column: 5 / 9;

      display: flex;
      align-items: center;
      justify-content: center;
      gap: clamp(48px, 2.625vw, 42px);
    }

    .login-nav a {
      font-family: 'Montserrat', sans-serif;
      color: rgba(255, 255, 255, .62);
      text-decoration: none;
      font-size: clamp(14px, 1vw, 16px);
      white-space: nowrap;
      transition: color 0.3s ease;
    }

    .login-nav a:hover {
      color: rgba(255, 255, 255, 1);
    }

    /* Кнопка Войти справа */
    .login-header-btn {
      grid-column: 11 / 13;
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

    .login-header-btn:hover {
      box-shadow: 0 0 18px rgba(255, 159, 10, .35);
    }

    /* ===== Контейнер карточки ===== */

    .login-page .login-wrap {
      width: calc(100% - clamp(80px, 30vw, 480px));
      max-width: none;

      /* Центрирует карточку по вертикали и горизонтали */
      margin: auto;

      padding: clamp(96px, 9vh, 130px) 0 clamp(32px, 5vh, 64px);

      position: relative;
      z-index: 1;
    }

    .login-grid {
      width: 100%;

      display: grid;
      grid-template-columns: repeat(12, minmax(0, 1fr));
      gap: 20px;
      align-items: center;
    }

    /* ===== Карточка входа ===== */

    .login-card {
      grid-column: 2 / 12;
      width: 100%;

      min-height: clamp(430px, 30.5vw, 488px);

      padding: 8px;
      border-radius: 22px;
      background: #eeeeff;

      box-shadow: 0 18px 35px rgba(0, 0, 0, .22);
      transition: transform 0.3s ease;
    }

    .login-card:hover {
      transform: scale(1.01);
    }

    /* Внутренняя сетка карточки:
   10 колонок:
   картинка 1/5,
   5-я колонка — отступ,
   форма 6/11 */
    .login-form-area {
      min-height: calc(clamp(430px, 30.5vw, 488px) - 16px);
      height: 100%;

      display: grid;
      grid-template-columns: repeat(10, minmax(0, 1fr));
      gap: 20px;
      align-items: stretch;
    }

    /* Левая часть под картинку */
    .login-illustration {
      grid-column: 1 / 5;

      min-height: calc(clamp(430px, 30.5vw, 488px) - 16px);
      height: 100%;

      border-radius: 16px;
      background: #30239d;
      overflow: hidden;
    }

    .login-illustration img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: cover;
    }

    /* Правая часть с формой */
    .login-content {
      grid-column: 6 / 11;

      display: flex;
      flex-direction: column;
      justify-content: center;

      padding-right: clamp(20px, 2.625vw, 42px);
    }

    .login-title {
      margin-bottom: clamp(36px, 4vw, 64px);
    }

    .login-title h1 {
      font-family: Borsok;
      margin: 0 0 14px;
      font-size: clamp(30px, 2.375vw, 38px);
      line-height: 1;
      text-transform: uppercase;
      color: #22213a;
      font-weight: 900;
    }

    .login-title p {
      font-family: 'Montserrat', sans-serif;
      margin: 0;
      font-size: clamp(16px, 1.25vw, 20px);
      line-height: 1.25;
      color: #7b7b92;
      padding: 0;
    }

    .login-form {
      width: 100%;
    }

    .login-form p {
      margin: clamp(12px, 1.125vw, 18px) 0;
    }

    .login-form input {
      width: 100%;
      height: clamp(48px, 3.5vw, 56px);
      padding: 0 18px;

      border-radius: 999px;
      border: 2px solid #d7d7eb;
      background: #eeeeff;
      color: #ABACC2;
      font-size: clamp(14px, 1vw, 16px);

      box-shadow: inset 0 2px 5px rgba(0, 0, 0, .06);
      transition: transform 0.2s ease;
    }

    .login-form input:hover {
      transform: scale(1.03);
    }

    .login-btn {
      font-family: Borsok;
      font-size: clamp(17px, 1.25vw, 20px);
      width: 100%;
      height: clamp(48px, 3.5vw, 56px);
      margin-top: 8px;

      border-radius: 999px;
      background: #ff9f0a;
      color: #fff;

      text-transform: uppercase;

      box-shadow: 0 8px 18px rgba(255, 159, 10, .38);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .login-btn:hover {
      box-shadow: 0 0 18px rgba(255, 159, 10, .5);
      transform: scale(1.03);
    }

    /* Чекбокс "Запомнить меня" */
    .remember {
      padding-left: clamp(8px, 1vw, 16px);
      display: flex;
      align-items: center;
      gap: 10px;

      font-size: clamp(13px, .9375vw, 15px);
      color: #ABACC2;
      transition: transform 0.2s ease;
    }

    .remember:hover {
      transform: scale(1.03);
    }

    .remember input {
      width: 20px;
      height: 20px;
      padding: 0;
      border-radius: 6px;
      color: #ABACC2;
    }

    /* ===== Ноутбуки ===== */

    @media (max-width: 1366px) {
      .login-card {
        grid-column: 1 / 13;
      }

      .login-nav {
        grid-column: 4 / 10;
      }

      .login-header-btn {
        grid-column: 10 / 13;
      }
    }

    /* Низкие экраны ноутбуков */
    @media (max-height: 760px) {
      .login-page .login-wrap {
        padding-top: 112px;
        padding-bottom: 32px;
      }

      .login-card {
        min-height: 430px;
      }

      .login-form-area,
      .login-illustration {
        min-height: 414px;
      }

      .login-title {
        margin-bottom: 32px;
      }

      .login-form p {
        margin: 12px 0;
      }
    }

    /* ===== Мобильная версия login ===== */

    @media (max-width: 768px) {

      html,
      body {
        height: 100%;
        overflow: hidden;
      }

      body {
        background: #29209D;
        padding-bottom: 0;
      }

      .login-page {
        height: 100dvh;
        min-height: 100dvh;

        display: flex;
        align-items: center;

        padding: 22px 0 84px;

        overflow: hidden;

        background-position: center center;
        background-size: cover;
      }

      /* ===== Header mobile bottom ===== */

      .login-header {
        position: fixed;
        top: auto;
        bottom: 14px;
        left: 50%;
        z-index: 9999;

        width: calc(100% - 24px);
        height: 58px;
        transform: translateX(-50%);

        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: center;
        gap: 12px;

        padding: 0 14px;
        border-radius: 999px;
      }

      .login-logo {
        grid-column: 1 / 2;
        justify-self: start;

        width: 38px;
        height: 38px;
      }

      .login-nav {
        display: none;
      }

      .login-header-btn {
        grid-column: 2 / 3;
        justify-self: end;

        min-width: 96px;
        height: 34px;

        font-size: 14px;
      }

      /* ===== Login card mobile ===== */

      .login-page .login-wrap {
        width: calc(100% - 32px);
        max-width: 430px;

        margin: auto;
        padding: 0;

        position: relative;
        z-index: 2;
      }

      .login-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
      }

      .login-card {
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

      .login-card:hover {
        transform: none;
      }

      .login-form-area {
        min-height: auto;
        height: auto;

        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0;
      }

      .login-illustration {
        display: none;
      }

      .login-content {
        grid-column: 1 / 3;

        padding: 34px 22px 28px;

        display: flex;
        flex-direction: column;
        justify-content: center;
      }

      .login-title {
        margin-bottom: 30px;
        text-align: center;
      }

      .login-title h1 {
        margin: 0 0 14px;

        font-size: 34px;
        line-height: .98;
      }

      .login-title p {
        max-width: 330px;
        margin: 0 auto;

        font-size: 15px;
        line-height: 1.32;
      }

      .login-title p br {
        display: none;
      }

      .login-form {
        width: 100%;
      }

      .login-form p {
        margin: 14px 0;
      }

      .login-form input {
        width: 100%;
        height: 54px;
        padding: 0 20px;

        font-size: 16px;
      }

      .login-form input:hover {
        transform: none;
      }

      .remember {
        justify-content: center;

        padding-left: 0;
        margin: 2px 0 4px;

        font-size: 15px;
      }

      .remember:hover {
        transform: none;
      }

      .remember input {
        width: 21px;
        height: 21px;
      }

      .login-btn {
        height: 54px;
        margin-top: 10px;

        font-size: 17px;
      }

      .login-btn:hover {
        transform: none;
      }

      .err {
        margin-top: 14px;
        text-align: center;
      }
    }

    /* ===== Очень маленькие телефоны ===== */

    @media (max-width: 420px) {
      .login-page {
        padding: 18px 0 80px;
      }

      .login-header {
        bottom: 10px;
        width: calc(100% - 18px);
        height: 58px;
        padding: 0 12px;
      }

      .login-logo {
        width: 38px;
        height: 38px;
      }

      .login-header-btn {
        min-width: 96px;
        height: 34px;
        font-size: 14px;
      }

      .login-page .login-wrap {
        width: calc(100% - 24px);
        max-width: 390px;
      }

      .login-card {
        padding: 8px;
        border-radius: 30px;
      }

      .login-content {
        padding: 32px 18px 26px;
      }

      .login-title {
        margin-bottom: 28px;
      }

      .login-title h1 {
        font-size: 31px;
      }

      .login-title p {
        max-width: 300px;
        font-size: 14px;
        line-height: 1.28;
      }

      .login-form p {
        margin: 13px 0;
      }

      .login-form input {
        height: 52px;
        font-size: 15px;
      }

      .remember {
        font-size: 14px;
      }

      .remember input {
        width: 20px;
        height: 20px;
      }

      .login-btn {
        height: 52px;
        font-size: 16px;
      }
    }
  </style>
</head>

<body>
  <main class="login-page">

    <header class="login-header">
      <a href="index.php" class="login-logo" aria-label="Логотип">
        <img src="assets/img/logo.svg" alt="Логотип">
      </a>

      <nav class="login-nav">
        <a href="index.php#home">Главная</a>
        <a href="index.php#lopa">О Лопе</a>
        <a href="index.php#how">Как это работает?</a>
      </nav>

      <a href="login.php" class="login-header-btn">Войти</a>
    </header>

    <div class="wrap login-wrap">
      <div class="grid login-grid">

        <section class="card login-card">
          <div class="login-form-area">

            <div class="login-illustration">
              <img src="assets/img/login_img.jpg" alt="Иллюстрация входа">
            </div>

            <div class="login-content">
              <div class="login-title">
                <h1>Вход в аккаунт</h1>
                <p>Логин и пароль вам выдает преподаватель.<br>
                  Вы можете поменять свой пароль в профиле.
                </p>
              </div>

              <form method="post" class="login-form">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

                <p>
                  <input name="login" placeholder="Логин" required>
                </p>

                <p>
                  <input name="password" type="password" placeholder="Пароль" required>
                </p>

                <p>
                  <label class="remember">
                    <input type="checkbox" name="remember" value="1">
                    Запомнить меня
                  </label>
                </p>

                <button class="login-btn">Войти</button>

                <?php if ($error): ?>
                  <div class="err"><?= h($error) ?></div>
                <?php endif; ?>
              </form>
            </div>

          </div>
        </section>

      </div>
    </div>
  </main>
</body>

</html>