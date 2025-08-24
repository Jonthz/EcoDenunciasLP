<?php
// config/database.php - Configuración de base de datos para EcoDenuncia
// Jonathan Paul Zambrano Arriaga

class Database {
    // Detectar entorno automáticamente
    private $is_local;
    
    // Configuración para desarrollo (XAMPP)
    private $host_local = "127.0.0.1"; // Usamos explícito para evitar resoluciones distintas
    private $port_local = 3307; // Puerto personalizado de MySQL en XAMPP
    private $db_name_local = "ecodenuncia_db";
    private $username_local = "root";
    private $password_local = ""; // Cambiar si tu XAMPP tiene contraseña
    
    // Configuración para producción (000webhost)
    private $host_prod = "localhost";
    private $db_name_prod = "id22091234_ecodenuncia"; // Cambiar por tu DB real
    private $username_prod = "id22091234_jonathan"; // Cambiar por tu usuario real
    private $password_prod = "TuPassword123!"; // Cambiar por tu password real
    
    public $conn;
    private $last_error = null;
    
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
    $this->last_error = null;
        
        try {
            if ($this->is_local) {
                // Intentos (orden de prioridad) usando puerto real primero
                $intentos = [
                    [ 'host' => $this->host_local, 'port' => $this->port_local, 'user' => $this->username_local, 'pass' => $this->password_local ],
                    [ 'host' => 'localhost',        'port' => $this->port_local, 'user' => $this->username_local, 'pass' => $this->password_local ],
                    [ 'host' => $this->host_local, 'port' => $this->port_local, 'user' => $this->username_local, 'pass' => 'password' ],
                    // Fallbacks a puerto estándar por si el usuario lo cambia luego
                    [ 'host' => $this->host_local, 'port' => 3307, 'user' => $this->username_local, 'pass' => $this->password_local ],
                    [ 'host' => 'localhost',       'port' => 3307, 'user' => $this->username_local, 'pass' => $this->password_local ],
                ];

                $ultimo_error = '';
                foreach ($intentos as $cfg) {
                    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$this->db_name_local};charset=utf8mb4";
                    try {
                        $this->conn = new PDO(
                            $dsn,
                            $cfg['user'],
                            $cfg['pass'],
                            [
                                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                                PDO::ATTR_TIMEOUT => 5
                            ]
                        );
                        break; // Exito
                    } catch(PDOException $e) {
                        $ultimo_error = $e->getMessage();
                        continue;
                    }
                }
                if (!$this->conn) {
                    throw new PDOException("No se pudo conectar (probados puertos 3307/3306). Último error: " . $ultimo_error);
                }
                
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
                    'host' => $this->is_local ? ($this->host_local . ':' . $this->port_local) : $this->host_prod,
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
            
            // Guardar error y lanzar excepción para que el caller lo maneje
            $this->last_error = $exception->getMessage();
            throw $exception;
        }
        
