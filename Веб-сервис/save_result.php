<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['uid'], $_SESSION['role']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Доступ запрещён'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Некорректные данные'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$studentId = (int)$_SESSION['uid'];
$gameId = (int)($data['game_id'] ?? 0);
$correctAnswers = (int)($data['correct_answers'] ?? 0);
$incorrectAnswers = (int)($data['incorrect_answers'] ?? 0);
$timeSpent = (int)($data['time_spent'] ?? 0);

if (
    $gameId <= 0 ||
    $correctAnswers < 0 ||
    $incorrectAnswers < 0 ||
    $timeSpent < 0
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка в переданных значениях'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $accessStmt = $pdo->prepare("
        SELECT is_enabled
        FROM student_game_access
        WHERE student_id = ? AND game_id = ?
        LIMIT 1
    ");

    $accessStmt->execute([$studentId, $gameId]);
    $access = $accessStmt->fetch();

    if (!$access || (int)$access['is_enabled'] !== 1) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Игра недоступна ученику'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO game_result
            (student_id, game_id, correct_answers, incorrect_answers, time_spent)
        VALUES
            (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $studentId,
        $gameId,
        $correctAnswers,
        $incorrectAnswers,
        $timeSpent
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Результат сохранён'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка сохранения результата'
    ], JSON_UNESCAPED_UNICODE);
}