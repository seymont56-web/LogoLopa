<?php
session_start();

$dbHost = '127.0.0.1';
$dbPort = '3307';
$dbName = 'logop_games';
$dbUser = 'root';
$dbPass = '';

$adminPassword = 'admin123';

$allowedTables = ['users', 'games', 'student_game_access'];

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . htmlspecialchars($e->getMessage()));
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: {$url}");
    exit;
}

function getColumns(PDO $pdo, string $table): array {
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    return $stmt->fetchAll();
}

function getPrimaryKeys(PDO $pdo, string $table): array {
    $columns = getColumns($pdo, $table);
    $keys = [];
    foreach ($columns as $column) {
        if ($column['Key'] === 'PRI') {
            $keys[] = $column['Field'];
        }
    }
    return $keys;
}

function buildWhereByPrimaryKeys(array $primaryKeys, array $source, array &$params): string {
    $where = [];
    foreach ($primaryKeys as $key) {
        if (!isset($source[$key])) {
            die('Не передан первичный ключ: ' . h($key));
        }
        $param = ':pk_' . $key;
        $where[] = "`{$key}` = {$param}";
        $params[$param] = $source[$key];
    }
    return implode(' AND ', $where);
}

if (isset($_GET['logout'])) {
    session_destroy();
    redirect('admin.php');
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    if (hash_equals($adminPassword, $_POST['password'] ?? '')) {
        $_SESSION['admin_logged_in'] = true;
        redirect('admin.php');
    } else {
        $loginError = 'Неверный пароль';
    }
}

if (empty($_SESSION['admin_logged_in'])):
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Вход в админку</title>
</head>
<body>
    <h1>Вход в админку</h1>
    <?php if ($loginError): ?><p style="color:red;"><?= h($loginError) ?></p><?php endif; ?>
    <form method="post">
        <input type="hidden" name="admin_login" value="1">
        <label>Пароль: <input type="password" name="password" required></label>
        <button type="submit">Войти</button>
    </form>
</body>
</html>
<?php
exit;
endif;

$table = $_GET['table'] ?? $allowedTables[0];
if (!in_array($table, $allowedTables, true)) {
    $table = $allowedTables[0];
}

$columns = getColumns($pdo, $table);
$primaryKeys = getPrimaryKeys($pdo, $table);
$message = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'insert') {
            $fields = [];
            $placeholders = [];
            $params = [];

            foreach ($columns as $column) {
                $name = $column['Field'];
                $isAutoIncrement = str_contains($column['Extra'], 'auto_increment');
                if ($isAutoIncrement && ($_POST[$name] ?? '') === '') {
                    continue;
                }
                $fields[] = "`{$name}`";
                $placeholders[] = ':' . $name;
                $params[':' . $name] = $_POST[$name] ?? null;
            }

            $sql = "INSERT INTO `{$table}` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $message = 'Запись добавлена';
        }

        if ($action === 'update') {
            if (!$primaryKeys) {
                throw new RuntimeException('У таблицы нет первичного ключа, редактирование недоступно.');
            }

            $sets = [];
            $params = [];
            foreach ($columns as $column) {
                $name = $column['Field'];
                if (in_array($name, $primaryKeys, true)) {
                    continue;
                }
                $sets[] = "`{$name}` = :{$name}";
                $params[':' . $name] = $_POST[$name] ?? null;
            }

            $where = buildWhereByPrimaryKeys($primaryKeys, $_POST, $params);
            $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE {$where}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $message = 'Запись обновлена';
        }

        if ($action === 'delete') {
            if (!$primaryKeys) {
                throw new RuntimeException('У таблицы нет первичного ключа, удаление недоступно.');
            }

            $params = [];
            $where = buildWhereByPrimaryKeys($primaryKeys, $_POST, $params);
            $sql = "DELETE FROM `{$table}` WHERE {$where}";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $message = 'Запись удалена';
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$stmt = $pdo->query("SELECT * FROM `{$table}` LIMIT 200");
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Админка базы данных</title>
</head>
<body>
    <h1>Админка базы данных</h1>
    <p><a href="admin.php?logout=1">Выйти</a></p>

    <h2>Таблицы</h2>
    <nav>
        <?php foreach ($allowedTables as $allowedTable): ?>
            <a href="admin.php?table=<?= h($allowedTable) ?>"><?= h($allowedTable) ?></a>
            &nbsp;
        <?php endforeach; ?>
    </nav>

    <h2>Текущая таблица: <?= h($table) ?></h2>

    <?php if ($message): ?><p style="color:green;"><?= h($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p style="color:red;"><?= h($error) ?></p><?php endif; ?>

    <h3>Добавить запись</h3>
    <form method="post">
        <input type="hidden" name="action" value="insert">
        <table border="1" cellpadding="5">
            <?php foreach ($columns as $column): ?>
                <tr>
                    <td><?= h($column['Field']) ?></td>
                    <td>
                        <input
                            type="text"
                            name="<?= h($column['Field']) ?>"
                            placeholder="<?= h($column['Type']) ?><?= str_contains($column['Extra'], 'auto_increment') ? ' / можно пустым' : '' ?>"
                        >
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <button type="submit">Добавить</button>
    </form>

    <h3>Данные таблицы</h3>
    <table border="1" cellpadding="5">
        <tr>
            <?php foreach ($columns as $column): ?>
                <th><?= h($column['Field']) ?></th>
            <?php endforeach; ?>
            <th>Действия</th>
        </tr>
        <?php foreach ($rows as $row): ?>
            <tr>
                <form method="post">
                    <input type="hidden" name="action" value="update">
                    <?php foreach ($columns as $column): ?>
                        <?php $name = $column['Field']; ?>
                        <td>
                            <input
                                type="text"
                                name="<?= h($name) ?>"
                                value="<?= h($row[$name]) ?>"
                                <?= in_array($name, $primaryKeys, true) ? 'readonly' : '' ?>
                            >
                        </td>
                    <?php endforeach; ?>
                    <td>
                        <button type="submit">Сохранить</button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('Удалить запись?');">
                    <input type="hidden" name="action" value="delete">
                    <?php foreach ($primaryKeys as $key): ?>
                        <input type="hidden" name="<?= h($key) ?>" value="<?= h($row[$key]) ?>">
                    <?php endforeach; ?>
                    <button type="submit">Удалить</button>
                </form>
                    </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p>Показаны первые 200 записей.</p>
</body>
</html>
