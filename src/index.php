<?php 

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

// =====================================================================
//                    1. КРИПТО-ФУНКЦИИ (AES-256-GCM)
// =====================================================================

/**
 * Шифрование AES-256-GCM (AEAD).
 * Формат payload: base64( IV(12) | TAG(16) | CIPHERTEXT )
 */
function encryptModern(string $plaintext, string $key, string $aad = ''): string
{
    $cipher   = 'aes-256-gcm';
    $ivLength = openssl_cipher_iv_length($cipher); // 12 байт — стандарт NIST для GCM
    $iv       = random_bytes($ivLength);
    $tag      = '';

    $ciphertext = openssl_encrypt(
        $plaintext,
        $cipher,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        $aad,
        16
    );

    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed: ' . openssl_error_string());
    }

    return base64_encode($iv . $tag . $ciphertext);
}

/**
 * Расшифровка. Бросает исключение, если данные подделаны, повреждены или ключ неверный.
 */
function decryptModern(string $base64Data, string $key, string $aad = ''): string
{
    $cipher    = 'aes-256-gcm';
    $ivLength  = openssl_cipher_iv_length($cipher);
    $tagLength = 16;

    $data = base64_decode($base64Data, true);
    if ($data === false || strlen($data) < $ivLength + $tagLength) {
        throw new RuntimeException('Повреждённый payload');
    }

    $iv         = substr($data, 0, $ivLength);
    $tag        = substr($data, $ivLength, $tagLength);
    $ciphertext = substr($data, $ivLength + $tagLength);

    $plaintext = openssl_decrypt(
        $ciphertext,
        $cipher,
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        $aad
    );

    if ($plaintext === false) {
        throw new RuntimeException('Не удалось расшифровать или проверить целостность');
    }

    return $plaintext;
}

// =====================================================================
//                    2. ИНИЦИАЛИЗАЦИЯ КЛЮЧА И БД
// =====================================================================

$dbPath = getenv('DB_DATABASE') ?: '/var/www/html/data/app.sqlite';
$dbKey  = getenv('SQLCIPHER_KEY') ?: '';

// Ключ AES — отдельный от ключа SQLCipher! Берём из окружения.
// В проде хранить в KMS/Vault. Здесь — для демо генерируем из пароля + соли.
$appPassword = getenv('APP_ENC_PASSWORD') ?: 'user-strong-password';
$appSalt     = getenv('APP_ENC_SALT')     ?: 'demo-salt-16bytes';
$appKey      = hash_pbkdf2('sha256', $appPassword, $appSalt, 600_000, 32, true);

$dir = dirname($dbPath);
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // SQLCipher unlock — должен идти ДО любого другого SQL.
    if ($dbKey !== '') {
        $pdo->exec('PRAGMA key = ' . $pdo->quote($dbKey));
        $pdo->exec('PRAGMA cipher_compatibility = 4');
    }

    // Таблица под зашифрованные сообщения
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS secret_messages (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            context     TEXT    NOT NULL,
            ciphertext  TEXT    NOT NULL,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
        )
    SQL);

    // =================================================================
    //                3. ПОЛУЧАЕМ СООБЩЕНИЕ → ШИФРУЕМ → ПИШЕМ
    // =================================================================

    $plaintext = $_POST['message']
        ?? 'Конфиденциальная информация № ' . bin2hex(random_bytes(4));
    $context   = 'user_id:99'; // AAD — привязка к контексту

    $encrypted = encryptModern($plaintext, $appKey, $context);


    $insert = $pdo->prepare(
        'INSERT INTO secret_messages (context, ciphertext) VALUES (:ctx, :ct)'
    );
    $insert->execute([':ctx' => $context, ':ct' => $encrypted]);
    $newId = (int) $pdo->lastInsertId();

    // =================================================================
    //                4. ЧИТАЕМ ИЗ БД И РАСШИФРОВЫВАЕМ
    // =================================================================

    $select = $pdo->prepare(
        'SELECT id, context, ciphertext, created_at
           FROM secret_messages
          WHERE id = :id'
    );
    $select->execute([':id' => $newId]);
    $row = $select->fetch();

    if ($row === false) {
        throw new RuntimeException('Запись не найдена');
    }

    $decrypted = decryptModern($row['ciphertext'], $appKey, $row['context']);

    // Несколько последних записей — для наглядности
    $last = $pdo->query(
        'SELECT id, context, ciphertext, created_at
           FROM secret_messages
       ORDER BY id DESC LIMIT 5'
    )->fetchAll();

    $cipherInfo = $dbKey !== ''
        ? ($pdo->query('PRAGMA cipher_version')->fetchColumn() ?: 'unknown')
        : 'plaintext sqlite';

} catch (Throwable $e) {
    http_response_code(500);
    echo '<pre>', htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8'), '</pre>';
    exit;
}

