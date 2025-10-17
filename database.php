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

        // Cargar variables de entorno si la librería dotenv está disponible
        if (class_exists('Dotenv\Dotenv')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
            $dotenv->safeLoad();
        }

        self::$dsn = $_ENV['DB_DSN'] ?? null;
        self::$username = $_ENV['DB_USERNAME'] ?? self::$username;
        self::$password = $_ENV['DB_PASSWORD'] ?? self::$password;

        // Si no hay DSN en entorno, usar SQLite por defecto con el archivo webhooks.db
        if (empty(self::$dsn)) {
            self::$dsn = 'sqlite:' . self::$db_path;
            // Para SQLite no usamos usuario/contraseña
            self::$username = null;
            self::$password = null;
        }

        if (self::$connection == null) {
            try {
                self::$connection = new PDO(self::$dsn, self::$username, self::$password);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // Inicializar esquema (crea tablas si no existen)
                self::initDatabase();
            } catch (PDOException $pex) {
                die("Error de conexión: " . $pex->getMessage());
            }
        }
        return self::$connection;
    }

    private static function initDatabase()
    {
        // DDL compatible con MySQL y SQLite. Se crea la tabla si no existe y se asegura la columna endpoint_token
        $driver = self::$connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS webhook_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                method VARCHAR(10) NOT NULL,
                url TEXT NOT NULL,
                headers TEXT,
                body TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                content_type VARCHAR(100),
                endpoint_token VARCHAR(255),
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
            self::$connection->exec($sql);
            // Tabla para endpoints/tokens
            $sql2 = "CREATE TABLE IF NOT EXISTS endpoints (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token VARCHAR(255) UNIQUE,
                label TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
            self::$connection->exec($sql2);
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS webhook_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                method VARCHAR(10) NOT NULL,
                url TEXT NOT NULL,
                headers TEXT,
                body TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                content_type VARCHAR(100),
                endpoint_token VARCHAR(255),
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
            self::$connection->exec($sql);

            // Asegurarnos que la columna endpoint_token existe (por si la tabla se creó antes)
            try {
                $colCheck = self::$connection->query("SHOW COLUMNS FROM webhook_logs LIKE 'endpoint_token'");
                $exists = $colCheck && $colCheck->rowCount() > 0;
            } catch (Exception $e) {
                $exists = false;
            }

            if (!$exists) {
                try {
                    self::$connection->exec("ALTER TABLE webhook_logs ADD COLUMN endpoint_token VARCHAR(255)");
                } catch (Exception $e) {
                    // Si no es posible (por permisos) simplemente ignoramos
                }
            }
            // Crear tabla endpoints en MySQL si no existe
            try {
                $sql2 = "CREATE TABLE IF NOT EXISTS endpoints (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    token VARCHAR(255) UNIQUE,
                    label TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )";
                self::$connection->exec($sql2);
            } catch (Exception $e) {
                // ignorar errores de creación
            }
        }
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

    // Nueva versión que soporta endpoint_token si se pasa
    public static function logWebhookWithToken($data)
    {
        $db = self::getConnection();

        // Intentar insertar incluyendo endpoint_token (la columna puede existir o no)
        $sql = "INSERT INTO webhook_logs (method, url, headers, body, ip_address, user_agent, content_type, endpoint_token) 
                VALUES (:method, :url, :headers, :body, :ip_address, :user_agent, :content_type, :endpoint_token)";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':method' => $data['method'],
            ':url' => $data['url'],
            ':headers' => $data['headers'],
            ':body' => $data['body'],
            ':ip_address' => $data['ip_address'],
            ':user_agent' => $data['user_agent'],
            ':content_type' => $data['content_type'],
            ':endpoint_token' => $data['endpoint_token'] ?? null
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

    // Nuevos métodos con filtro por endpoint_token
    public static function getWebhooksByToken($token, $limit = 50)
    {
        $db = self::getConnection();
        $sql = "SELECT * FROM webhook_logs WHERE endpoint_token = :token ORDER BY timestamp DESC LIMIT :limit";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getWebhooksSinceByToken($token, $timestamp)
    {
        $db = self::getConnection();
        $sql = "SELECT * FROM webhook_logs WHERE endpoint_token = :token AND timestamp > :timestamp ORDER BY timestamp DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute([':token' => $token, ':timestamp' => $timestamp]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // CRUD para endpoints/tokens
    public static function listEndpoints()
    {
        $db = self::getConnection();
        $sql = "SELECT * FROM endpoints ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function createEndpoint($token, $label = null)
    {
        $db = self::getConnection();
        $sql = "INSERT INTO endpoints (token, label) VALUES (:token, :label)";
        $stmt = $db->prepare($sql);
        $stmt->execute([':token' => $token, ':label' => $label]);
        return $db->lastInsertId();
    }

    public static function deleteEndpointById($id)
    {
        $db = self::getConnection();
        $sql = "DELETE FROM endpoints WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public static function deleteEndpointByToken($token)
    {
        $db = self::getConnection();
        $sql = "DELETE FROM endpoints WHERE token = :token";
        $stmt = $db->prepare($sql);
        return $stmt->execute([':token' => $token]);
    }
}
