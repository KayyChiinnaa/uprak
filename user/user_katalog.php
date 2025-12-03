<?php
require_once '../database/config.php';

// Cek login dan role user
check_login('user');

$success = '';
$error = '';

// Handle pesan barang
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pesan_barang'])) {
    // Validasi CSRF Token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid security token!";
    } else {
        $barang_id = (int)$_POST['barang_id'];
        $tanggal_pinjam = clean_input($_POST['tanggal_pinjam']);
        $jumlah = (int)$_POST['jumlah_pinjam'];
        $keterangan = clean_input($_POST['keterangan']);
        $user_id = $_SESSION['user_id'];
        
        // Validasi input
        if (!validate_date($tanggal_pinjam)) {
            $error = 'Format tanggal tidak valid!';
        } elseif (strtotime($tanggal_pinjam) < strtotime(date('Y-m-d'))) {
            $error = 'Tanggal pinjam tidak boleh di masa lalu!';
        } elseif ($jumlah <= 0) {
            $error = 'Jumlah pinjam harus lebih dari 0!';
        } elseif (empty($keterangan)) {
            $error = 'Keterangan wajib diisi!';
        } else {
            // Gunakan transaction untuk menghindari race condition
            $conn->begin_transaction();
            
            try {
                // Lock row untuk mencegah race condition
                $barang_query = "SELECT jumlah_tersedia, nama_barang FROM barang WHERE id = ? FOR UPDATE";
                $barang_result = db_select($conn, $barang_query, [$barang_id], "i");
                
                if (!$barang_result || $barang_result->num_rows == 0) {
                    throw new Exception('Barang tidak ditemukan!');
                }
                
                $barang = $barang_result->fetch_assoc();
                
                // Cek ketersediaan
                if ($barang['jumlah_tersedia'] < $jumlah) {
                    throw new Exception('Jumlah barang tidak mencukupi! Tersedia: ' . $barang['jumlah_tersedia']);
                }
                
                // Cek apakah user sudah punya peminjaman yang sedang menunggu untuk barang ini
                $check_pending = db_select(
                    $conn,
                    "SELECT id FROM peminjaman WHERE user_id = ? AND barang_id = ? AND status = 'Menunggu'",
                    [$user_id, $barang_id],
                    "ii"
                );
                
                if ($check_pending && $check_pending->num_rows > 0) {
                    throw new Exception('Anda sudah memiliki pesanan yang sedang menunggu untuk barang ini!');
                }
                
                // Insert peminjaman
                $sql = "INSERT INTO peminjaman (user_id, barang_id, tanggal_pinjam, jumlah_pinjam, keterangan, status) 
                        VALUES (?, ?, ?, ?, ?, 'Menunggu')";
                
                if (!db_execute($conn, $sql, [$user_id, $barang_id, $tanggal_pinjam, $jumlah, $keterangan], "iisis")) {
                    throw new Exception('Gagal mengirim pesanan!');
                }
                
                // Commit transaction
                $conn->commit();
                $success = 'Pesanan berhasil dikirim! Menunggu persetujuan admin.';
                
            } catch (Exception $e) {
                // Rollback jika ada error
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katalog Barang - User</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .catalog-item {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        .catalog-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .item-icon {
            width: 60px;
            height: 60px;
            background-color: #667eea;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            margin-bottom: 15px;
        }
        .item-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .item-info {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .item-stock {
            display: inline-block;
            padding: 5px 12px;
            background: #d1fae5;
            color: #065f46;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 10px 0;
        }
        .item-stock.low {
            background: #fef3c7;
            color: #92400e;
        }
        .item-stock.out {
            background: #fee2e2;
            color: #991b1b;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .btn-close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        .btn-close:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">
            <span></span>
            <span>Sistem Peminjaman Barang</span>
        </div>
        <div class="navbar-user">
            <span><?php echo escape_output($_SESSION['nama_lengkap']); ?></span>
            <a href="../log/logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="user_dashboard.php" class="menu-item">Dashboard</a>
            <a href="user_katalog.php" class="menu-item active">Katalog Barang</a>
            <a href="user_peminjaman.php" class="menu-item">Peminjaman Saya</a>
        </div>
        
        <div class="content">
            <h2 style="margin-bottom: 20px; color: #333;">Katalog Barang</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo escape_output($success); ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo escape_output($error); ?></div>
            <?php endif; ?>
            
            <div class="catalog-grid">
                <?php
                $result = db_select($conn, "SELECT * FROM barang WHERE kondisi != 'Rusak Berat' ORDER BY nama_barang ASC");
                if ($result && $result->num_rows > 0):
                    while($barang = $result->fetch_assoc()):
                        // Tentukan class stock
                        $stock_class = '';
                        if ($barang['jumlah_tersedia'] == 0) {
                            $stock_class = 'out';
                        } elseif ($barang['jumlah_tersedia'] <= 3) {
                            $stock_class = 'low';
                        }
                        
                        // Icon default
                        $icon = '📦';
                ?>
                <div class="catalog-item">
                    <div class="item-icon"><?php echo $icon; ?></div>
                    <div class="item-name"><?php echo escape_output($barang['nama_barang']); ?></div>
                    <div class="item-info">Kode: <?php echo escape_output($barang['kode_barang']); ?></div>
                    <div class="item-info">Kondisi: <?php echo escape_output($barang['kondisi']); ?></div>
                    <div class="item-stock <?php echo $stock_class; ?>">
                        Tersedia: <?php echo escape_output($barang['jumlah_tersedia']); ?>
                    </div>
                    <br>
                    <?php if($barang['jumlah_tersedia'] > 0): ?>
                        <button class="btn btn-primary" style="width: 100%; margin-top: 10px;" 
                                onclick="showPesanModal(<?php echo $barang['id']; ?>, '<?php echo htmlspecialchars($barang['nama_barang'], ENT_QUOTES); ?>', <?php echo $barang['jumlah_tersedia']; ?>)">
                            Pinjam Sekarang
                        </button>
                    <?php else: ?>
                        <button class="btn" style="width: 100%; margin-top: 10px; background: #ccc; cursor: not-allowed;" disabled>
                            Tidak Tersedia
                        </button>
                    <?php endif; ?>
                </div>
                <?php 
                    endwhile;
                else:
                ?>
                <div style="grid-column: 1/-1; text-align: center; color: #999; padding: 40px;">
                    Belum ada barang tersedia
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Pesan Barang -->
    <div id="pesanModal" class="modal">
        <div class="modal-content">
            <span class="btn-close" onclick="closePesanModal()">&times;</span>
            <div class="modal-header">Pinjam Barang: <span id="modal_barang_name"></span></div>
            <form method="POST" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="barang_id" id="modal_barang_id">
                
                <div class="form-group">
                    <label>Tanggal Pinjam</label>
                    <input type="date" name="tanggal_pinjam" id="modal_tanggal" required 
                           value="<?php echo date('Y-m-d'); ?>" 
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Jumlah Pinjam</label>
                    <input type="number" name="jumlah_pinjam" id="modal_jumlah" required min="1">
                    <small style="color: #666;">Tersedia: <span id="modal_stock"></span></small>
                </div>
                
                <div class="form-group">
                    <label>Keperluan / Keterangan</label>
                    <textarea name="keterangan" rows="3" required 
                              placeholder="Jelaskan untuk keperluan apa..."
                              minlength="10"
                              maxlength="500"></textarea>
                    <small style="color: #666;">Minimal 10 karakter</small>
                </div>
                
                <button type="submit" name="pesan_barang" class="btn btn-primary" style="width: 100%;">
                    Kirim Pesanan
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function showPesanModal(id, nama, stock) {
            document.getElementById('modal_barang_id').value = id;
            document.getElementById('modal_barang_name').textContent = nama;
            document.getElementById('modal_stock').textContent = stock;
            document.getElementById('modal_jumlah').max = stock;
            document.getElementById('modal_jumlah').value = 1;
            document.getElementById('pesanModal').style.display = 'block';
        }
        
        function closePesanModal() {
            document.getElementById('pesanModal').style.display = 'none';
        }
        
        function validateForm() {
            const jumlah = parseInt(document.getElementById('modal_jumlah').value);
            const stock = parseInt(document.getElementById('modal_stock').textContent);
            
            if (jumlah > stock) {
                alert('Jumlah pinjam melebihi stok tersedia!');
                return false;
            }
            
            const tanggal = document.getElementById('modal_tanggal').value;
            const today = new Date().toISOString().split('T')[0];
            
            if (tanggal < today) {
                alert('Tanggal pinjam tidak boleh di masa lalu!');
                return false;
            }
            
            return true;
        }
        
        // Close modal saat klik di luar
        window.onclick = function(event) {
            const modal = document.getElementById('pesanModal');
            if (event.target == modal) {
                closePesanModal();
            }
        }
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>