// =====================================================================
//                          5. ОТРИСОВКА
// =====================================================================

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>AES-256-GCM ↔️ SQLite demo</title>
    <style>
        body  { font-family: system-ui, sans-serif; max-width: 920px; margin: 2rem auto; padding: 0 1rem; color:#222; }
        h1    { font-size: 1.4rem; }
        .card { border:1px solid #ddd; border-radius:8px; padding:1rem; margin:.8rem 0; }
        .ok   { background:#eaffea; border-color:#9fdc9f; }
        .ct   { background:#fff8e0; border-color:#e6cf73; word-break:break-all; font-family: ui-monospace, monospace; font-size:.85rem; }
        table { width:100%; border-collapse: collapse; font-size:.85rem; }
        th,td { border-bottom:1px solid #eee; padding:.4rem; text-align:left; vertical-align:top; }
        th    { background:#f6f6f6; }
        code  { background:#f3f3f3; padding:.1rem .3rem; border-radius:3px; }
        form  { margin: 1rem 0; }
        input[type=text] { width:70%; padding:.4rem; }
        button{ padding:.4rem .9rem; }
    </style>
</head>
<body>

<h1>AES-256-GCM → SQLite → расшифровка</h1>

<p>
    БД: <code><?= $h($dbPath) ?></code> &nbsp;|&nbsp;
    Слой хранения: <code><?= $h((string)$cipherInfo) ?></code> &nbsp;|&nbsp;
    Алгоритм поля: <code>AES-256-GCM (AEAD)</code>
</p>

<form method="post">
    <input type="text" name="message" placeholder="Введите сообщение для шифрования">
    <button type="submit">Зашифровать и сохранить</button>
</form>

<div class="card">
    <strong>1. Открытый текст (вход):</strong>
    <div><?= $h($plaintext) ?></div>
</div>

<div class="card ct">
    <strong>2. Что фактически лежит в БД (base64 IV|TAG|CT):</strong>
    <div><?= $h($encrypted) ?></div>
</div>

<div class="card ok">
    <strong>3. Расшифровано после SELECT (id=<?= $newId ?>):</strong>
    <div><?= $h($decrypted) ?></div>
    <small>
        Совпадает с исходным:
        <?= hash_equals($plaintext, $decrypted) ? '✅ да' : '❌ нет' ?>
    </small>
</div>


<h2>Последние 5 записей в БД</h2>
<table>
    <thead>
        <tr>
            <th>id</th><th>context (AAD)</th><th>ciphertext (хранится так)</th>
            <th>после decrypt</th><th>created_at</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($last as $r): ?>
        <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= $h($r['context']) ?></td>
            <td style="font-family:ui-monospace,monospace;font-size:.75rem;word-break:break-all;max-width:340px;">
                <?= $h($r['ciphertext']) ?>
            </td>
            <td>
                <?php
                try {
                    echo $h(decryptModern($r['ciphertext'], $appKey, $r['context']));
                } catch (Throwable $e) {
                    echo '<span style="color:#b00">ошибка: ', $h($e->getMessage()), '</span>';
                }
                ?>
            </td>
            <td><?= $h($r['created_at']) ?></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>

<p style="margin-top:2rem;font-size:.85rem;color:#666">
    Замечание: при подмене любого байта в <code>ciphertext</code> или смене <code>context</code>
    расшифровка вернёт исключение — это и есть гарантия целостности AEAD-режима.
</p>

</body>
</html>