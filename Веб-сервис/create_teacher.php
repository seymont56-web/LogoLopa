<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';

$login = 'teacher';
$pass  = '12345';
$name  = 'Учитель';

$hash = password_hash($pass, PASSWORD_DEFAULT);

try {
  $stmt = $pdo->prepare("INSERT INTO users(role, login, pass_hash, display_name) VALUES('teacher', ?, ?, ?)");
  $stmt->execute([$login, $hash, $name]);
  echo "OK. Teacher created. login=$login pass=$pass. УДАЛИ create_teacher.php";
} catch (Throwable $e) {
  echo "Teacher already exists OR error: " . $e->getMessage();
}
