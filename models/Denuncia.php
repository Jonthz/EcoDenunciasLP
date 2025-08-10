<?php
// models/Denuncia.php - Modelo unificado para todas las funcionalidades de denuncias
// Responsables: Jonathan Zambrano (Resumen Semanal) y Darwin Pacheco (Estados)
require_once '../config/database.php';

class Denuncia {
    private $conn;
    private $table_name = "denuncias";
    private $table_historial = "historial_estados";
    
    public $id;
    public $tipo_problema;
    public $descripcion;
    public $ubicacion_direccion;
    public $ubicacion_lat;
    public $ubicacion_lng;
    public $imagen_url;
    public $estado;
    public $fecha_creacion;
    public $fecha_actualizacion;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        // Crear tabla de historial si no existe (para funcionalidad de Darwin)
        $this->crearTablaHistorial();
    }
    
    // =====================================================
    // FUNCIONALIDAD 1: RESUMEN SEMANAL (Jonathan Zambrano)
    // =====================================================
    
    /**
     * Obtener resumen semanal con filtros
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
    
    // =====================================================
    // FUNCIONALIDAD 3: ACTUALIZAR ESTADO (Darwin Pacheco)
    // =====================================================
    
    /**
     * Actualizar estado de una denuncia con historial
     * @param int $denuncia_id ID de la denuncia
     * @param string $nuevo_estado Nuevo estado (pendiente, en_proceso, resuelta)
     * @param string $notas Notas opcionales sobre el cambio
     * @param string $usuario_responsable Usuario que realiza el cambio
     * @return array Resultado de la operación
     */
    public function actualizarEstado($denuncia_id, $nuevo_estado, $notas = null, $usuario_responsable = 'Sistema') {
        try {
            // Iniciar transacción para asegurar consistencia
            $this->conn->beginTransaction();
            
            // Obtener estado actual
            $stmt = $this->conn->prepare("SELECT estado FROM " . $this->table_name . " WHERE id = :id");
            $stmt->bindParam(':id', $denuncia_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $denuncia = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$denuncia) {
                $this->conn->rollBack();
                return array('success' => false, 'error' => 'Denuncia no encontrada');
            }
            
            $estado_anterior = $denuncia['estado'];
            
            // Validar transición de estado
            if (!$this->validarTransicionEstado($estado_anterior, $nuevo_estado)) {
                $this->conn->rollBack();
                return array('success' => false, 'error' => 'Transición de estado no válida');
            }
            
            // Actualizar estado en tabla denuncias
            $query_update = "UPDATE " . $this->table_name . " 
                           SET estado = :estado, 
                               fecha_actualizacion = NOW() 
                           WHERE id = :id";
            
            $stmt_update = $this->conn->prepare($query_update);
            $stmt_update->bindParam(':estado', $nuevo_estado);
            $stmt_update->bindParam(':id', $denuncia_id, PDO::PARAM_INT);
            
            if (!$stmt_update->execute()) {
                $this->conn->rollBack();
                return array('success' => false, 'error' => 'Error al actualizar estado');
            }
            
            // Registrar en historial
            $query_historial = "INSERT INTO " . $this->table_historial . " 
                              (denuncia_id, estado_anterior, estado_nuevo, fecha_cambio, 
                               usuario_responsable, notas) 
                              VALUES (:denuncia_id, :estado_anterior, :estado_nuevo, NOW(), 
                                     :usuario_responsable, :notas)";
            
            $stmt_historial = $this->conn->prepare($query_historial);
            $stmt_historial->bindParam(':denuncia_id', $denuncia_id, PDO::PARAM_INT);
            $stmt_historial->bindParam(':estado_anterior', $estado_anterior);
            $stmt_historial->bindParam(':estado_nuevo', $nuevo_estado);
            $stmt_historial->bindParam(':usuario_responsable', $usuario_responsable);
            $stmt_historial->bindParam(':notas', $notas);
            
            if (!$stmt_historial->execute()) {
                $this->conn->rollBack();
                return array('success' => false, 'error' => 'Error al registrar en historial');
            }
            
            // Confirmar transacción
            $this->conn->commit();
            
            return array(
                'success' => true,
                'estado_anterior' => $estado_anterior,
                'estado_nuevo' => $nuevo_estado,
                'historial_id' => $this->conn->lastInsertId()
            );
            
        } catch(Exception $e) {
            $this->conn->rollBack();
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Obtener una denuncia por ID
     */
    public function obtenerPorId($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener denuncia completa con información adicional
     */
    public function obtenerDenunciaCompleta($denuncia_id) {
        $query = "SELECT 
                    d.*,
                    DATEDIFF(NOW(), d.fecha_creacion) as dias_transcurridos,
                    (SELECT COUNT(*) FROM comentarios WHERE denuncia_id = d.id) as total_comentarios,
                    (SELECT COUNT(*) FROM " . $this->table_historial . " WHERE denuncia_id = d.id) as cambios_estado
                  FROM " . $this->table_name . " d
                  WHERE d.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $denuncia_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener historial de cambios de estado
     */
    public function obtenerHistorial($denuncia_id) {
        $query = "SELECT * FROM " . $this->table_historial . " 
                  WHERE denuncia_id = :denuncia_id 
                  ORDER BY fecha_cambio DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':denuncia_id', $denuncia_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener estadísticas de una denuncia específica
     */
    public function obtenerEstadisticasDenuncia($denuncia_id) {
        // Total de comentarios
        $query_comentarios = "SELECT COUNT(*) as total FROM comentarios WHERE denuncia_id = :id";
        $stmt = $this->conn->prepare($query_comentarios);
        $stmt->bindParam(':id', $denuncia_id, PDO::PARAM_INT);
        $stmt->execute();
        $comentarios = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Cambios de estado
        $query_cambios = "SELECT COUNT(*) as total FROM " . $this->table_historial . " WHERE denuncia_id = :id";
        $stmt = $this->conn->prepare($query_cambios);
        $stmt->bindParam(':id', $denuncia_id, PDO::PARAM_INT);
        $stmt->execute();
        $cambios = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Tiempo de resolución (si está resuelta)
        $query_resolucion = "SELECT 
                                TIMESTAMPDIFF(DAY, d.fecha_creacion, h.fecha_cambio) as dias_resolucion
                             FROM " . $this->table_name . " d
                             LEFT JOIN " . $this->table_historial . " h ON d.id = h.denuncia_id
                             WHERE d.id = :id AND h.estado_nuevo = 'resuelta'
                             ORDER BY h.fecha_cambio DESC
                             LIMIT 1";
        
        $stmt = $this->conn->prepare($query_resolucion);
        $stmt->bindParam(':id', $denuncia_id, PDO::PARAM_INT);
        $stmt->execute();
        $resolucion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return array(
            'total_comentarios' => $comentarios['total'] ?? 0,
            'cambios_estado' => $cambios['total'] ?? 0,
            'tiempo_resolucion' => $resolucion['dias_resolucion'] ?? null
        );
    }
    
    // =====================================================
    // MÉTODOS COMUNES Y AUXILIARES
    // =====================================================
    
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
     * Validar transición de estado
     */
    private function validarTransicionEstado($estado_actual, $estado_nuevo) {
        // Definir transiciones válidas
        $transiciones_validas = array(
            'pendiente' => array('en_proceso', 'resuelta'),
            'en_proceso' => array('pendiente', 'resuelta'),
            'resuelta' => array('pendiente', 'en_proceso')
        );
        
        // Permitir mantener el mismo estado (aunque el controlador ya lo valida)
        if ($estado_actual === $estado_nuevo) {
            return false;
        }
        
        if (!isset($transiciones_validas[$estado_actual])) {
            return false;
        }
        
        return in_array($estado_nuevo, $transiciones_validas[$estado_actual]);
    }
    
    /**
     * Crear tabla de historial si no existe
     */
    private function crearTablaHistorial() {
        $query = "CREATE TABLE IF NOT EXISTS " . $this->table_historial . " (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    denuncia_id INT NOT NULL,
                    estado_anterior VARCHAR(50) NOT NULL,
                    estado_nuevo VARCHAR(50) NOT NULL,
                    fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    usuario_responsable VARCHAR(100) DEFAULT 'Sistema',
                    notas TEXT NULL,
                    FOREIGN KEY (denuncia_id) REFERENCES denuncias(id) ON DELETE CASCADE,
                    INDEX idx_denuncia_id (denuncia_id),
                    INDEX idx_fecha_cambio (fecha_cambio),
                    INDEX idx_estado_nuevo (estado_nuevo)
                  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $this->conn->exec($query);
            return true;
        } catch(PDOException $e) {
            // La tabla ya existe o hay otro error, pero no interrumpimos
            return false;
        }
    }
    
    /**
     * Obtener todas las denuncias con paginación
     */
    public function obtenerTodas($limite = 20, $offset = 0, $filtros = array()) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE 1=1";
        
        // Aplicar filtros si existen
        if (isset($filtros['estado'])) {
            $query .= " AND estado = :estado";
        }
        if (isset($filtros['tipo_problema'])) {
            $query .= " AND tipo_problema = :tipo_problema";
        }
        
        $query .= " ORDER BY fecha_creacion DESC LIMIT :limite OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        
        if (isset($filtros['estado'])) {
            $stmt->bindParam(':estado', $filtros['estado']);
        }
        if (isset($filtros['tipo_problema'])) {
            $stmt->bindParam(':tipo_problema', $filtros['tipo_problema']);
        }
        
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>