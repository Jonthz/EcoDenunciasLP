<?php
// models/Denuncia.php - Funcionalidad 1: Resumen Semanal
require_once '../config/database.php';

class Denuncia {
    private $conn;
    private $table_name = "denuncias";
    
    // ACTUALIZACIÓN Giovanni Sambonino - Propiedades para campos completos de denuncias con imagen
    public $id;
    public $tipo_problema;
    public $descripcion;
    public $ubicacion;
    public $latitud;
    public $longitud;
    public $imagen_evidencia;
    public $nombre_reportante;
    public $email_reportante;
    public $telefono_reportante;
    public $estado;
    public $prioridad;
    public $fecha_reporte;
    public $fecha_creacion;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * FUNCIONALIDAD 1: Obtener resumen semanal con filtros
     * @param string $zona - Filtro opcional por zona/ubicación
     * @param string $categoria - Filtro opcional por tipo de problema
     * @param int $limite - Cantidad máxima de denuncias (default: 10)
     * @return PDOStatement
     */
    public function obtenerResumenSemanal($zona = null, $categoria = null, $limite = 10) {
        // Query base para últimos 7 días
        $query = "SELECT 
                    id,
                    tipo_problema,
                    descripcion,
                    ubicacion_direccion,
                    estado,
                    fecha_creacion,
                    imagen_url,
                    DATEDIFF(NOW(), fecha_creacion) as dias_transcurridos
                  FROM " . $this->table_name . " 
                  WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        
        // Array para parámetros
        $params = array();
        
        // Agregar filtro por zona si se especifica
        if ($zona && !empty(trim($zona))) {
            $query .= " AND ubicacion_direccion LIKE :zona";
            $params[':zona'] = "%" . trim($zona) . "%";
        }
        
        // Agregar filtro por categoría si se especifica
        if ($categoria && !empty(trim($categoria))) {
            $query .= " AND tipo_problema = :categoria";
            $params[':categoria'] = trim($categoria);
        }
        
        // Ordenar por fecha descendente y limitar
        $query .= " ORDER BY fecha_creacion DESC LIMIT :limite";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind de parámetros
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt;
    }
    
    /**
     * Obtener estadísticas del resumen semanal
     */
    public function obtenerEstadisticasSemanal($zona = null, $categoria = null) {
        $query = "SELECT 
                    COUNT(*) as total_denuncias,
                    COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
                    COUNT(CASE WHEN estado = 'en_proceso' THEN 1 END) as en_proceso,
                    COUNT(CASE WHEN estado = 'resuelta' THEN 1 END) as resueltas
                  FROM " . $this->table_name . " 
                  WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        
        $params = array();
        
        if ($zona && !empty(trim($zona))) {
            $query .= " AND ubicacion_direccion LIKE :zona";
            $params[':zona'] = "%" . trim($zona) . "%";
        }
        
        if ($categoria && !empty(trim($categoria))) {
            $query .= " AND tipo_problema = :categoria";
            $params[':categoria'] = trim($categoria);
        }
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener categorías disponibles para filtros
     */
    public function obtenerCategorias() {
        $query = "SELECT DISTINCT tipo_problema as categoria
                  FROM " . $this->table_name . " 
                  ORDER BY tipo_problema";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $categorias = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categorias[] = $row['categoria'];
        }
        
        return $categorias;
    }
    
    /**
     * Obtener zonas/ubicaciones disponibles para filtros
     */
    public function obtenerZonas() {
        $query = "SELECT DISTINCT 
                    TRIM(SUBSTRING_INDEX(ubicacion_direccion, ',', -1)) as zona
                  FROM " . $this->table_name . " 
                  WHERE ubicacion_direccion IS NOT NULL
                  ORDER BY zona";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $zonas = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $zona = trim($row['zona']);
            if (!empty($zona) && !in_array($zona, $zonas)) {
                $zonas[] = $zona;
            }
        }
        
