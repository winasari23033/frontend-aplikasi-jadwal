<?php
// config/database.php - Konfigurasi Database
class Database {
    private $host = "localhost";
    private $db_name = "studyenth_db";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

// models/Jadwal.php - Model untuk Jadwal Kuliah
class Jadwal {
    private $conn;
    private $table = "jadwal_kuliah";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                 SET id=:id, nama_matkul=:nama_matkul, nama_dosen=:nama_dosen, 
                     jam_kuliah=:jam_kuliah, hari=:hari, ruangan=:ruangan";
        
        $stmt = $this->conn->prepare($query);
        
        // Generate ID unik
        $id = uniqid('jadwal_', true);
        
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":nama_matkul", $data['matkul']);
        $stmt->bindParam(":nama_dosen", $data['dosen']);
        $stmt->bindParam(":jam_kuliah", $data['jam']);
        $stmt->bindParam(":hari", $data['hari']);
        $stmt->bindParam(":ruangan", $data['ruangan']);
        
        if($stmt->execute()) {
            return $id;
        }
        return false;
    }

    public function read() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY 
                 CASE hari 
                     WHEN 'Senin' THEN 1
                     WHEN 'Selasa' THEN 2
                     WHEN 'Rabu' THEN 3
                     WHEN 'Kamis' THEN 4
                     WHEN 'Jumat' THEN 5
                     WHEN 'Sabtu' THEN 6
                     WHEN 'Minggu' THEN 7
                 END, jam_kuliah";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        return $stmt->execute();
    }

    public function getJadwalHariIni() {
        $query = "SELECT * FROM jadwal_hari_ini ORDER BY jam_kuliah";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}

// models/Tugas.php - Model untuk Tugas
class Tugas {
    private $conn;
    private $table = "tugas";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                 SET id=:id, nama_matkul=:nama_matkul, keterangan=:keterangan, 
                     deadline=:deadline, status=:status, prioritas=:prioritas";
        
        $stmt = $this->conn->prepare($query);
        
        // Generate ID unik
        $id = uniqid('tugas_', true);
        
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":nama_matkul", $data['matkul']);
        $stmt->bindParam(":keterangan", $data['keterangan']);
        $stmt->bindParam(":deadline", $data['deadline']);
        $stmt->bindParam(":status", $data['status']);
        $stmt->bindParam(":prioritas", $data['prioritas']);
        
        if($stmt->execute()) {
            return $id;
        }
        return false;
    }

    public function read() {
        $query = "SELECT *, 
                  CASE 
                      WHEN deadline < CURDATE() AND status != 'selesai' THEN 'terlambat'
                      WHEN DATEDIFF(deadline, CURDATE()) <= 3 AND status != 'selesai' THEN 'mendesak'
                      ELSE 'normal'
                  END as urgency,
                  DATEDIFF(deadline, CURDATE()) as hari_tersisa
                  FROM " . $this->table . " 
                  ORDER BY 
                  CASE status 
                      WHEN 'sedang' THEN 1
                      WHEN 'belum' THEN 2
                      WHEN 'selesai' THEN 3
                  END, deadline ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table . " 
                 SET status = :status, 
                     completed_at = CASE WHEN :status = 'selesai' THEN NOW() ELSE NULL END
                 WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $id);
        
        return $stmt->execute();
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        return $stmt->execute();
    }

    public function getStatistik() {
        $query = "SELECT * FROM statistik_tugas";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getTugasMenungguDeadline($hari = 7) {
        $query = "SELECT *, DATEDIFF(deadline, CURDATE()) as hari_tersisa 
                 FROM " . $this->table . " 
                 WHERE deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :hari DAY)
                 AND status != 'selesai'
                 ORDER BY deadline ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":hari", $hari);
        $stmt->execute();
        return $stmt;
    }
}

// api/jadwal.php - API Endpoint untuk Jadwal
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

include_once '../config/database.php';
include_once '../models/Jadwal.php';

