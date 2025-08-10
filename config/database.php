<?php
// config/database.php - Configuración de base de datos para EcoDenuncia
// Jonathan Paul Zambrano Arriaga

class Database {
    // Detectar entorno automáticamente
    private $is_local;
    
    // Configuración para desarrollo (XAMPP)
    private $host_local = "localhost";
    private $db_name_local = "ecodenuncia_db";
    private $username_local = "root";
    private $password_local = "";
    
    // Configuración para producción (000webhost)
    private $host_prod = "localhost";
    private $db_name_prod = "id22091234_ecodenuncia"; // Cambiar por tu DB real
    private $username_prod = "id22091234_jonathan"; // Cambiar por tu usuario real
    private $password_prod = "TuPassword123!"; // Cambiar por tu password real
    
    public $conn;
    
    public function __construct() {
        // Detectar si estamos en desarrollo o producción
        $this->is_local = (
            $_SERVER['SERVER_NAME'] === 'localhost' || 
            $_SERVER['SERVER_NAME'] === '127.0.0.1' ||
            strpos($_SERVER['SERVER_NAME'], '.local') !== false
        );
    }
    
    /**
     * Obtener conexión a la base de datos
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            if ($this->is_local) {
                // Configuración para desarrollo (XAMPP)
                $this->conn = new PDO(
                    "mysql:host=" . $this->host_local . ";dbname=" . $this->db_name_local . ";charset=utf8mb4",
                    $this->username_local,
                    $this->password_local
                );
            } else {
                // Configuración para producción (000webhost)
                $this->conn = new PDO(
                    "mysql:host=" . $this->host_prod . ";dbname=" . $this->db_name_prod . ";charset=utf8mb4",
                    $this->username_prod,
                    $this->password_prod
                );
            }
            
            // Configurar PDO
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            // Log de conexión exitosa
            if (function_exists('logError')) {
                logError('Database connection established', array(
                    'environment' => $this->is_local ? 'development' : 'production',
                    'host' => $this->is_local ? $this->host_local : $this->host_prod,
                    'database' => $this->is_local ? $this->db_name_local : $this->db_name_prod
                ));
            }
            
        } catch(PDOException $exception) {
            // Log del error
            if (function_exists('logError')) {
                logError('Database connection failed', array(
                    'error' => $exception->getMessage(),
                    'environment' => $this->is_local ? 'development' : 'production'
                ));
            }
            
            // En desarrollo, mostrar el error. En producción, ocultar detalles
            if ($this->is_local) {
                die("Error de conexión: " . $exception->getMessage());
            } else {
                die("Error de conexión a la base de datos. Contacte al administrador.");
            }
        }
        
        return $this->conn;
    }
    
    /**
     * Cerrar conexión
     */
    public function closeConnection() {
        $this->conn = null;
    }
    
    /**
     * Verificar si las tablas existen
     */
    public function verificarTablas() {
        try {
            $tablas_requeridas = array('denuncias', 'comentarios');
            $tablas_existentes = array();
            
            foreach ($tablas_requeridas as $tabla) {
                $stmt = $this->conn->prepare("SHOW TABLES LIKE ?");
                $stmt->execute(array($tabla));
                
                if ($stmt->rowCount() > 0) {
                    $tablas_existentes[] = $tabla;
                }
            }
            
            return array(
                'requeridas' => $tablas_requeridas,
                'existentes' => $tablas_existentes,
                'faltantes' => array_diff($tablas_requeridas, $tablas_existentes),
                'todas_existen' => count($tablas_existentes) === count($tablas_requeridas)
            );
            
        } catch(PDOException $e) {
            return array(
                'error' => true,
                'mensaje' => $e->getMessage()
            );
        }
    }
    
