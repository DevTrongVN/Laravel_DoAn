<?php
declare(strict_types=1);

/** Router GET/POST hỗ trợ URL sạch, tham số :name và controller/action. */
final class Router
{
    /** @var array<string, list<array{pattern:string,names:array,action:mixed}>> */
    private array $routes = [];

    public function get(string $url, mixed $action): void { $this->add('GET', $url, $action); }
    public function post(string $url, mixed $action): void { $this->add('POST', $url, $action); }
    public function delete(string $url, mixed $action): void { $this->add('DELETE', $url, $action); }

    public function add(string $method, string $url, mixed $action): void
    {
        $method = strtoupper($method);
        [$pattern, $names] = $this->compile($url);
        $this->routes[$method][] = ['pattern' => $pattern, 'names' => $names, 'action' => $action];
    }

    /** Biên dịch /post/:id thành regex và lưu tên tham số route. */
    private function compile(string $url): array
    {
        if ($url === '/' || trim($url, '/') === '') return ['#^/$#', []];
        $names = [];
        $quoted = preg_quote('/' . trim($url, '/'), '#');
        $pattern = preg_replace_callback('/\\\\:([A-Za-z_][A-Za-z0-9_]*)/', static function (array $match) use (&$names): string {
            $names[] = $match[1];
            return '([^/]+)';
        }, $quoted);
        return ['#^' . $pattern . '/?$#', $names];
    }

    /** Điều hướng theo URL request, hoặc nhận path/method trực tiếp để tương thích front controller. */
    public function dispatch(?string $url = null, ?string $method = null): void
    {
        $method = strtoupper($method ?? (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST') {
            $override = $_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;
            if ($override && in_array(strtoupper((string)$override), ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = strtoupper((string)$override);
            }
        }
        $path = $url ?? (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        if (isset($_GET['url'])) {
            $legacy = trim((string) $_GET['url']);
            if ($legacy !== '') {
                // Tách query string nếu tham số url vô tình chứa chuỗi truy vấn (ví dụ: route?status=pending)
                $parsed = parse_url($legacy);
                if (!empty($parsed['query'])) {
                    parse_str($parsed['query'], $extraParams);
                    foreach ($extraParams as $k => $v) {
                        if (!isset($_GET[$k])) {
                            $_GET[$k] = $v;
                        }
                    }
                }
                $path = '/' . ltrim($parsed['path'] ?? $legacy, '/');
            }
        }
        $path = rawurldecode((string) (parse_url($path, PHP_URL_PATH) ?: '/'));
        if (!isset($_GET['url'])) {
            $appConfig = require __DIR__ . '/../config/app.php';
            $basePath = rtrim((string)(parse_url($appConfig['app_url'], PHP_URL_PATH) ?: ''), '/');
            if ($basePath !== '' && str_starts_with($path, $basePath)) $path = substr($path, strlen($basePath)) ?: '/';
        }
        $path = '/' . trim($path, '/');
        if ($path === '//') $path = '/';

        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) continue;
            array_shift($matches);
            $params = [];
            foreach ($route['names'] as $index => $name) $params[$name] = $matches[$index] ?? null;
            $this->invoke($route['action'], array_values($params));
            return;
        }
        http_response_code(404);
        if (str_starts_with($path, '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Không tìm thấy API endpoint.'], JSON_UNESCAPED_UNICODE);
            return;
        }
        echo '<h2>404 - Không tìm thấy trang</h2>';
    }

    private function invoke(mixed $action, array $params): void
    {
        if (is_callable($action)) { $action(...$params); return; }
        if (is_array($action) && count($action) >= 2) {
            [$controllerName, $methodName] = $action;
        } elseif (is_string($action) && str_contains($action, '@')) {
            [$controllerName, $methodName] = explode('@', $action, 2);
        } else {
            throw new RuntimeException('Định nghĩa route không hợp lệ.');
        }
        if (!class_exists($controllerName) || !method_exists($controllerName, $methodName)) {
            throw new RuntimeException("Không tìm thấy controller/action: {$controllerName}::{$methodName}");
        }
        (new $controllerName())->{$methodName}(...$params);
    }
}
