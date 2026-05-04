<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

const SESSION_TIMEOUT = 3600;
const REMEMBER_TIMEOUT = 60 * 60 * 24 * 30;

function is_logged_in(): bool {
  return isset($_SESSION['uid'], $_SESSION['role']);
}

function check_session_timeout(): void {
  if (!is_logged_in()) {
    return;
  }

  $now = time();

  $timeout = !empty($_SESSION['remember'])
    ? REMEMBER_TIMEOUT
    : SESSION_TIMEOUT;

  if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > $timeout) {
    logout();

    header('Location: ../login.php');
    exit;
  }

  $_SESSION['last_activity'] = $now;
}


function require_role(string $role): void {
  if (!is_logged_in()) {
    header('Location: ../login.php');
    exit;
  }
  
  check_session_timeout();
  $currentRole = $_SESSION['role'] ?? null;

  if ($currentRole !== $role) {
    if ($currentRole === 'student') {
      header('Location: ../student/home.php');
      exit;
    }

    if ($currentRole === 'teacher') {
      header('Location: ../teacher/dashboard.php');
      exit;
    }

    header('Location: ../login.php');
    exit;
  }
}

function redirect_by_role(): void {
  if (!is_logged_in()) return;

  check_session_timeout();

  if ($_SESSION['role'] === 'teacher') {
    header('Location: teacher/dashboard.php');
    exit;
  }

  if ($_SESSION['role'] === 'student') {
    header('Location: student/home.php');
    exit;
  }

  header('Location: login.php');
  exit;
}

function logout(): void {
  $_SESSION = [];

  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
      session_name(),
      '',
      time() - 42000,
      $params['path'],
      $params['domain'],
      $params['secure'],
      $params['httponly']
    );
  }

  session_destroy();
}