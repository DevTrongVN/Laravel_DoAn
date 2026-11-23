<?php
declare(strict_types=1);

/** Kết nối PDO dùng chung và các thao tác SQL qua prepared statements. */
final class Database
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = require __DIR__ . '/../config/database.php';
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $config['port'], $config['dbname'], $config['charset']);
        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            error_log('Lỗi kết nối cơ sở dữ liệu: ' . $exception->getMessage());
            throw new RuntimeException('Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra cấu hình MySQL.', 0, $exception);
        }
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /** Trả PDO để model có thể thao tác trực tiếp khi cần. */
    public function getConnection(): PDO { return $this->pdo; }

    /** Chuẩn bị và thực thi truy vấn, trả về statement để đọc kết quả. */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    /** Lấy một dòng kết quả hoặc null nếu không có dữ liệu. */
    public function fetch(PDOStatement|string $query, array $params = []): ?array
    {
        $statement = $query instanceof PDOStatement ? $query : $this->query($query, $params);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    /** Lấy toàn bộ dòng kết quả. */
    public function fetchAll(PDOStatement|string $query, array $params = []): array
    {
        $statement = $query instanceof PDOStatement ? $query : $this->query($query, $params);
        return $statement->fetchAll();
    }

    /** Thực thi INSERT/UPDATE/DELETE; PDO ném exception nếu câu lệnh lỗi. */
    public function execute(string $sql, array $params = []): bool
    {
        $this->query($sql, $params);
        return true;
    }

    public function lastInsertId(): string|false { return $this->pdo->lastInsertId(); }
}
