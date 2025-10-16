<?php
// database.php - Configuración de la base de datos
class Database
{
    private static $connection = null;

    private static $dsn = 'mysql:host=localhost;dbname=test';
    private static $username = 'username';
    private static $password = 'password';
    // Configuración SQLite (más sencillo para desarrollo)
    private static $db_path = __DIR__ . '/webhooks.db';

    public static function getConnection()
    {

        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->safeLoad();

        self::$dsn = $_ENV['DB_DSN'] ?? self::$dsn;
        self::$username = $_ENV['DB_USERNAME'] ?? self::$username;
        self::$password = $_ENV['DB_PASSWORD'] ?? self::$password;

        if (self::$connection == null) {
            try {
                self::$connection = new PDO(self::$dsn, self::$username, self::$password);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                //self::initDatabase();
            } catch (PDOException $pex) {
                die("Error de conexión: " . $pex->getMessage());
            }
        }
        return self::$connection;
    }

    private static function initDatabase()
    {
        $sql = "CREATE TABLE IF NOT EXISTS webhook_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            method VARCHAR(10) NOT NULL,
            url TEXT NOT NULL,
            headers TEXT,
            body TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            content_type VARCHAR(100),
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        )";

        self::$connection->exec($sql);
    }

    public static function logWebhook($data)
    {
        $db = self::getConnection();
        $sql = "INSERT INTO webhook_logs (method, url, headers, body, ip_address, user_agent, content_type) 
                VALUES (:method, :url, :headers, :body, :ip_address, :user_agent, :content_type)";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':method' => $data['method'],
            ':url' => $data['url'],
            ':headers' => $data['headers'],
            ':body' => $data['body'],
            ':ip_address' => $data['ip_address'],
            ':user_agent' => $data['user_agent'],
            ':content_type' => $data['content_type']
        ]);

        return $db->lastInsertId();
    }

    public static function getWebhooks($limit = 50)
    {
        $db = self::getConnection();
        $sql = "SELECT * FROM webhook_logs ORDER BY timestamp DESC LIMIT :limit";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getWebhooksSince($timestamp)
    {
        $db = self::getConnection();
        $sql = "SELECT * FROM webhook_logs WHERE timestamp > :timestamp ORDER BY timestamp DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':timestamp' => $timestamp]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
