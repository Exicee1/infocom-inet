<?php

function encryptAES($data, $key) {
    // Генерация случайного вектора инициализации
    $iv = random_bytes(16);
    
    // Шифруем
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    
    // Вычисляем HMAC
    $hmac = hash_hmac('sha256', $encrypted, $key, true);
    
    // Склеиваем: IV (16) + HMAC (32) + шифротекст. Итог кодируем в Base64.
    return base64_encode($iv . $hmac . $encrypted);
}

function decryptAES($data, $key) {
    $data = base64_decode($data);
    
    // Извлекаем части
    $iv        = substr($data, 0, 16);
    $hmac      = substr($data, 16, 32);
    $encrypted = substr($data, 48);
    
    // Пересчитываем HMAC для проверки целостности
    $calculatedHmac = hash_hmac('sha256', $encrypted, $key, true);
    
    // Безопасное сравнение — защита от timing-атак
    if (!hash_equals($hmac, $calculatedHmac)) {
        return false; // Ключ неверный или данные повреждены
    }
    
    // Расшифровываем
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted;
}

// --- Использование ---
$key  = 'your-256-bit-secret-key-32bytes!!'; // 32 байта!
$data = 'Секретные данные клиента';

$encrypted = encryptAES($data, $key);
echo "Зашифровано: " . $encrypted . PHP_EOL;

$decrypted = decryptAES($encrypted, $key);
echo "Расшифровано: " . $decrypted . PHP_EOL;


// ======================================

function encryptAesGcm($plaintext, $key) {
    $cipher = 'aes-256-gcm';
    
    // Генерируем IV и тег
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv    = openssl_random_pseudo_bytes($ivlen);
    $tag   = null;
    
    // Дополнительные ассоциированные данные (AAD) — не шифруются, но влияют на tag
    $aad   = 'user_id_12345';
    
    // Шифруем (tag будет записан через ссылку)
    $ciphertext = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
    
    // Возвращаем всё в Base64 (для удобства хранения в БД или JSON)
    return base64_encode($iv . $tag . $ciphertext);
}

function decryptAesGcm($combinedCipher, $key) {
    $cipher = 'aes-256-gcm';
    $data   = base64_decode($combinedCipher);
    
    // Извлекаем компоненты
    $ivlen      = openssl_cipher_iv_length($cipher);
    $iv         = substr($data, 0, $ivlen);
    $tag        = substr($data, $ivlen, 16);
    $ciphertext = substr($data, $ivlen + 16);
    
    $aad = 'user_id_12345';
    
    // При расшифровке если tag не совпадает, вернётся false
    $originalPlaintext = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);
    return $originalPlaintext;
}

// --- Использование ---
$key   = random_bytes(32); // Используйте криптографически безопасный генератор
$plain = "Тестовый платёж";

$enc = encryptAesGcm($plain, $key);
echo "GCM зашифровано: " . $enc . PHP_EOL;

$dec = decryptAesGcm($enc, $key);
echo "GCM расшифровано: " . ($dec !== false ? $dec : "Ошибка!") . PHP_EOL;