<?php
/**
 * Общие вспомогательные функции.
 */

/** Ошибка, текст которой можно показать пользователю */
class AppException extends Exception {}

function config(string $section)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config[$section] ?? null;
}

/** Экранирование для вывода в HTML (защита от XSS) */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return number_format((float)$value, 2, ',', ' ') . ' ' . config('app')['currency'];
}

/** Наценка в процентах; при нулевой закупочной цене — null */
function markup_percent($purchase, $selling)
{
    $purchase = (float)$purchase;
    return $purchase > 0 ? round(((float)$selling - $purchase) / $purchase * 100, 2) : null;
}

// ---------- CSRF ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (!hash_equals(csrf_token(), (string)$token)) {
        throw new AppException('Сессия устарела. Обновите страницу.');
    }
}

// ---------- Чтение и проверка входных данных ----------

function input_str(string $key, int $maxLen = 255, bool $required = false, string $label = ''): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    if ($required && $value === '') {
        throw new AppException('Заполните поле «' . ($label ?: $key) . '»');
    }
    if (mb_strlen($value) > $maxLen) {
        throw new AppException('Поле «' . ($label ?: $key) . "» длиннее $maxLen символов");
    }
    return $value;
}

function input_int(string $key, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX, string $label = ''): int
{
    $raw = $_POST[$key] ?? '';
    if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
        throw new AppException('Некорректное значение поля «' . ($label ?: $key) . '»');
    }
    $value = (int)$raw;
    if ($value < $min || $value > $max) {
        throw new AppException('Поле «' . ($label ?: $key) . "» должно быть от $min до $max");
    }
    return $value;
}

/** Необязательный id: пустая строка → null */
function input_id_or_null(string $key)
{
    $raw = $_POST[$key] ?? '';
    return ($raw === '' || $raw === null) ? null : input_int($key, 1);
}

function input_float(string $key, float $min = 0, float $max = 1e9, string $label = ''): float
{
    $raw = str_replace(',', '.', (string)($_POST[$key] ?? ''));
    if (!is_numeric($raw)) {
        throw new AppException('Некорректное число в поле «' . ($label ?: $key) . '»');
    }
    $value = round((float)$raw, 2);
    if ($value < $min || $value > $max) {
        throw new AppException('Поле «' . ($label ?: $key) . "» должно быть от $min до $max");
    }
    return $value;
}

function input_date(string $key, string $label = ''): string
{
    $raw = (string)($_POST[$key] ?? '');
    $d = DateTime::createFromFormat('Y-m-d', $raw);
    if (!$d || $d->format('Y-m-d') !== $raw) {
        throw new AppException('Некорректная дата в поле «' . ($label ?: $key) . '»');
    }
    return $raw;
}

function query_date(string $key, string $default): string
{
    $raw = (string)($_GET[$key] ?? $_POST[$key] ?? '');
    $d = DateTime::createFromFormat('Y-m-d', $raw);
    return ($d && $d->format('Y-m-d') === $raw) ? $raw : $default;
}

// ---------- JSON API ----------

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Единая точка обработки AJAX-запросов.
 * $handlers = ['action' => function () { return [...]; }, ...]
 */
function api_dispatch(array $handlers): void
{
    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['ok' => false, 'message' => 'Метод не поддерживается'], 405);
        }
        csrf_check();

        $action = $_POST['action'] ?? '';
        if (!isset($handlers[$action])) {
            json_response(['ok' => false, 'message' => 'Неизвестное действие'], 400);
        }

        $result = $handlers[$action]();
        json_response(array_merge(['ok' => true], is_array($result) ? $result : []));
    } catch (AppException $e) {
        json_response(['ok' => false, 'message' => $e->getMessage()], 422);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $msg = 'Ошибка базы данных';
        if ($e->getCode() === '23000') {
            $msg = 'Операция нарушает целостность данных (запись используется или уже существует)';
        } elseif (config('app')['debug']) {
            $msg .= ': ' . $e->getMessage();
        }
        json_response(['ok' => false, 'message' => $msg], 500);
    } catch (Throwable $e) {
        error_log((string)$e);
        json_response(['ok' => false, 'message' => config('app')['debug'] ? $e->getMessage() : 'Внутренняя ошибка'], 500);
    }
}
