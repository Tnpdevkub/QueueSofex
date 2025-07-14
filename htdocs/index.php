<?php
// config.php
class Database {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO('sqlite:work_queue.db');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTable();
        } catch(PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS work_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_name TEXT NOT NULL,
            discord_id TEXT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            description TEXT NOT NULL,
            deadline DATE NOT NULL,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->pdo->exec($sql);
    }
    
    public function getPDO() {
        return $this->pdo;
    }
}

// WorkQueue.php
class WorkQueue {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->getPDO();
    }
    
    public function addQueue($customerName, $discordId, $price, $description, $deadline) {
        $sql = "INSERT INTO work_queue (customer_name, discord_id, price, description, deadline) 
                VALUES (:customer_name, :discord_id, :price, :description, :deadline)";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':customer_name' => $customerName,
            ':discord_id' => $discordId,
            ':price' => $price,
            ':description' => $description,
            ':deadline' => $deadline
        ]);
    }
    
    public function getAllQueues() {
        $sql = "SELECT * FROM work_queue ORDER BY deadline ASC, created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateStatus($id, $status) {
        $sql = "UPDATE work_queue SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }
    
    public function deleteQueue($id) {
        $sql = "DELETE FROM work_queue WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}

// Initialize
$database = new Database();
$workQueue = new WorkQueue($database);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_queue'])) {
        $customerName = trim($_POST['customer_name']);
        $discordId = trim($_POST['discord_id']);
        $price = floatval($_POST['price']);
        $description = trim($_POST['description']);
        $deadline = $_POST['deadline'];
        
        if (!empty($customerName) && !empty($discordId) && $price > 0 && !empty($description) && !empty($deadline)) {
            $workQueue->addQueue($customerName, $discordId, $price, $description, $deadline);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    }
    
    if (isset($_POST['update_status'])) {
        $id = intval($_POST['id']);
        $status = $_POST['status'];
        $workQueue->updateStatus($id, $status);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (isset($_POST['delete_queue'])) {
        $id = intval($_POST['id']);
        $workQueue->deleteQueue($id);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get all queues
$queues = $workQueue->getAllQueues();

// Helper function to format date
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Helper function to get status color
function getStatusColor($status) {
    switch($status) {
        case 'pending': return '#ffc107';
        case 'in_progress': return '#007bff';
        case 'completed': return '#28a745';
        case 'cancelled': return '#dc3545';
        default: return '#6c757d';
    }
}

// Helper function to get status text
function getStatusText($status) {
    switch($status) {
        case 'pending': return 'รอดำเนินการ';
        case 'in_progress': return 'กำลังทำ';
        case 'completed': return 'เสร็จแล้ว';
        case 'cancelled': return 'ยกเลิก';
        default: return 'ไม่ทราบสถานะ';
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจดบันทึกคิวงาน</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Kanit', 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            line-height: 1.6;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="10" cy="50" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="90" cy="30" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
            z-index: -1;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1 {
            text-align: center;
            color: white;
            margin-bottom: 20px;
            font-size: 2.2em;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #96ceb4);
            background-size: 400% 400%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradientShift 3s ease-in-out infinite;
        }
        
        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .form-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
            margin-bottom: 20px;
            animation: fadeInUp 0.6s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        .form-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }
        
        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            50% { transform: translateX(100%) translateY(100%) rotate(45deg); }
            100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
        }
        
        .form-section h2 {
            color: #2c3e50;
            margin-bottom: 18px;
            font-size: 1.5em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-section h2::before {
            content: '';
            width: 3px;
            height: 24px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 2px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        input, textarea, select {
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Sarabun', sans-serif;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }
        
        input:hover, textarea:hover, select:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Kanit', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }
        
        .btn-success {
            background: linear-gradient(45deg, #4ecdc4, #44a08d);
            color: white;
            font-size: 13px;
            padding: 8px 15px;
            box-shadow: 0 4px 15px rgba(78, 205, 196, 0.4);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(78, 205, 196, 0.6);
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
            color: white;
            font-size: 13px;
            padding: 8px 15px;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.6);
        }
        
        .queue-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
            animation: fadeInUp 0.8s ease-out;
        }
        
        .queue-section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.5em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .queue-section h2::before {
            content: '';
            width: 3px;
            height: 24px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 2px;
        }
        
        .queue-grid {
            display: grid;
            gap: 15px;
        }
        
        .queue-item {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid transparent;
            background-image: linear-gradient(rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.9)), 
                              linear-gradient(45deg, #667eea, #764ba2);
            background-origin: border-box;
            background-clip: padding-box, border-box;
            position: relative;
            transition: all 0.3s ease;
            animation: slideInLeft 0.6s ease-out;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .queue-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }
        
        .queue-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.03), rgba(118, 75, 162, 0.03));
            border-radius: 15px;
            z-index: -1;
        }
        
        .queue-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .queue-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .queue-actions {
            display: flex;
            gap: 10px;
        }
        
        .queue-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-weight: bold;
            color: #555;
            font-size: 0.9em;
        }
        
        .detail-value {
            margin-top: 5px;
            font-size: 1em;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            text-transform: uppercase;
        }
        
        .price {
            font-size: 1.2em;
            font-weight: bold;
            color: #27ae60;
        }
        
        .deadline {
            color: #e74c3c;
            font-weight: bold;
        }
        
        .deadline.urgent {
            background-color: #ffebee;
            padding: 2px 6px;
            border-radius: 3px;
        }
        
        .no-queues {
            text-align: center;
            color: #666;
            font-size: 1.1em;
            padding: 40px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
            transition: all 0.3s ease;
            animation: scaleIn 0.6s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.5s;
            opacity: 0;
        }
        
        .stat-card:hover::before {
            opacity: 1;
            animation: rotate 2s linear infinite;
        }
        
        @keyframes rotate {
            0% { transform: rotate(45deg) translateX(-100%) translateY(-100%); }
            100% { transform: rotate(45deg) translateX(100%) translateY(100%); }
        }
        
        .stat-card:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 15px 40px rgba(31, 38, 135, 0.5);
        }
        
        .stat-number {
            font-size: 2.2em;
            font-weight: 700;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }
        
        .stat-label {
            color: #666;
            font-size: 1em;
            font-weight: 500;
            margin-top: 5px;
        }
        
        label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 1.05em;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .queue-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .queue-actions {
                justify-content: flex-start;
            }
            
            h1 {
                font-size: 2.2em;
            }
            
            .form-section, .queue-section {
                padding: 25px;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .queue-item {
                padding: 20px;
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(45deg, #5a67d8, #6b46c1);
        }
        
        /* Enhanced form styling */
        .form-group {
            position: relative;
        }
        
        .form-group::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            transition: width 0.3s ease;
        }
        
        .form-group:focus-within::before {
            width: 100%;
        }
        
        /* Button hover effects */
        .btn {
            transform-origin: center;
        }
        
        .btn:active {
            transform: scale(0.95);
        }
        
        /* Loading animation for forms */
        .form-section.loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #667eea);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 ระบบจดบันทึกคิวงาน</h1>
        
        <?php
        // Calculate statistics
        $totalQueues = count($queues);
        $pendingQueues = count(array_filter($queues, function($q) { return $q['status'] === 'pending'; }));
        $inProgressQueues = count(array_filter($queues, function($q) { return $q['status'] === 'in_progress'; }));
        $completedQueues = count(array_filter($queues, function($q) { return $q['status'] === 'completed'; }));
        $totalRevenue = array_sum(array_column(array_filter($queues, function($q) { return $q['status'] === 'completed'; }), 'price'));
        ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalQueues; ?></div>
                <div class="stat-label">คิวทั้งหมด</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pendingQueues; ?></div>
                <div class="stat-label">รอดำเนินการ</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $inProgressQueues; ?></div>
                <div class="stat-label">กำลังทำ</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $completedQueues; ?></div>
                <div class="stat-label">เสร็จแล้ว</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">฿<?php echo number_format($totalRevenue, 2); ?></div>
                <div class="stat-label">รายได้รวม</div>
            </div>
        </div>
        
        <div class="form-section">
            <h2>➕ เพิ่มคิวงานใหม่</h2>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="customer_name">ชื่อลูกค้า:</label>
                        <input type="text" id="customer_name" name="customer_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="discord_id">Discord ID:</label>
                        <input type="text" id="discord_id" name="discord_id" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">ราคา (บาท):</label>
                        <input type="number" id="price" name="price" min="0" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="deadline">กำหนดส่ง:</label>
                        <input type="date" id="deadline" name="deadline" required>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label for="description">รายละเอียดงาน:</label>
                    <textarea id="description" name="description" rows="4" required></textarea>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" name="add_queue" class="btn btn-primary">เพิ่มคิวงาน</button>
                </div>
            </form>
        </div>
        
        <div class="queue-section">
            <h2>📝 รายการคิวงาน</h2>
            
            <?php if (empty($queues)): ?>
                <div class="no-queues">
                    ยังไม่มีคิวงาน
                </div>
            <?php else: ?>
                <div class="queue-grid">
                    <?php foreach ($queues as $queue): ?>
                        <?php
                        $isUrgent = strtotime($queue['deadline']) <= strtotime('+3 days');
                        $deadlineClass = $isUrgent ? 'deadline urgent' : 'deadline';
                        ?>
                        <div class="queue-item">
                            <div class="queue-header">
                                <div class="queue-title"><?php echo htmlspecialchars($queue['customer_name']); ?></div>
                                <div class="queue-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id" value="<?php echo $queue['id']; ?>">
                                        <select name="status" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $queue['status'] === 'pending' ? 'selected' : ''; ?>>รอดำเนินการ</option>
                                            <option value="in_progress" <?php echo $queue['status'] === 'in_progress' ? 'selected' : ''; ?>>กำลังทำ</option>
                                            <option value="completed" <?php echo $queue['status'] === 'completed' ? 'selected' : ''; ?>>เสร็จแล้ว</option>
                                            <option value="cancelled" <?php echo $queue['status'] === 'cancelled' ? 'selected' : ''; ?>>ยกเลิก</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบคิวนี้?')">
                                        <input type="hidden" name="id" value="<?php echo $queue['id']; ?>">
                                        <button type="submit" name="delete_queue" class="btn btn-danger">ลบ</button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="queue-details">
                                <div class="detail-item">
                                    <div class="detail-label">Discord ID:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($queue['discord_id']); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">ราคา:</div>
                                    <div class="detail-value price">฿<?php echo number_format($queue['price'], 2); ?></div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">กำหนดส่ง:</div>
                                    <div class="detail-value <?php echo $deadlineClass; ?>">
                                        <?php echo formatDate($queue['deadline']); ?>
                                        <?php if ($isUrgent): ?>
                                            <span style="color: #e74c3c; font-weight: bold;"> (เร่งด่วน!)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-label">สถานะ:</div>
                                    <div class="detail-value">
                                        <span class="status-badge" style="background-color: <?php echo getStatusColor($queue['status']); ?>">
                                            <?php echo getStatusText($queue['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-label">รายละเอียด:</div>
                                <div class="detail-value"><?php echo nl2br(htmlspecialchars($queue['description'])); ?></div>
                            </div>
                            
                            <div style="margin-top: 10px; font-size: 0.9em; color: #666;">
                                เพิ่มเมื่อ: <?php echo date('d/m/Y H:i', strtotime($queue['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>