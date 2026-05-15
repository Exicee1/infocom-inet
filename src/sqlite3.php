<?php
/**
 * Пример подключения к базе данных SQLite через PDO
 * 
 * @file      sqlite3.php
 * @author    Your Name <your.email@example.com>
 * @version   1.0
 * @date      $(date)
 */

namespace App\Database;

use PDO;
use PDOException;

/**
 * Класс для работы с базой данных SQLite через PDO
 *
 * @package App\Database
 */
class Database
{
    /**
     * Имя файла базы данных SQLite (в относительном или абсолютном пути)
     *
     * @var string
     */
    private $databasePath = __DIR__ . '/data/database.sqlite3';

    /**
     * Объект PDO для подключения к базе данных
     *
     * @var ?PDO
     */
    private $pdo;

    /**
     * Конструктор класса
     *
     * @param string|null $path Путь к файлу базы данных (по умолчанию используется default)
     */
    public function __construct(?string $path = null)
    {
        if ($path !== null) {
            $this->databasePath = $path;
        }

        // Создаем директорию для базы данных, если она не существует
        $dir = dirname($this->databasePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Получить объект PDO
     *
     * @return PDO|
     */
    public function getPDO()
    {
        if (!$this->pdo) {
            $this->connect();
        }
        return $this->pdo;
    }

    /**
     * Подключение к базе данных SQLite
     *
     * @throws PDOException
     */
    private function connect(): void
    {
        try {
            // Опции для подключения: DSN, драйвер, опции
            $dsn = 'sqlite:' . $this->databasePath;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Бросать исключения при ошибках
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Возвращать ассоциативные массивы
                PDO::ATTR_EMULATE_PREPARES => false, // Использовать реальные prepared statements
            ];

            $this->pdo = new PDO($dsn, null, null, $options);
        } catch (PDOException $e) {
            throw new PDOException(
                'Ошибка подключения к базе данных SQLite: ' . $e->getMessage(),
                $e->getCode()
            );
        }
    }

    /**
     * Выполнить SQL-запрос без возврата результатов
     *
     * @param string $sql SQL-запрос
     * @param array|null $params Массив параметров для prepared statement (опционально)
     *
     * @return bool|int Количество затронутых строк или результат INSERT/UPDATE/DELETE
     */
    public function execute(string $sql, ?array $params = null): int
    {
        try {
            return $this->getPDO()->exec($sql);
        } catch (PDOException $e) {
            error_log('Ошибка выполнения SQL: ' . $e->getMessage());
            throw new PDOException(
                'Не удалось выполнить запрос: ' . $e->getMessage(),
                $e->getCode()
            );
        }
    }

    /**
     * Выполнить SELECT-запрос и вернуть массив строк
     *
     * @param string $sql SQL-запрос
     * @param array|null $params Массив параметров для prepared statement (опционально)
     *
     * @return array|null Массив записей или null при ошибке
     */
    public function query(string $sql, ?array $params = null): ?array
    {
        try {
            $stmt = $this->getPDO()->prepare($sql);
            
            if ($params !== null) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Ошибка выполнения SQL: ' . $e->getMessage());
            throw new PDOException(
                'Не удалось выполнить запрос: ' . $e->getMessage(),
                $e->getCode()
            );
        }
    }

    /**
     * Получить одну строку из результата SELECT-запроса
     *
     * @param string $sql SQL-запрос
     * @param array|null $params Массив параметров для prepared statement (опционально)
     *
     * @return mixed|string Первая колонка первой строки или null при ошибке
     */
    public function fetchOne(string $sql, ?array $params = null): ?string
    {
        try {
            $stmt = $this->getPDO()->prepare($sql);
            
            if ($params !== null) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Ошибка выполнения SQL: ' . $e->getMessage());
            throw new PDOException(
                'Не удалось выполнить запрос: ' . $e->getMessage(),
                $e->getCode()
            );
        }
    }

    /**
     * Проверить, существует ли запись в таблице с заданными параметрами
     *
     * @param string $table Название таблицы
     * @param array $params Массив полей и их значений для проверки (например: ['id' => 1])
     *
     * @return bool Истина если запись существует, ложь иначе
     */
    public function exists(string $table, array $params = []): bool
    {
        // Построим динамический SQL-запрос для проверки существования записи
        $conditions = [];
        foreach ($params as $column => $value) {
            $conditions[] = "$column = :" . $column;
        }
        
        if (empty($conditions)) {
            return false;
        }

        $sql = "SELECT 1 FROM `$table` WHERE " . implode(' AND ', $conditions);
        
        try {
            $stmt = $this->getPDO()->prepare($sql);
            $stmt->execute(array_merge($params, array_keys($params)));
            
            return (bool) $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Ошибка проверки существования записи: ' . $e->getMessage());
            throw new PDOException(
                'Не удалось проверить запись: ' . $e->getMessage(),
                $e->getCode()
            );
        }
    }

    /**
     * Получить имя таблицы по умолчанию для работы с базой данных
     *
     * @return string Имя таблицы
     */
    public function getDefaultTable(): string
    {
        return 'users'; // По умолчанию таблица 'users'
    }
}

// Пример использования (для отладки)
if (php_sapi_name() === 'cli') {
    echo "SQLite3 Database Class Example\n";
    echo str_repeat('-', 50) . "\n\n";

    try {
        $db = new Database();
        
        // Проверить, существует ли база данных
        if (!file_exists($db->databasePath)) {
            echo "База данных не найдена. Создадим её...\n\n";
            
            // Создаем таблицу 'users' если она не существует
            $sql = "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            $db->execute($sql);
            echo "Таблица 'users' создана успешно.\n\n";
        } else {
            echo "База данных уже существует: {$db->databasePath}\n\n";
        }

        // Вставляем тестовые данные
        $testData = [
            ['Иван', 'ivan@example.com'],
            ['Мария', 'maria@example.com'],
            ['Петр', 'peter@example.com']
        ];

        foreach ($testData as $user) {
            $sql = "INSERT INTO users (name, email) VALUES (:name, :email)";
            $db->execute($sql, [
                ':name' => $user[0],
                ':email' => $user[1]
            ]);
        }

        echo "Данные успешно вставлены.\n\n";

        // Проверить все пользователи
        $users = $db->query("SELECT * FROM users ORDER BY id DESC");
        echo "Пользователи (отсортированные по ID, убывающе):\n";
        print_r($users);

        // Получить пользователя по email (с параметром)
        $email = 'ivan@example.com';
        $user = $db->fetchOne("SELECT name FROM users WHERE email = :email", [':email' => $email]);
        echo "\nПользователь с email '{$email}': {$user}\n";

        // Проверить существование записи
        if ($db->exists('users', ['name' => 'Иван'])) {
            echo "Запись 'Иван' существует в таблице users.\n";
        }

    } catch (Exception $e) {
        echo "Ошибка: " . $e->getMessage() . "\n";
    }
}