        return $this->conn;
    }

    /**
     * Obtener último error de conexión
     */
    public function getLastError() {
        return $this->last_error;
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
     * Diagnóstico de conexión (solo para desarrollo)
     */
    public function diagnosticarConexion() {
        if (!$this->is_local) {
            return array('error' => 'Diagnóstico solo disponible en desarrollo');
        }
        
        $resultados = array();
        
        // Verificar si MySQL está corriendo
        $mysql_corriendo = false;
        if (function_exists('mysqli_connect')) {
            $test_conn = @mysqli_connect($this->host_local, $this->username_local, $this->password_local, null, $this->port_local);
            if ($test_conn) {
                $mysql_corriendo = true;
                mysqli_close($test_conn);
            }
        }
        
        $resultados['mysql_running'] = $mysql_corriendo;
        
        // Probar configuraciones (incluye puerto 3307 prioritario)
        $configuraciones_test = [
            '127.0.0.1_3307_sin_password' => [$this->host_local, $this->port_local, $this->username_local, $this->password_local],
            'localhost_3307_sin_password' => ['localhost', $this->port_local, $this->username_local, $this->password_local],
            '127.0.0.1_3307_con_password' => [$this->host_local, $this->port_local, $this->username_local, 'password'],
            '127.0.0.1_3306_sin_password' => [$this->host_local, 3306, $this->username_local, $this->password_local],
        ];
        
        foreach ($configuraciones_test as $nombre => $cfg) {
            try {
                $dsn = "mysql:host={$cfg[0]};port={$cfg[1]};charset=utf8mb4";
                $test_pdo = new PDO($dsn, $cfg[2], $cfg[3], [PDO::ATTR_TIMEOUT => 3]);
                $resultados[$nombre] = 'EXITOSO';
                $test_pdo = null;
            } catch(PDOException $e) {
                $resultados[$nombre] = 'ERROR: ' . $e->getMessage();
            }
        }
        
        // Verificar si la base de datos existe
        try {
            $test_pdo = new PDO("mysql:host={$this->host_local};port={$this->port_local};charset=utf8mb4", $this->username_local, $this->password_local);
            $stmt = $test_pdo->query("SHOW DATABASES LIKE 'ecodenuncia_db'");
            $resultados['database_exists'] = $stmt->rowCount() > 0 ? 'SÍ' : 'NO';
            $test_pdo = null;
        } catch(PDOException $e) {
            $resultados['database_exists'] = 'ERROR: ' . $e->getMessage();
        }
        
        return $resultados;
    }
    
    /**
     * Crear base de datos si no existe (solo desarrollo)
     */
    public function crearBaseDatos() {
        if (!$this->is_local) {
            throw new Exception("Esta función solo está disponible en desarrollo");
        }
        
        try {
            // Conectar sin especificar base de datos
            $conn = new PDO("mysql:host={$this->host_local};port={$this->port_local};charset=utf8mb4", $this->username_local, $this->password_local);
            
            // Crear la base de datos
            $sql = "CREATE DATABASE IF NOT EXISTS " . $this->db_name_local . " 
                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $conn->exec($sql);
            
            return array('success' => true, 'message' => 'Base de datos creada o ya existe');
            
        } catch(PDOException $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    public function crearTablasDesarrollo() {
        if (!$this->is_local) {
            throw new Exception("Esta función solo está disponible en desarrollo");
        }
        
        try {
            // Crear tabla denuncias
            $sql_denuncias = "
                CREATE TABLE IF NOT EXISTS denuncias (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tipo_problema ENUM(
                        'contaminacion_agua',
                        'contaminacion_aire', 
                        'deforestacion',
                        'manejo_residuos',
                        'ruido_excesivo',
                        'contaminacion_suelo',
                        'otros'
                    ) NOT NULL,
                    descripcion TEXT NOT NULL,
                    ubicacion_lat DECIMAL(10, 8) NULL,
                    ubicacion_lng DECIMAL(11, 8) NULL,
                    ubicacion_direccion VARCHAR(255) NOT NULL,
                    imagen_url VARCHAR(500) NULL,
                    estado ENUM('pendiente', 'en_proceso', 'resuelta') DEFAULT 'pendiente',
                    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    fecha_actualizacion TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
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
                    'descripcion' => 'Vertido de residuos industriales en el Río Verde afectando la calidad del agua',
                    'ubicacion' => 'Río Verde, Sector Industrial, Guayaquil',
                    'estado' => 'pendiente',
                    'dias_atras' => 2
                ),
                array(
                    'tipo_problema' => 'deforestacion',
                    'descripcion' => 'Tala ilegal de árboles en área protegida sin permisos correspondientes',
                    'ubicacion' => 'Área Verde Protegida, Vía a la Costa',
                    'estado' => 'pendiente',
                    'dias_atras' => 1
                ),
                array(
                    'tipo_problema' => 'manejo_residuos',
                    'descripcion' => 'Acumulación de basura en parque público sin recolección por más de una semana',
                    'ubicacion' => 'Parque Central, Samborondón',
                    'estado' => 'en_proceso',
                    'dias_atras' => 5
                ),
                array(
                    'tipo_problema' => 'contaminacion_aire',
                    'descripcion' => 'Emisión excesiva de gases contaminantes de fábrica textil',
                    'ubicacion' => 'Zona Industrial Norte, Guayaquil',
                    'estado' => 'en_proceso',
                    'dias_atras' => 3
                )
            );
            
            $sql = "INSERT INTO denuncias (tipo_problema, descripcion, ubicacion_direccion, estado, fecha_creacion) 
                    VALUES (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))";
            
            $stmt = $this->conn->prepare($sql);
            
            foreach ($denuncias_ejemplo as $denuncia) {
                $stmt->execute(array(
                    $denuncia['tipo_problema'],
                    $denuncia['descripcion'],
                    $denuncia['ubicacion'],
                    $denuncia['estado'],
                    $denuncia['dias_atras']
                ));
            }
            
            // Insertar algunos comentarios de ejemplo
            $comentarios_ejemplo = array(
                array(1, 'Sofia Ramirez', 'Esto requiere atención inmediata de las autoridades'),
                array(1, 'Carlos Gomez', 'Estoy de acuerdo, la situación es muy grave'),
                array(2, 'Ana Lopez', 'He visto camiones llevándose los árboles ilegalmente'),
                array(3, 'Miguel Torres', 'La gestión de residuos en esta zona es deficiente')
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
