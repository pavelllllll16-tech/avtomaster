<?php
/**
 * Подключение к MySQL через PDO (одно соединение на запрос).
 */
class Database
{
    /** @var PDO|null */
    private static $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $c = config('db');
            $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
            self::$pdo = new PDO($dsn, $c['user'], $c['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    /** Выполнить запрос с параметрами и вернуть PDOStatement */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @return array|null */
    public static function one(string $sql, array $params = [])
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** @return mixed */
    public static function value(string $sql, array $params = [])
    {
        return self::run($sql, $params)->fetchColumn();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int)self::connection()->lastInsertId();
    }

    /**
     * Выполнить функцию в транзакции: при исключении всё откатывается.
     * @return mixed результат функции
     */
    public static function transaction(callable $fn)
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
