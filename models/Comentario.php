<?php
// models/Comentario.php - Funcionalidad 2: Sistema de Comentarios
require_once '../config/database.php';

class Comentario {
    private $conn;
    private $table_name = "comentarios";
    
    public $id;
    public $denuncia_id;
    public $nombre_usuario;
    public $comentario;
    public $fecha_creacion;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * FUNCIONALIDAD 2: Crear nuevo comentario
     * @return int|false ID del comentario creado o false si falla
     */
    public function crear() {
        // Validaciones básicas
        if (empty($this->denuncia_id) || empty($this->nombre_usuario) || empty($this->comentario)) {
            throw new Exception("Todos los campos son requeridos");
        }
        
        // Verificar que la denuncia existe
        if (!$this->denunciaExiste($this->denuncia_id)) {
            throw new Exception("La denuncia especificada no existe");
        }
        
        $query = "INSERT INTO " . $this->table_name . " 
                  SET denuncia_id = :denuncia_id, 
                      nombre_usuario = :nombre_usuario, 
                      comentario = :comentario, 
                      fecha_creacion = NOW()";
        
        $stmt = $this->conn->prepare($query);
        
        // Limpiar y validar datos
        $this->denuncia_id = (int)$this->denuncia_id;
        $this->nombre_usuario = htmlspecialchars(strip_tags(trim($this->nombre_usuario)));
        $this->comentario = htmlspecialchars(strip_tags(trim($this->comentario)));
        
        // Validar longitudes
        if (strlen($this->nombre_usuario) < 2 || strlen($this->nombre_usuario) > 100) {
            throw new Exception("El nombre debe tener entre 2 y 100 caracteres");
        }
        
        if (strlen($this->comentario) < 5 || strlen($this->comentario) > 1000) {
            throw new Exception("El comentario debe tener entre 5 y 1000 caracteres");
        }
        
        $stmt->bindParam(":denuncia_id", $this->denuncia_id);
        $stmt->bindParam(":nombre_usuario", $this->nombre_usuario);
        $stmt->bindParam(":comentario", $this->comentario);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    /**
     * Obtener comentarios por denuncia con paginación
     * @param int $denuncia_id ID de la denuncia
     * @param int $limite Cantidad de comentarios por página
     * @param int $offset Desplazamiento para paginación
     * @return PDOStatement
     */
    public function obtenerPorDenuncia($denuncia_id, $limite = 20, $offset = 0) {
        $query = "SELECT 
                    c.id,
                    c.nombre_usuario,
                    c.comentario,
                    c.fecha_creacion,
                    TIMESTAMPDIFF(MINUTE, c.fecha_creacion, NOW()) as minutos_transcurridos
                  FROM " . $this->table_name . " c
                  WHERE c.denuncia_id = :denuncia_id 
                  ORDER BY c.fecha_creacion ASC 
                  LIMIT :limite OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":denuncia_id", $denuncia_id, PDO::PARAM_INT);
        $stmt->bindParam(":limite", $limite, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Contar total de comentarios por denuncia
     */
    public function contarPorDenuncia($denuncia_id) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " 
                  WHERE denuncia_id = :denuncia_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":denuncia_id", $denuncia_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['total'];
    }
    
    /**
     * Obtener estadísticas de comentarios por denuncia
     */
    public function obtenerEstadisticasPorDenuncia($denuncia_id) {
        $query = "SELECT 
                    COUNT(*) as total_comentarios,
                    MAX(fecha_creacion) as ultimo_comentario,
                    MIN(fecha_creacion) as primer_comentario
                  FROM " . $this->table_name . " 
                  WHERE denuncia_id = :denuncia_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":denuncia_id", $denuncia_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Verificar si una denuncia existe
     */
    private function denunciaExiste($denuncia_id) {
        $query = "SELECT COUNT(*) as count FROM denuncias WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $denuncia_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
    
    /**
     * Obtener comentarios recientes (todas las denuncias)
     */
    public function obtenerRecientes($limite = 10) {
        $query = "SELECT 
                    c.id,
                    c.denuncia_id,
                    c.nombre_usuario,
                    c.comentario,
                    c.fecha_creacion,
                    d.tipo_problema,
                    LEFT(d.descripcion, 50) as denuncia_resumen
                  FROM " . $this->table_name . " c
                  INNER JOIN denuncias d ON c.denuncia_id = d.id
                  ORDER BY c.fecha_creacion DESC 
                  LIMIT :limite";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limite", $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt;
    }
}
?>