$database = new Database();
$db = $database->getConnection();
$jadwal = new Jadwal($db);

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        if(isset($_GET['action']) && $_GET['action'] == 'hari_ini') {
            $stmt = $jadwal->getJadwalHariIni();
        } else {
            $stmt = $jadwal->read();
        }
        
        $jadwal_arr = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($jadwal_arr, array(
                'id' => $row['id'],
                'matkul' => $row['nama_matkul'],
                'dosen' => $row['nama_dosen'],
                'jam' => $row['jam_kuliah'],
                'hari' => $row['hari'] ?? '',
                'ruangan' => $row['ruangan'] ?? ''
            ));
        }
        echo json_encode($jadwal_arr);
        break;
    
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validasi data
        if(empty($data['matkul']) || empty($data['dosen']) || empty($data['jam'])) {
            http_response_code(400);
            echo json_encode(array("message" => "Data tidak lengkap"));
            break;
        }
        
        // Set default values
        $data['hari'] = $data['hari'] ?? '';
        $data['ruangan'] = $data['ruangan'] ?? '';
        
        $result = $jadwal->create($data);
        
        if($result) {
            http_response_code(201);
            echo json_encode(array(
                "message" => "Jadwal berhasil ditambahkan",
                "id" => $result
            ));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Gagal menambahkan jadwal"));
        }
        break;
    
    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"), true);
        
        if(empty($data['id'])) {
            http_response_code(400);
            echo json_encode(array("message" => "ID tidak ditemukan"));
            break;
        }
        
        if($jadwal->delete($data['id'])) {
            http_response_code(200);
            echo json_encode(array("message" => "Jadwal berhasil dihapus"));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Gagal menghapus jadwal"));
        }
        break;
}

// api/tugas.php - API Endpoint untuk Tugas
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

include_once '../config/database.php';
include_once '../models/Tugas.php';

$database = new Database();
$db = $database->getConnection();
$tugas = new Tugas($db);

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        if(isset($_GET['action'])) {
            switch($_GET['action']) {
                case 'statistik':
                    $stats = $tugas->getStatistik();
                    echo json_encode($stats);
                    exit;
                
                case 'deadline':
                    $hari = $_GET['hari'] ?? 7;
                    $stmt = $tugas->getTugasMenungguDeadline($hari);
                    $tugas_arr = array();
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        array_push($tugas_arr, $row);
                    }
                    echo json_encode($tugas_arr);
                    exit;
            }
        }
        
        $stmt = $tugas->read();
        $tugas_arr = array();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($tugas_arr, array(
                'id' => $row['id'],
                'matkul' => $row['nama_matkul'],
                'keterangan' => $row['keterangan'],
                'deadline' => $row['deadline'],
                'status' => $row['status'],
                'prioritas' => $row['prioritas'],
                'urgency' => $row['urgency'],
                'hari_tersisa' => $row['hari_tersisa'],
                'completed_at' => $row['completed_at']
            ));
        }
        echo json_encode($tugas_arr);
        break;
    
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validasi data
        if(empty($data['matkul']) || empty($data['keterangan']) || empty($data['deadline'])) {
            http_response_code(400);
            echo json_encode(array("message" => "Data tidak lengkap"));
            break;
        }
        
        // Set default values
        $data['status'] = $data['status'] ?? 'belum';
        $data['prioritas'] = $data['prioritas'] ?? 'sedang';
        
        $result = $tugas->create($data);
        
        if($result) {
            http_response_code(201);
            echo json_encode(array(
                "message" => "Tugas berhasil ditambahkan",
                "id" => $result
            ));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Gagal menambahkan tugas"));
        }
        break;
    
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);
        
        if(empty($data['id']) || empty($data['status'])) {
            http_response_code(400);
            echo json_encode(array("message" => "ID atau status tidak ditemukan"));
            break;
        }
        
        if($tugas->updateStatus($data['id'], $data['status'])) {
            http_response_code(200);
            echo json_encode(array("message" => "Status tugas berhasil diubah"));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Gagal mengubah status tugas"));
        }
        break;
    
    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"), true);
        
        if(empty($data['id'])) {
            http_response_code(400);
            echo json_encode(array("message" => "ID tidak ditemukan"));
            break;
        }
        
        if($tugas->delete($data['id'])) {
            http_response_code(200);
            echo json_encode(array("message" => "Tugas berhasil dihapus"));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Gagal menghapus tugas"));
        }
        break;
}
?>