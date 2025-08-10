<?php
// models/Denuncia.php - Funcionalidad 1: Resumen Semanal
require_once '../config/database.php';

class Denuncia {
    private $conn;
    private $table_name = "denuncias";
    
    public $id;
    public $tipo_problema;
    public $descripcion;
    public $ubicacion_direccion;
    public $estado;
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
}
?>
