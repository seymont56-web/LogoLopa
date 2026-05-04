  <?php
  require_once __DIR__ . '/../inc/db.php';
  require_once __DIR__ . '/../inc/auth.php';
  require_once __DIR__ . '/../inc/csrf.php';

  require_role('teacher');

  $studentId = (int) ($_GET['id'] ?? 0);

  if ($studentId <= 0) {
    http_response_code(400);
    echo "Bad request";
    exit;
  }

  $student = $pdo->prepare("
    SELECT id, display_name, login, created_at, must_change_pass
    FROM users
    WHERE id = ? AND role = 'student'
    LIMIT 1
  ");
  $student->execute([$studentId]);
  $stu = $student->fetch();

  if (!$stu) {
    http_response_code(404);
    echo "Not found";
    exit;
  }

  $ok = '';
  $err = '';
  $newPassword = $_SESSION['new_temp_password'] ?? '';
  unset($_SESSION['new_temp_password']);

  function is_ajax_request(): bool
  {
    return (
      isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    );
  }

  function json_response(array $data): void
  {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
  }

  function generate_temp_password(int $length = 8): string
  {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
      $password .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return $password;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action = $_POST['action'] ?? '';

    try {
      if ($action === 'update_name') {
        $displayName = trim($_POST['display_name'] ?? '');

        if ($displayName === '') {
          $err = 'Имя ученика не может быть пустым.';
        } else {
          $stmt = $pdo->prepare("
            UPDATE users
            SET display_name = ?
            WHERE id = ? AND role = 'student'
          ");
          $stmt->execute([$displayName, $studentId]);

          $stu['display_name'] = $displayName;
          $ok = 'Имя ученика обновлено.';
        }
      }

      if ($action === 'reset_password') {
        $tempPassword = trim($_POST['new_password'] ?? '');

        if ($tempPassword === '') {
          $tempPassword = generate_temp_password();
        }

        if (mb_strlen($tempPassword) < 4) {
          $err = 'Пароль должен содержать минимум 4 символа.';
        } else {
          $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

          $stmt = $pdo->prepare("
            UPDATE users
            SET pass_hash = ?, must_change_pass = 1
            WHERE id = ? AND role = 'student'
          ");
          $stmt->execute([$hash, $studentId]);

          $_SESSION['new_temp_password'] = $tempPassword;

          header('Location: student.php?id=' . $studentId);
          exit;
        }
      }

      if ($action === 'save_access') {
        $enabled = $_POST['enabled'] ?? [];

        $pdo->beginTransaction();

        $gamesIds = $pdo->query("SELECT id FROM games")->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("
          INSERT INTO student_game_access (student_id, game_id, is_enabled)
          VALUES (?, ?, ?)
          ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)
        ");

        foreach ($gamesIds as $gid) {
          $isOn = isset($enabled[$gid]) ? 1 : 0;
          $stmt->execute([$studentId, (int) $gid, $isOn]);
        }

        $pdo->commit();
        $ok = 'Доступы к играм сохранены.';

        if (is_ajax_request()) {
          json_response([
            'success' => true,
            'message' => $ok,
            'action' => 'save_access'
          ]);
        }
      }

      if ($action === 'open_all_games') {
        $pdo->beginTransaction();

        $gamesIds = $pdo->query("SELECT id FROM games")->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("
          INSERT INTO student_game_access (student_id, game_id, is_enabled)
          VALUES (?, ?, 1)
          ON DUPLICATE KEY UPDATE is_enabled = 1
        ");

        foreach ($gamesIds as $gid) {
          $stmt->execute([$studentId, (int) $gid]);
        }

        $pdo->commit();
        $ok = 'Все игры открыты для ученика.';

        if (is_ajax_request()) {
          json_response([
            'success' => true,
            'message' => $ok,
            'action' => 'open_all_games'
          ]);
        }
      }

      if ($action === 'close_all_games') {
        $pdo->beginTransaction();

        $gamesIds = $pdo->query("SELECT id FROM games")->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("
          INSERT INTO student_game_access (student_id, game_id, is_enabled)
          VALUES (?, ?, 0)
          ON DUPLICATE KEY UPDATE is_enabled = 0
        ");

        foreach ($gamesIds as $gid) {
          $stmt->execute([$studentId, (int) $gid]);
        }

        $pdo->commit();
        $ok = 'Все игры закрыты для ученика.';

        if (is_ajax_request()) {
          json_response([
            'success' => true,
            'message' => $ok,
            'action' => 'close_all_games'
          ]);
        }
      }

      if ($action === 'delete_student') {
        $stmt = $pdo->prepare("
          DELETE FROM users
          WHERE id = ? AND role = 'student'
        ");
        $stmt->execute([$studentId]);

        header('Location: dashboard.php');
        exit;
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }

      $err = 'Ошибка выполнения действия.';

      if (is_ajax_request()) {
        json_response([
          'success' => false,
          'message' => $err
        ]);
      }
    }

    $student = $pdo->prepare("
      SELECT id, display_name, login, created_at, must_change_pass
      FROM users
      WHERE id = ? AND role = 'student'
      LIMIT 1
    ");
    $student->execute([$studentId]);
    $stu = $student->fetch();

    if (!$stu) {
      header('Location: dashboard.php');
      exit;
    }
  }

  $rows = $pdo->prepare("
    SELECT g.id, g.title, g.code, a.is_enabled
    FROM games g
    LEFT JOIN student_game_access a
      ON a.game_id = g.id AND a.student_id = ?
    ORDER BY g.id
  ");
  $rows->execute([$studentId]);
  $games = $rows->fetchAll();

  $openedGames = array_values(array_filter($games, function ($game) {
    return (int) ($game['is_enabled'] ?? 0) === 1;
  }));

  $hasResultsTable = false;

  try {
    $check = $pdo->query("SHOW TABLES LIKE 'game_result'");
    $hasResultsTable = (bool) $check->fetchColumn();
  } catch (Throwable $e) {
    $hasResultsTable = false;
  }

  $resultsByGame = [];

  if ($hasResultsTable && $openedGames) {
    try {
      $res = $pdo->prepare("
        SELECT
          r.id,
          r.student_id,
          r.game_id,
          r.correct_answers,
          r.incorrect_answers,
          r.time_spent,
          r.completed_at,
          g.title AS game_title
        FROM game_result r
        INNER JOIN games g ON g.id = r.game_id
        WHERE r.student_id = ?
        ORDER BY r.completed_at DESC, r.id DESC
      ");
      $res->execute([$studentId]);
      $allResults = $res->fetchAll();

      foreach ($allResults as $item) {
        $gid = (int) $item['game_id'];

        if (!isset($resultsByGame[$gid])) {
          $resultsByGame[$gid] = [];
        }

        $resultsByGame[$gid][] = $item;
      }
    } catch (Throwable $e) {
      $resultsByGame = [];
    }
  }
  ?>
  <!doctype html>
  <html lang="ru">

  <head>
    <meta charset="utf-8">
    <title>Карточка ученика</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/style.css?v=2">
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

      .teacher-mobile-back {
        display: none;
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


      .teacher-wrap {
        width: calc(100% - clamp(80px, 30vw, 480px));
        max-width: none;

        margin: 0 auto;
        padding: clamp(130px, 10vw, 150px) 0 clamp(40px, 5vh, 70px);

        position: relative;
        z-index: 1;
      }

      .teacher-grid {
        width: 100%;

        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 20px;
        align-items: start;
      }


      .student-title-row {
        grid-column: 1 / 13;

        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 20px;
        align-items: end;

        margin-bottom: clamp(8px, 1vw, 14px);
      }

      .student-title {
        grid-column: 1 / 10;
      }

      .student-title h1 {
        margin: 0;

        color: #fff;

        font-family: Borsok;
        font-size: clamp(34px, 3vw, 48px);
        line-height: 1;
        text-transform: uppercase;
        font-weight: 900;
      }

      .student-title p {
        margin: 12px 0 0;

        color: rgba(255, 255, 255, .68);

        font-family: 'Montserrat', sans-serif;
        font-size: clamp(14px, 1vw, 16px);
        line-height: 1.25;
      }

      .student-title-actions {
        grid-column: 10 / 13;

        display: flex;
        justify-content: flex-end;
        align-items: center;
      }


      .student-main-card,
      .student-access-card {
        grid-column: 1 / 13;
        width: 100%;

        background: #eeeeff;
        border-radius: 22px;

        padding: clamp(24px, 2vw, 32px);

        box-shadow: 0 18px 35px rgba(0, 0, 0, .22);
        transition: transform 0.3s ease;
      }

      .student-main-card:hover,
      .student-access-card:hover {
        transform: scale(1.01);
      }

      .student-main-card {
        min-height: 0;
      }

      .student-main-card.is-collapsed {
        padding-bottom: clamp(24px, 2vw, 32px);
      }

      .student-access-card {
        min-height: clamp(390px, 28vw, 470px);
      }

      .card-title-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;

        margin-bottom: 22px;
      }

      .card-title-row h2,
      .modal-box h2,
      .result-game-panel h3 {
        margin: 0;

        color: #22213a;

        font-family: Borsok;
        font-size: clamp(22px, 1.75vw, 28px);
        line-height: 1;
        text-transform: uppercase;
        font-weight: 900;
      }

      .card-subtitle {
        margin-top: 10px;

        color: #7b7b92;

        font-family: 'Montserrat', sans-serif;
        font-size: clamp(14px, 1vw, 16px);
        line-height: 1.25;
      }

      .student-info-body {
        display: block;
      }

      .student-info-body.is-collapsed {
        display: none;
      }

      .collapse-btn {
        border: 0;
        background: transparent;
        cursor: pointer;

        color: #7b7b92;

        font-family: 'Montserrat', sans-serif;
        font-size: 14px;

        transition: color 0.2s ease;
      }

      .collapse-btn:hover {
        color: #22213a;
      }

      .student-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 24px;
        flex-wrap: wrap;
      }

      .student-name {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;

        margin-bottom: 14px;
      }

      .student-name strong {
        color: #22213a;

        font-family: 'Montserrat', sans-serif;
        font-size: clamp(22px, 1.625vw, 26px);
        font-weight: 800;
      }

      .student-meta {
        display: flex;
        flex-direction: column;
        gap: 8px;
      }

      .small {
        color: #7b7b92;

        font-family: 'Montserrat', sans-serif;
        font-size: clamp(14px, 1vw, 16px);
        line-height: 1.3;
      }

      .icon-btn {
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 50%;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        background: rgba(255, 255, 255, .55);

        cursor: pointer;
        transition: transform 0.2s ease, background 0.2s ease;
      }

      .icon-btn:hover {
        transform: scale(1.08);
        background: rgba(255, 255, 255, .85);
      }

      .actions-row,
      .games-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
      }

      .actions-row {
        margin-top: 24px;
      }

      .games-actions {
        justify-content: flex-end;
      }


      button,
      .orange-btn,
      .soft-btn,
      .danger-btn {
        border: 0;
        cursor: pointer;
        text-decoration: none;
      }

      .orange-btn,
      button.orange-btn {
        min-width: 150px;
        height: 42px;
        padding: 0 24px;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        border-radius: 999px;
        background: #ff9f0a;
        color: #fff;

        font-family: Borsok;
        font-size: clamp(15px, 1vw, 16px);
        text-transform: uppercase;

        box-shadow: 0 8px 18px rgba(255, 159, 10, .38);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }

      .orange-btn:hover {
        transform: scale(1.03);
        box-shadow: 0 0 18px rgba(255, 159, 10, .45);
      }

      .soft-btn,
      button.soft-btn {
        min-width: 150px;
        height: 42px;
        padding: 0 24px;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        border-radius: 999px;
        background: rgba(255, 255, 255, .55);
        color: #22213a;

        font-family: 'Montserrat', sans-serif;
        font-size: 14px;

        transition: transform 0.2s ease, background 0.2s ease;
      }

      .soft-btn:hover {
        transform: scale(1.03);
        background: rgba(255, 255, 255, .85);
      }

      .soft-btn:disabled,
      .orange-btn:disabled,
      .danger-btn:disabled {
        cursor: default;
        transform: none;
      }

      .danger-btn,
      button.danger-btn {
        min-width: 150px;
        height: 42px;
        padding: 0 24px;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        border-radius: 999px;
        background: #ef7d87;
        color: #fff;

        font-family: 'Montserrat', sans-serif;
        font-size: 14px;

        box-shadow: 0 6px 14px rgba(239, 125, 135, .35);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
      }

      .danger-btn:hover {
        transform: scale(1.03);
        box-shadow: 0 0 18px rgba(239, 125, 135, .35);
      }


      .games-table-scroll {
        width: 100%;
        max-height: 330px;
        overflow-y: auto;
        overflow-x: auto;

        margin-top: 8px;
      }

      .games-table {
        width: 100%;
        min-width: 720px;
        border-collapse: collapse;

        font-family: 'Montserrat', sans-serif;
      }

      .games-table th {
        position: sticky;
        top: 0;
        z-index: 2;

        padding: 0 12px 12px;
        border-bottom: 2px solid #d7d7eb;

        background: #eeeeff;
        color: #22213a;

        font-size: clamp(15px, 1vw, 16px);
        text-align: left;
        font-weight: 800;
      }

      .games-table td {
        padding: 10px 12px;
        border-bottom: 2px solid #d7d7eb;

        color: #7b7b92;
        font-size: clamp(14px, 1vw, 16px);
      }

      .games-table th:nth-child(1),
      .games-table td:nth-child(1) {
        width: 34%;
      }

      .games-table th:nth-child(2),
      .games-table td:nth-child(2) {
        width: 30%;
      }

      .games-table th:nth-child(3),
      .games-table td:nth-child(3) {
        width: 36%;
      }

      .access-toggle input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
      }

      .access-pill {
        min-width: 150px;
        height: 42px;
        padding: 0 24px;
        border-radius: 999px;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        color: #fff;
        background: #ef7d87;

        font-family: 'Montserrat', sans-serif;
        font-size: 14px;
        cursor: pointer;

        box-shadow: 0 6px 14px rgba(239, 125, 135, .35);
        transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
      }

      .access-pill:hover {
        transform: scale(1.03);
        box-shadow: 0 0 18px rgba(239, 125, 135, .35);
      }

      .access-toggle input:checked + .access-pill {
        background: rgba(255, 255, 255, .55);
        color: #22213a;
        box-shadow: none;
      }

      .access-toggle input:checked + .access-pill:hover {
        background: rgba(255, 255, 255, .85);
      }

      .access-pill::before {
        content: "Закрыт";
      }

      .access-toggle input:checked + .access-pill::before {
        content: "Открыт";
      }

      .save-row {
        display: flex;
        justify-content: flex-end;

        margin-top: 32px;
      }


      .toast-container {
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 3000;

        display: flex;
        flex-direction: column;
        gap: 12px;

        pointer-events: none;
      }

      .toast {
        min-width: 280px;
        max-width: 380px;

        padding: 14px 18px;
        border-radius: 18px;

        color: #22213a;
        background: #eeeeff;

        font-family: 'Montserrat', sans-serif;
        font-size: 14px;
        line-height: 1.35;

        box-shadow: 0 14px 30px rgba(0, 0, 0, .28);

        opacity: 0;
        transform: translateX(24px);

        animation: toastIn .25s ease forwards;
      }

      .toast.is-hide {
        animation: toastOut .25s ease forwards;
      }

      .toast-success {
        border-left: 6px solid #8ee685;
      }

      .toast-error {
        border-left: 6px solid #ef7d87;
      }

      @keyframes toastIn {
        to {
          opacity: 1;
          transform: translateX(0);
        }
      }

      @keyframes toastOut {
        to {
          opacity: 0;
          transform: translateX(24px);
        }
      }


      .modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1000;

        padding: 20px;
        background: rgba(0, 0, 0, .55);
        backdrop-filter: blur(7px);
        -webkit-backdrop-filter: blur(7px);
      }

      .modal.is-open {
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .modal-box {
        width: min(920px, 100%);
        max-height: 90vh;
        overflow: auto;

        padding: clamp(22px, 2vw, 32px);
        border-radius: 22px;
        background: #eeeeff;

        box-shadow: 0 18px 35px rgba(0, 0, 0, .35);
      }

      .modal-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;

        margin-bottom: 22px;
      }

      .modal-top h2 {
        font-size: clamp(22px, 1.75vw, 28px);
      }

      .modal-close {
        width: 36px;
        height: 36px;
        min-width: 36px;
        padding: 0;
        border: 0;
        border-radius: 50%;

        display: flex;
        align-items: center;
        justify-content: center;

        background: rgba(255, 255, 255, .55);
        color: #22213a;

        font-family: Arial, sans-serif;
        font-size: 0;
        line-height: 1;

        cursor: pointer;
        transition: transform 0.2s ease, background 0.2s ease;
      }

      .modal-close::before {
        content: "×";
        display: block;
        font-size: 28px;
        line-height: 1;
        transform: translateY(-1px);
      }

      .modal-close:hover {
        transform: scale(1.08);
        background: rgba(255, 255, 255, .85);
      }

      .modal-form p {
        margin: 14px 0;
      }

      .modal-form input {
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

      .modal-form input::placeholder {
        color: #ABACC2;
      }

      .modal-form input:hover,
      .modal-form input:focus {
        transform: scale(1.01);
        border-color: #c7c7e8;
      }

      .temp-password-box {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-height: 54px;
        padding: 10px 24px;
        margin: 16px 0;

        border-radius: 999px;
        background: rgba(255, 255, 255, .65);

        color: #22213a;

        font-family: 'Montserrat', sans-serif;
        font-size: clamp(22px, 1.75vw, 28px);
        font-weight: 800;
      }

      .muted-box {
        padding: 16px 18px;
        border-radius: 16px;
        background: rgba(255, 255, 255, .55);
      }


      .result-game-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin: 18px 0;
      }

      .result-game-btn.is-active {
        background: #ff9f0a;
        color: #fff;
        font-family: Borsok;
        text-transform: uppercase;
        box-shadow: 0 8px 18px rgba(255, 159, 10, .38);
      }

      .result-game-panel {
        display: none;
        margin-top: 20px;
      }

      .result-game-panel.is-active {
        display: block;
      }

      .result-game-panel h3 {
        margin-bottom: 16px;
        font-size: 22px;
      }

      .result-table-wrap {
        overflow-x: auto;
      }

      .result-table {
        width: 100%;
        min-width: 760px;
        border-collapse: collapse;

        font-family: 'Montserrat', sans-serif;
      }

      .result-table th {
        padding: 0 12px 12px;
        border-bottom: 2px solid #d7d7eb;

        color: #22213a;
        font-size: 15px;
        text-align: left;
        font-weight: 800;
      }

      .result-table td {
        padding: 13px 12px;
        border-bottom: 2px solid #d7d7eb;

        color: #7b7b92;
        font-size: 14px;
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

        .student-title-row,
        .student-main-card,
        .student-access-card {
          grid-column: 1 / 13;
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

        .student-title {
          grid-column: 1 / 9;
        }

        .student-title-actions {
          grid-column: 9 / 13;
          justify-content: flex-end;
        }

        .card-title-row {
          align-items: flex-start;
        }

        .games-actions {
          justify-content: flex-end;
        }
      }


      @media (max-width: 900px) and (min-width: 521px) {
        .teacher-header {
          top: 16px;

          width: calc(100% - 32px);
          height: 62px;
          min-height: 62px;

          grid-template-columns: repeat(12, minmax(0, 1fr));
          gap: 12px;

          padding: 0 18px;
        }

        .teacher-logo {
          grid-column: 1 / 2;

          width: 40px;
          height: 40px;
        }

        .teacher-nav {
          grid-column: 2 / 10;

          justify-content: center;
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

        .teacher-grid {
          gap: 18px;
        }

        .student-title-row {
          gap: 16px;
        }

        .student-title {
          grid-column: 1 / 13;
        }

        .student-title h1 {
          font-size: 34px;
        }

        .student-title p {
          max-width: 520px;
          font-size: 14px;
        }

        .student-title-actions {
          grid-column: 1 / 13;
          justify-content: flex-start;
        }

        .student-title-actions .orange-btn {
          width: auto;
        }

        .student-main-card,
        .student-access-card {
          padding: 24px;
          border-radius: 24px;
        }

        .student-main-card:hover,
        .student-access-card:hover,
        .orange-btn:hover,
        .soft-btn:hover,
        .danger-btn:hover,
        .access-pill:hover,
        .icon-btn:hover {
          transform: none;
        }

        .card-title-row {
          flex-direction: column;
          gap: 14px;
        }

        .games-actions {
          width: 100%;

          display: grid;
          grid-template-columns: repeat(2, minmax(0, 1fr));
          gap: 10px;
        }

        .games-actions form {
          width: 100%;
        }

        .games-actions .soft-btn {
          width: 100%;
        }

        .actions-row {
          width: 100%;

          display: grid;
          grid-template-columns: repeat(3, minmax(0, 1fr));
          gap: 10px;
        }

        .actions-row .soft-btn,
        .actions-row .danger-btn {
          width: 100%;
          min-width: 0;
        }

        .save-row {
          justify-content: stretch;
        }

        .save-row .orange-btn {
          width: 100%;
        }

        .modal {
          padding: 16px;
        }

        .toast-container {
          left: 18px;
          right: 18px;
          bottom: 18px;
        }

        .toast {
          width: 100%;
          min-width: 0;
          max-width: none;
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

        .teacher-logo,
        .teacher-nav {
          display: none;
        }

        .teacher-mobile-back {
          grid-column: 1 / 2;
          justify-self: start;

          width: 38px;
          height: 38px;
          border-radius: 50%;

          display: inline-flex;
          align-items: center;
          justify-content: center;

          background: rgba(255, 255, 255, .16);
          color: #fff;

          font-family: Arial, sans-serif;
          font-size: 38px;
          line-height: 1;
          text-decoration: none;

          padding-bottom: 5px;
        }

        .teacher-header-btn {
          grid-column: 2 / 3;
          justify-self: end;

          min-width: 96px;
          height: 34px;

          font-size: 14px;
        }

        .teacher-wrap {
          width: calc(100% - 28px);

          margin: 0 auto;
          padding: 30px 0 112px;

          position: relative;
          z-index: 1;
        }

        .teacher-grid {
          display: grid;
          grid-template-columns: repeat(2, minmax(0, 1fr));
          gap: 22px;
        }

        .student-title-row {
          grid-column: 1 / 3;

          display: grid;
          grid-template-columns: repeat(2, minmax(0, 1fr));
          gap: 16px;

          margin-bottom: 0;
        }

        .student-title {
          grid-column: 1 / 3;
          text-align: center;
        }

        .student-title h1 {
          font-size: 30px;
          line-height: .98;
        }

        .student-title p {
          max-width: 340px;
          margin: 12px auto 0;

          font-size: 13px;
          line-height: 1.3;
        }

        .student-title-actions {
          display: none;
        }

        .student-main-card,
        .student-access-card {
          grid-column: 1 / 3;

          padding: 26px 16px;
          border-radius: 30px;
        }

        .student-main-card:hover,
        .student-access-card:hover {
          transform: none;
        }

        .student-access-card {
          min-height: auto;
        }

        .card-title-row {
          flex-direction: column;
          align-items: center;
          gap: 14px;

          margin-bottom: 24px;

          text-align: center;
        }

        .card-title-row h2,
        .modal-box h2,
        .result-game-panel h3 {
          font-size: 24px;
          line-height: .98;
          text-align: center;
        }

        .card-subtitle {
          margin-top: 10px;

          font-size: 13px;
          line-height: 1.3;
        }

        .collapse-btn {
          height: 36px;
          padding: 0 18px;

          border-radius: 999px;
          background: rgba(255, 255, 255, .55);

          font-size: 13px;
        }

        .student-head {
          justify-content: center;
          text-align: center;
        }

        .student-name {
          justify-content: center;
          margin-bottom: 16px;
        }

        .student-name strong {
          font-size: 22px;
        }

        .student-meta {
          align-items: center;
          gap: 7px;
        }

        .small {
          font-size: 13px;
          line-height: 1.3;
        }

        .icon-btn {
          width: 34px;
          height: 34px;
        }

        .actions-row {
          width: 100%;

          display: grid;
          grid-template-columns: 1fr;
          gap: 10px;

          margin-top: 24px;
        }

        .actions-row .soft-btn,
        .actions-row .danger-btn,
        .actions-row .orange-btn {
          width: 100%;
        }

        .orange-btn,
        button.orange-btn,
        .soft-btn,
        button.soft-btn,
        .danger-btn,
        button.danger-btn {
          width: 100%;
          min-width: 0;
          height: 46px;
          padding: 0 18px;

          font-size: 14px;
        }

        .orange-btn:hover,
        .soft-btn:hover,
        .danger-btn:hover,
        .access-pill:hover,
        .icon-btn:hover,
        .modal-close:hover {
          transform: none;
        }

        .games-actions {
          width: 100%;

          display: grid;
          grid-template-columns: 1fr;
          gap: 10px;

          justify-content: stretch;
        }

        .games-actions form {
          width: 100%;
        }

        .games-table-scroll {
          max-height: none;
          overflow: visible;

          margin-top: 0;
        }

        .games-table {
          width: 100%;
          min-width: 0;

          border-collapse: separate;
          border-spacing: 0 14px;
        }

        .games-table thead {
          display: none;
        }

        .games-table,
        .games-table tbody,
        .games-table tr,
        .games-table td {
          display: block;
        }

        .games-table tr {
          padding: 16px;

          border-radius: 22px;
          background: rgba(255, 255, 255, .58);

          box-shadow: inset 0 0 0 1px rgba(34, 33, 58, .06);
        }

        .games-table td {
          padding: 0;
          border-bottom: none;

          font-size: 14px;
          line-height: 1.25;
        }

        .games-table td:nth-child(1) {
          margin-bottom: 6px;

          font-size: 18px;
          font-weight: 800;
          color: #22213a;
        }

        .games-table td:nth-child(2) {
          margin-bottom: 14px;

          font-size: 13px;
          color: #7b7b92;
        }

        .games-table td:nth-child(2)::before {
          content: "Код: ";
          font-weight: 700;
          color: #4c4b63;
        }

        .games-table td:nth-child(3) {
          margin-top: 10px;
        }

        .access-toggle {
          width: 100%;
          display: block;
        }

        .access-pill {
          width: 100%;
          height: 44px;

          font-size: 14px;
        }

        .save-row {
          justify-content: stretch;
          margin-top: 24px;
        }

        .toast-container {
          left: 14px;
          right: 14px;
          bottom: 86px;

          gap: 10px;
        }

        .toast {
          width: 100%;
          min-width: 0;
          max-width: none;

          padding: 13px 16px;
          border-radius: 18px;

          font-size: 13px;
        }

        .modal {
          padding: 14px;
        }

        .modal.is-open {
          align-items: center;
          justify-content: center;
        }

        .modal-box {
          width: 100%;
          max-height: calc(100dvh - 28px);

          padding: 24px 16px;
          border-radius: 28px;
        }

        .modal-top {
          gap: 14px;
          margin-bottom: 20px;
        }

        .modal-close {
          width: 36px;
          height: 36px;
          min-width: 36px;
        }

        .modal-form p {
          margin: 12px 0;
        }

        .modal-form input {
          height: 50px;
          font-size: 14px;
        }

        .modal-form input:hover,
        .modal-form input:focus {
          transform: none;
        }

        .temp-password-box {
          width: 100%;

          min-height: 52px;
          padding: 10px 18px;

          font-size: 22px;
        }

        .muted-box {
          padding: 14px;
          border-radius: 18px;
        }

        .result-game-buttons {
          display: grid;
          grid-template-columns: 1fr;
          gap: 10px;

          margin: 18px 0;
        }

        .result-game-buttons .soft-btn {
          width: 100%;
        }

        .result-game-panel {
          margin-top: 18px;
        }

        .result-game-panel h3 {
          margin-bottom: 14px;
          font-size: 21px;
        }

        .result-table-wrap {
          overflow: visible;
        }

        .result-table {
          width: 100%;
          min-width: 0;

          border-collapse: separate;
          border-spacing: 0 12px;
        }

        .result-table thead {
          display: none;
        }

        .result-table,
        .result-table tbody,
        .result-table tr,
        .result-table td {
          display: block;
        }

        .result-table tr {
          padding: 14px;

          border-radius: 20px;
          background: rgba(255, 255, 255, .58);
        }

        .result-table td {
          padding: 0;
          border-bottom: none;

          font-size: 13px;
          line-height: 1.35;
          color: #4c4b63;
        }

        .result-table td + td {
          margin-top: 8px;
        }

        .result-table td:nth-child(1)::before {
          content: "Правильные ответы: ";
          font-weight: 800;
          color: #22213a;
        }

        .result-table td:nth-child(2)::before {
          content: "Неправильные ответы: ";
          font-weight: 800;
          color: #22213a;
        }

        .result-table td:nth-child(3)::before {
          content: "Время: ";
          font-weight: 800;
          color: #22213a;
        }

        .result-table td:nth-child(4)::before {
          content: "Когда закончил: ";
          font-weight: 800;
          color: #22213a;
        }
      }

      @media (max-width: 420px) {
        body {
          padding-bottom: 100px;
        }

        .teacher-header {
          bottom: 12px;

          width: calc(100% - 22px);
          height: 58px;
          min-height: 58px;

          padding: 0 12px;
        }

        .teacher-mobile-back {
          width: 38px;
          height: 38px;
        }

        .teacher-header-btn {
          min-width: 96px;
          height: 34px;

          font-size: 14px;
        }

        .teacher-wrap {
          width: calc(100% - 24px);
          padding-top: 26px;
          padding-bottom: 108px;
        }

        .teacher-grid {
          gap: 20px;
        }

        .student-title h1 {
          font-size: 27px;
        }

        .student-title p {
          max-width: 310px;

          font-size: 12.5px;
        }

        .student-main-card,
        .student-access-card {
          padding: 24px 14px;
          border-radius: 28px;
        }

        .card-title-row h2,
        .modal-box h2,
        .result-game-panel h3 {
          font-size: 22px;
        }

        .card-subtitle {
          font-size: 12.5px;
        }

        .student-name strong {
          font-size: 20px;
        }

        .games-table tr {
          padding: 14px;
          border-radius: 20px;
        }

        .games-table td:nth-child(1) {
          font-size: 17px;
        }

        .orange-btn,
        button.orange-btn,
        .soft-btn,
        button.soft-btn,
        .danger-btn,
        button.danger-btn {
          height: 44px;
          font-size: 13px;
        }

        .access-pill {
          height: 42px;
          font-size: 13px;
        }

        .modal-box {
          padding: 22px 14px;
          border-radius: 26px;
        }

        .modal-form input {
          height: 48px;
        }

        .toast-container {
          left: 12px;
          right: 12px;
          bottom: 82px;
        }
      }

      @media (max-height: 760px) and (min-width: 769px) {
        .teacher-wrap {
          padding-top: 112px;
          padding-bottom: 32px;
        }

        .student-access-card {
          min-height: 390px;
        }

        .games-table-scroll {
          max-height: 280px;
        }
      }
    </style>
  </head>

  <body>
    <main class="teacher-page">

      <header class="teacher-header">
        <a class="teacher-mobile-back" href="dashboard.php" aria-label="Назад в панель учителя">
          ‹
        </a>

        <a class="teacher-logo" href="../index.php" aria-label="На главную">
          <img src="../assets/img/logo.svg" alt="Логотип">
        </a>

        <nav class="teacher-nav">
          <a href="../index.php">Главная</a>
          <a href="dashboard.php" class="active">Панель учителя</a>
        </nav>

        <a class="teacher-header-btn" href="../logout.php">Выйти</a>
      </header>

      <div class="teacher-wrap">
        <div class="teacher-grid">

          <div class="student-title-row">
            <div class="student-title">
              <h1>Карточка ученика</h1>
              <p>Управление данными ученика, паролем, результатами и&nbsp;доступом к играм</p>
            </div>

            <div class="student-title-actions">
              <a class="orange-btn" href="dashboard.php">← Назад</a>
            </div>
          </div>

          <section class="student-main-card">
            <div class="card-title-row">
              <div>
                <h2>Информация об ученике</h2>
                <div class="card-subtitle">
                  Основные данные, пароль, результаты и&nbsp;удаление ученика
                </div>
              </div>

              <button class="collapse-btn" type="button" id="studentInfoToggle" onclick="toggleStudentInfo()">
                Свернуть
              </button>
            </div>

            <div class="student-info-body" id="studentInfoBody">
              <div class="student-head">
                <div>
                  <div class="student-name">
                    <strong><?= h($stu['display_name']) ?></strong>

                    <button class="icon-btn" type="button" onclick="openModal('editNameModal')" title="Редактировать имя">
                      ✏️
                    </button>
                  </div>

                  <div class="student-meta">
                    <div class="small">Логин: <?= h($stu['login']) ?></div>
                    <div class="small">Дата создания: <?= h($stu['created_at'] ?? '—') ?></div>
                  </div>
                </div>
              </div>

              <div class="actions-row">
                <button class="soft-btn" type="button" onclick="openModal('resetPasswordModal')">
                  Сбросить пароль
                </button>

                <button class="soft-btn" type="button" onclick="openModal('resultsModal')">
                  Результаты
                </button>

                <button class="danger-btn" type="button" onclick="openModal('deleteStudentModal')">
                  Удалить
                </button>
              </div>
            </div>
          </section>

          <section class="student-access-card">
            <div class="card-title-row">
              <div>
                <h2>Доступ к играм</h2>
                <div class="card-subtitle">
                  Выдача и закрытие доступа к&nbsp;развивающим играм
                </div>
              </div>

              <div class="games-actions">
                <form method="post" class="js-ajax-access-form">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="open_all_games">
                  <button class="soft-btn" type="submit">Открыть все</button>
                </form>

                <form method="post" class="js-ajax-access-form">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="action" value="close_all_games">
                  <button class="soft-btn" type="submit">Закрыть все</button>
                </form>
              </div>
            </div>

            <form method="post" class="js-ajax-access-form">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="save_access">

              <div class="games-table-scroll">
                <table class="games-table">
                  <thead>
                    <tr>
                      <th>Игра</th>
                      <th>Код</th>
                      <th>Доступ</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php foreach ($games as $g): ?>
                      <tr>
                        <td><?= h($g['title']) ?></td>
                        <td><?= h($g['code']) ?></td>
                        <td>
                          <label class="access-toggle">
                            <input type="checkbox" name="enabled[<?= (int) $g['id'] ?>]" <?= ((int) $g['is_enabled'] === 1) ? 'checked' : '' ?>>
                            <span class="access-pill"></span>
                          </label>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="save-row">
                <button class="orange-btn" type="submit">Сохранить</button>
              </div>
            </form>
          </section>

        </div>
      </div>

    </main>

    <div class="toast-container" id="toastContainer"></div>

    <?php if ($ok): ?>
      <script>
        window.addEventListener('DOMContentLoaded', function () {
          showToast(<?= json_encode($ok, JSON_UNESCAPED_UNICODE) ?>, 'success');
        });
      </script>
    <?php endif; ?>

    <?php if ($err): ?>
      <script>
        window.addEventListener('DOMContentLoaded', function () {
          showToast(<?= json_encode($err, JSON_UNESCAPED_UNICODE) ?>, 'error');
        });
      </script>
    <?php endif; ?>

    <div class="modal" id="editNameModal">
      <div class="modal-box">
        <div class="modal-top">
          <h2>Редактирование имени</h2>
          <button class="modal-close" type="button" onclick="closeModal('editNameModal')">×</button>
        </div>

        <form class="modal-form" method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="update_name">

          <p>
            <input name="display_name" value="<?= h($stu['display_name']) ?>" placeholder="Имя ученика" required>
          </p>

          <button class="orange-btn" type="submit">Сохранить</button>
        </form>
      </div>
    </div>

    <div class="modal" id="resetPasswordModal">
      <div class="modal-box">
        <div class="modal-top">
          <h2>Сброс пароля</h2>
          <button class="modal-close" type="button" onclick="closeModal('resetPasswordModal')">×</button>
        </div>

        <form class="modal-form" method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="reset_password">

          <p class="small">
            Введите новый временный пароль ученику. Если оставить поле пустым, пароль будет сгенерирован автоматически.
          </p>

          <p>
            <input name="new_password" placeholder="Новый временный пароль">
          </p>

          <button class="orange-btn" type="submit">Сбросить</button>
        </form>
      </div>
    </div>

    <div class="modal <?= $newPassword ? 'is-open' : '' ?>" id="newPasswordModal">
      <div class="modal-box">
        <div class="modal-top">
          <h2>Новый временный пароль</h2>
          <button class="modal-close" type="button" onclick="closeModal('newPasswordModal')">×</button>
        </div>

        <p class="small">
          Передайте этот пароль ученику. После обновления страницы он больше не будет показан.
        </p>

        <div class="temp-password-box"><?= h($newPassword) ?></div>

        <div class="actions-row">
          <button class="orange-btn" type="button" onclick="closeModal('newPasswordModal')">
            Понятно
          </button>
        </div>
      </div>
    </div>

    <div class="modal" id="resultsModal">
      <div class="modal-box">
        <div class="modal-top">
          <h2>Результаты ученика</h2>
          <button class="modal-close" type="button" onclick="closeModal('resultsModal')">×</button>
        </div>

        <?php if (!$hasResultsTable): ?>
          <p class="small muted-box">
            Таблица результатов <strong>game_result</strong> пока не найдена.
          </p>
        <?php elseif (!$openedGames): ?>
          <p class="small muted-box">
            У ученика нет открытых игр. Откройте доступ к играм, чтобы здесь появились кнопки результатов.
          </p>
        <?php else: ?>
          <p class="small">
            Выберите игру, чтобы посмотреть историю прохождения.
          </p>

          <div class="result-game-buttons">
            <?php foreach ($openedGames as $index => $game): ?>
              <button type="button" class="soft-btn result-game-btn <?= $index === 0 ? 'is-active' : '' ?>"
                onclick="showGameResults(<?= (int) $game['id'] ?>, this)">
                <?= h($game['title']) ?>
              </button>
            <?php endforeach; ?>
          </div>

          <?php foreach ($openedGames as $index => $game): ?>
            <?php
            $gameId = (int) $game['id'];
            $gameResults = $resultsByGame[$gameId] ?? [];
            ?>

            <div class="result-game-panel <?= $index === 0 ? 'is-active' : '' ?>" id="game-results-<?= $gameId ?>">
              <h3><?= h($game['title']) ?></h3>

              <?php if (!$gameResults): ?>
                <p class="small muted-box">
                  По этой игре пока нет сохранённых результатов.
                </p>
              <?php else: ?>
                <div class="result-table-wrap">
                  <table class="result-table">
                    <thead>
                      <tr>
                        <th>Правильные ответы</th>
                        <th>Неправильные ответы</th>
                        <th>Время</th>
                        <th>Когда закончил</th>
                      </tr>
                    </thead>

                    <tbody>
                      <?php foreach ($gameResults as $r): ?>
                        <tr>
                          <td><?= (int) $r['correct_answers'] ?></td>
                          <td><?= (int) $r['incorrect_answers'] ?></td>
                          <td><?= (int) $r['time_spent'] ?> сек.</td>
                          <td><?= h($r['completed_at']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="modal" id="deleteStudentModal">
      <div class="modal-box">
        <div class="modal-top">
          <h2>Удаление ученика</h2>
          <button class="modal-close" type="button" onclick="closeModal('deleteStudentModal')">×</button>
        </div>

        <p class="small">
          Вы действительно хотите удалить ученика
          <strong><?= h($stu['display_name']) ?></strong>?
        </p>

        <p class="small muted-box">
          Это действие нельзя отменить.
        </p>

        <form class="actions-row" method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="delete_student">

          <button class="danger-btn" type="submit">Удалить</button>

          <button class="soft-btn" type="button" onclick="closeModal('deleteStudentModal')">
            Отмена
          </button>
        </form>
      </div>
    </div>

    <script>
      function openModal(id) {
        document.getElementById(id).classList.add('is-open');
      }

      function closeModal(id) {
        document.getElementById(id).classList.remove('is-open');
      }

      function showGameResults(gameId, button) {
        document.querySelectorAll('.result-game-panel').forEach(function (panel) {
          panel.classList.remove('is-active');
        });

        document.querySelectorAll('.result-game-btn').forEach(function (btn) {
          btn.classList.remove('is-active');
        });

        const panel = document.getElementById('game-results-' + gameId);

        if (panel) {
          panel.classList.add('is-active');
        }

        if (button) {
          button.classList.add('is-active');
        }
      }

      function toggleStudentInfo() {
        const card = document.querySelector('.student-main-card');
        const body = document.getElementById('studentInfoBody');
        const button = document.getElementById('studentInfoToggle');

        body.classList.toggle('is-collapsed');
        card.classList.toggle('is-collapsed');

        if (body.classList.contains('is-collapsed')) {
          button.textContent = 'Развернуть';
        } else {
          button.textContent = 'Свернуть';
        }
      }

      function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');

        if (!container) {
          return;
        }

        const toast = document.createElement('div');
        toast.className = 'toast ' + (type === 'error' ? 'toast-error' : 'toast-success');
        toast.textContent = message;

        container.appendChild(toast);

        setTimeout(function () {
          toast.classList.add('is-hide');

          setTimeout(function () {
            toast.remove();
          }, 300);
        }, 3200);
      }

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          document.querySelectorAll('.modal.is-open').forEach(function (modal) {
            modal.classList.remove('is-open');
          });
        }
      });

      document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('click', function (event) {
          if (event.target === modal) {
            modal.classList.remove('is-open');
          }
        });
      });

      document.querySelectorAll('.js-ajax-access-form').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
          event.preventDefault();

          const submitButton = form.querySelector('button[type="submit"]');
          const formData = new FormData(form);
          const action = formData.get('action');

          if (submitButton) {
            submitButton.disabled = true;
            submitButton.style.opacity = '0.65';
          }

          try {
            const response = await fetch(window.location.href, {
              method: 'POST',
              body: formData,
              headers: {
                'X-Requested-With': 'XMLHttpRequest'
              }
            });

            const data = await response.json();

            if (!data.success) {
              showToast(data.message || 'Ошибка выполнения действия.', 'error');
              return;
            }

            if (action === 'open_all_games') {
              document.querySelectorAll('.access-toggle input[type="checkbox"]').forEach(function (checkbox) {
                checkbox.checked = true;
              });
            }

            if (action === 'close_all_games') {
              document.querySelectorAll('.access-toggle input[type="checkbox"]').forEach(function (checkbox) {
                checkbox.checked = false;
              });
            }

            showToast(data.message || 'Готово.', 'success');
          } catch (error) {
            showToast('Не удалось выполнить действие без перезагрузки страницы.', 'error');
          } finally {
            if (submitButton) {
              submitButton.disabled = false;
              submitButton.style.opacity = '1';
            }
          }
        });
      });
    </script>
  </body>

  </html>