        return $zonas;
    }
    
    /**
     * ACTUALIZACIÓN Giovanni Sambonino - Crear nueva denuncia con campos completos incluyendo imagen y prioridad
     */
    public function crear($datos) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     (tipo_problema, descripcion, ubicacion, latitud, longitud, 
                      imagen_evidencia, nombre_reportante, email_reportante, 
                      telefono_reportante, estado, prioridad, fecha_reporte) 
                     VALUES 
                     (:tipo_problema, :descripcion, :ubicacion, :latitud, :longitud, 
                      :imagen_evidencia, :nombre_reportante, :email_reportante, 
                      :telefono_reportante, :estado, :prioridad, :fecha_reporte)";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind parameters con validación
            $stmt->bindParam(':tipo_problema', $datos['tipo_problema'], PDO::PARAM_STR);
            $stmt->bindParam(':descripcion', $datos['descripcion'], PDO::PARAM_STR);
            $stmt->bindParam(':ubicacion', $datos['ubicacion'], PDO::PARAM_STR);
            
            // Manejar valores NULL correctamente
            $latitud = $datos['latitud'];
            $longitud = $datos['longitud'];
            $imagen = $datos['imagen_evidencia'];
            $email = $datos['email_reportante'];
            $telefono = $datos['telefono_reportante'];
            
            $stmt->bindParam(':latitud', $latitud, PDO::PARAM_STR);
            $stmt->bindParam(':longitud', $longitud, PDO::PARAM_STR);
            $stmt->bindParam(':imagen_evidencia', $imagen, PDO::PARAM_STR);
            $stmt->bindParam(':nombre_reportante', $datos['nombre_reportante'], PDO::PARAM_STR);
            $stmt->bindParam(':email_reportante', $email, PDO::PARAM_STR);
            $stmt->bindParam(':telefono_reportante', $telefono, PDO::PARAM_STR);
            $stmt->bindParam(':estado', $datos['estado'], PDO::PARAM_STR);
            $stmt->bindParam(':prioridad', $datos['prioridad'], PDO::PARAM_STR);
            $stmt->bindParam(':fecha_reporte', $datos['fecha_reporte'], PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'id' => $this->conn->lastInsertId(),
                    'message' => 'Denuncia creada exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al ejecutar consulta: ' . implode(', ', $stmt->errorInfo())
                ];
            }
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'error' => 'Error de base de datos: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ACTUALIZACIÓN Giovanni Sambonino - Obtener denuncia por ID con información completa y estadísticas
     */
    public function obtenerPorId($id) {
        try {
            $query = "SELECT d.*, 
                             TIMESTAMPDIFF(DAY, d.fecha_reporte, NOW()) as dias_transcurridos,
                             (SELECT COUNT(*) FROM comentarios c WHERE c.denuncia_id = d.id AND c.activo = 1) as total_comentarios
                      FROM " . $this->table_name . " d 
                      WHERE d.id = :id 
                      LIMIT 1";
                      
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado) {
                // Agregar URL completa de imagen si existe
                if ($resultado['imagen_evidencia']) {
                    $resultado['imagen_url'] = API_URL . '../' . $resultado['imagen_evidencia'];
                }
                
                return $resultado;
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("Error en obtenerPorId: " . $e->getMessage());
            return false;
        }
    }

    // ACTUALIZACIÓN Giovanni Sambonino - Obtener denuncias con filtros para búsquedas avanzadas
    public function obtenerConFiltros($filtros = []) {
        try {
            $query = "SELECT d.*, 
                             TIMESTAMPDIFF(DAY, d.fecha_reporte, NOW()) as dias_transcurridos,
                             (SELECT COUNT(*) FROM comentarios c WHERE c.denuncia_id = d.id AND c.activo = 1) as total_comentarios
                      FROM " . $this->table_name . " d 
                      WHERE 1=1";
            
            $params = [];
            
            // Filtro por zona/ubicación
            if (!empty($filtros['zona'])) {
                $query .= " AND d.ubicacion LIKE :zona";
                $params[':zona'] = '%' . $filtros['zona'] . '%';
            }
            
            // Filtro por categoría
            if (!empty($filtros['categoria'])) {
                $query .= " AND d.tipo_problema = :categoria";
                $params[':categoria'] = $filtros['categoria'];
            }
            
            // Filtro por estado
            if (!empty($filtros['estado'])) {
                $query .= " AND d.estado = :estado";
                $params[':estado'] = $filtros['estado'];
            }
            
            // Filtro por fecha
            if (!empty($filtros['dias'])) {
                $query .= " AND d.fecha_reporte >= DATE_SUB(NOW(), INTERVAL :dias DAY)";
                $params[':dias'] = (int)$filtros['dias'];
            } else {
                $query .= " AND d.fecha_reporte >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            }
            
            // Ordenar por fecha más reciente
            $query .= " ORDER BY d.fecha_reporte DESC";
            
            // Limitar resultados
            $limite = !empty($filtros['limite']) ? min((int)$filtros['limite'], 50) : 10;
            $query .= " LIMIT :limite";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind parámetros
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            
            $stmt->execute();
            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Agregar URLs de imágenes
            foreach ($resultados as &$denuncia) {
                if ($denuncia['imagen_evidencia']) {
                    $denuncia['imagen_url'] = API_URL . '../' . $denuncia['imagen_evidencia'];
                }
            }
            
            return $resultados;
            
        } catch (PDOException $e) {
            error_log("Error en obtenerConFiltros: " . $e->getMessage());
            return false;
        }
    }

    // ACTUALIZACIÓN Giovanni Sambonino - Obtener estadísticas completas de denuncias por estado y prioridad
    public function obtenerEstadisticas($filtros = []) {
        try {
            $query = "SELECT 
                        COUNT(*) as total_denuncias,
                        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                        SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
                        SUM(CASE WHEN estado = 'resuelto' THEN 1 ELSE 0 END) as resueltas,
                        SUM(CASE WHEN estado = 'rechazado' THEN 1 ELSE 0 END) as rechazadas,
                        SUM(CASE WHEN prioridad = 'critica' THEN 1 ELSE 0 END) as criticas,
                        SUM(CASE WHEN prioridad = 'alta' THEN 1 ELSE 0 END) as altas,
                        AVG(TIMESTAMPDIFF(DAY, fecha_reporte, NOW())) as promedio_dias_antiguedad
                      FROM " . $this->table_name . " d 
                      WHERE 1=1";
            
            $params = [];
            
            // Aplicar filtros
            if (!empty($filtros['zona'])) {
                $query .= " AND d.ubicacion LIKE :zona";
                $params[':zona'] = '%' . $filtros['zona'] . '%';
            }
            
            if (!empty($filtros['categoria'])) {
                $query .= " AND d.tipo_problema = :categoria";
                $params[':categoria'] = $filtros['categoria'];
            }
            
            if (!empty($filtros['dias'])) {
                $query .= " AND d.fecha_reporte >= DATE_SUB(NOW(), INTERVAL :dias DAY)";
                $params[':dias'] = (int)$filtros['dias'];
            } else {
                $query .= " AND d.fecha_reporte >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            }
            
            $stmt = $this->conn->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total_denuncias' => (int)$estadisticas['total_denuncias'],
                'pendientes' => (int)$estadisticas['pendientes'],
                'en_proceso' => (int)$estadisticas['en_proceso'],
                'resueltas' => (int)$estadisticas['resueltas'],
                'rechazadas' => (int)$estadisticas['rechazadas'],
                'criticas' => (int)$estadisticas['criticas'],
                'altas' => (int)$estadisticas['altas'],
                'promedio_dias_antiguedad' => round((float)$estadisticas['promedio_dias_antiguedad'], 1)
            ];
            
        } catch (PDOException $e) {
            error_log("Error en obtenerEstadisticas: " . $e->getMessage());
            return false;
        }
    }
}
?>