    /**
     * Crear tablas si no existen (solo para desarrollo)
     */
    public function crearTablasDesarrollo() {
        if (!$this->is_local) {
            throw new Exception("Esta función solo está disponible en desarrollo");
        }
        
        try {
            // Crear tabla denuncias
            $sql_denuncias = "
                CREATE TABLE IF NOT EXISTS denuncias (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tipo_problema VARCHAR(100) NOT NULL,
                    descripcion TEXT NOT NULL,
                    ubicacion_direccion VARCHAR(255) NOT NULL,
                    ubicacion_lat DECIMAL(10, 8) NULL,
                    ubicacion_lng DECIMAL(11, 8) NULL,
                    imagen_url VARCHAR(255) NULL,
                    estado ENUM('pendiente', 'en_proceso', 'resuelta') DEFAULT 'pendiente',
                    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_fecha_creacion (fecha_creacion),
                    INDEX idx_estado (estado),
                    INDEX idx_tipo_problema (tipo_problema)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            // Crear tabla comentarios
            $sql_comentarios = "
                CREATE TABLE IF NOT EXISTS comentarios (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    denuncia_id INT NOT NULL,
                    nombre_usuario VARCHAR(100) NOT NULL,
                    comentario TEXT NOT NULL,
                    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (denuncia_id) REFERENCES denuncias(id) ON DELETE CASCADE,
                    INDEX idx_denuncia_id (denuncia_id),
                    INDEX idx_fecha_creacion (fecha_creacion)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $this->conn->exec($sql_denuncias);
            $this->conn->exec($sql_comentarios);
            
            return array(
                'success' => true,
                'mensaje' => 'Tablas creadas exitosamente'
            );
            
        } catch(PDOException $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Insertar datos de prueba (solo para desarrollo)
     */
    public function insertarDatosPrueba() {
        if (!$this->is_local) {
            throw new Exception("Esta función solo está disponible en desarrollo");
        }
        
        try {
            // Verificar si ya hay datos
            $stmt = $this->conn->query("SELECT COUNT(*) as count FROM denuncias");
            $count = $stmt->fetch()['count'];
            
            if ($count > 0) {
                return array(
                    'success' => true,
                    'mensaje' => 'Ya existen datos en la base de datos'
                );
            }
            
            // Insertar denuncias de ejemplo
            $denuncias_ejemplo = array(
                array(
                    'tipo_problema' => 'contaminacion_agua',
                    'descripcion' => 'Vertido de residuos industriales en el río. Se observa espuma y mal olor.',
                    'ubicacion' => 'Río Verde, Guayaquil, Ecuador',
                    'estado' => 'pendiente'
                ),
                array(
                    'tipo_problema' => 'basura_acumulada',
                    'descripcion' => 'Gran acumulación de basura en espacio público afectando la salud de los habitantes.',
                    'ubicacion' => 'Mercado Central, Quito, Ecuador',
                    'estado' => 'en_proceso'
                ),
                array(
                    'tipo_problema' => 'contaminacion_aire',
                    'descripcion' => 'Fábrica emitiendo humos tóxicos sin control, afectando barrios cercanos.',
                    'ubicacion' => 'Zona Industrial, Cuenca, Ecuador',
                    'estado' => 'pendiente'
                )
            );
            
            $sql = "INSERT INTO denuncias (tipo_problema, descripcion, ubicacion_direccion, estado, fecha_creacion) 
                    VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 7) DAY))";
            
            $stmt = $this->conn->prepare($sql);
            
            foreach ($denuncias_ejemplo as $denuncia) {
                $stmt->execute(array(
                    $denuncia['tipo_problema'],
                    $denuncia['descripcion'],
                    $denuncia['ubicacion'],
                    $denuncia['estado']
                ));
            }
            
            // Insertar algunos comentarios de ejemplo
            $comentarios_ejemplo = array(
                array(1, 'María González', 'Esta situación requiere atención inmediata de las autoridades ambientales.'),
                array(1, 'Carlos Pérez', 'He notado el mismo problema en la zona. Es urgente tomar medidas.'),
                array(2, 'Ana López', 'Excelente reporte. La basura afecta a toda la comunidad.'),
                array(3, 'Jonathan Zambrano', 'Situación preocupante que debe ser investigada a fondo.')
            );
            
            $sql_comentarios = "INSERT INTO comentarios (denuncia_id, nombre_usuario, comentario) VALUES (?, ?, ?)";
            $stmt_comentarios = $this->conn->prepare($sql_comentarios);
            
            foreach ($comentarios_ejemplo as $comentario) {
                $stmt_comentarios->execute($comentario);
            }
            
            return array(
                'success' => true,
                'mensaje' => 'Datos de prueba insertados exitosamente'
            );
            
        } catch(PDOException $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
}
?>
