<?php
require_once '../database/config.php';

// Cek login dan role admin
check_login('admin');

$success = '';
$error = '';

// Handle approval (Setujui)
if (isset($_GET['approve']) && isset($_GET['token'])) {
    if (verify_csrf_token($_GET['token'])) {
        $peminjaman_id = (int)$_GET['approve'];
        
        // Mulai transaction
        $conn->begin_transaction();
        
        try {
            // Ambil data peminjaman dengan lock
            $peminjamanQuery = "SELECT p.*, b.jumlah_tersedia, b.nama_barang 
                               FROM peminjaman p 
                               JOIN barang b ON p.barang_id = b.id 
                               WHERE p.id = ? AND p.status = 'Menunggu' 
                               FOR UPDATE";
            $result = db_select($conn, $peminjamanQuery, [$peminjaman_id], "i");
            
            if (!$result || $result->num_rows == 0) {
                throw new Exception('Peminjaman tidak ditemukan atau sudah diproses!');
            }
            
            $peminjaman = $result->fetch_assoc();
            
            // Cek ketersediaan stok
            if ($peminjaman['jumlah_tersedia'] < $peminjaman['jumlah_pinjam']) {
                throw new Exception('Stok tidak mencukupi! Tersedia: ' . $peminjaman['jumlah_tersedia']);
            }
            
            // Kurangi stok barang
            $new_stock = $peminjaman['jumlah_tersedia'] - $peminjaman['jumlah_pinjam'];
            $updateStockSql = "UPDATE barang SET jumlah_tersedia = ? WHERE id = ?";
            if (!db_execute($conn, $updateStockSql, [$new_stock, $peminjaman['barang_id']], "ii")) {
                throw new Exception('Gagal mengupdate stok barang!');
            }
            
            // Update status peminjaman
            $updatePeminjamanSql = "UPDATE peminjaman 
                                   SET status = 'Disetujui', 
                                       approved_by = ?, 
                                       approved_at = NOW() 
                                   WHERE id = ?";
            if (!db_execute($conn, $updatePeminjamanSql, [$_SESSION['user_id'], $peminjaman_id], "ii")) {
                throw new Exception('Gagal mengupdate status peminjaman!');
            }
            
            // Commit transaction
            $conn->commit();
            $_SESSION['success'] = 'Peminjaman berhasil disetujui! Stok barang berkurang ' . $peminjaman['jumlah_pinjam'] . ' unit.';
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }
        
        redirect('admin_approval.php');
    }
}

// Handle reject (Tolak)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid security token!";
    } else {
        $peminjaman_id = (int)$_POST['peminjaman_id'];
        $alasan_tolak = clean_input($_POST['alasan_tolak']);
        
        if (empty($alasan_tolak)) {
            $error = "Alasan penolakan harus diisi!";
        } else {
            $sql = "UPDATE peminjaman 
                   SET status = 'Ditolak', 
                       alasan_tolak = ?, 
                       approved_by = ?, 
                       approved_at = NOW() 
                   WHERE id = ? AND status = 'Menunggu'";
            
            if (db_execute($conn, $sql, [$alasan_tolak, $_SESSION['user_id'], $peminjaman_id], "sii")) {
                $success = 'Peminjaman berhasil ditolak!';
            } else {
                $error = 'Gagal menolak peminjaman!';
            }
        }
    }
}

// Ambil pesan dari session
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Generate CSRF token
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan Peminjaman - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
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
            margin: 10% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
        }
        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
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
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .info-label {
            font-weight: 600;
            color: #666;
        }
        .info-value {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">
            <span></span>
            <span>Sistem Peminjaman Barang - ADMIN</span>
        </div>
        <div class="navbar-user">
            <span><?php echo escape_output($_SESSION['nama_lengkap']); ?></span>
            <a href="../log/logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="menu-item">Dashboard</a>
            <a href="admin_approval.php" class="menu-item active">Persetujuan</a>
            <a href="admin_barang.php" class="menu-item">Data Barang</a>
            <a href="admin_peminjaman.php" class="menu-item">Riwayat Peminjaman</a>
            <a href="admin_users.php" class="menu-item">Pengguna</a>
        </div>
        
        <div class="content">
            <h2 style="margin-bottom: 20px; color: #333;">Persetujuan Peminjaman</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo escape_output($success); ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo escape_output($error); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">Daftar Permohonan Peminjaman</div>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Peminjam</th>
                            <th>Barang</th>
                            <th>Jumlah</th>
                            <th>Tgl Pinjam</th>
                            <th>Keterangan</th>
                            <th>Stok</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.*, u.nama_lengkap, b.nama_barang, b.jumlah_tersedia, b.kode_barang
                               FROM peminjaman p
                               JOIN users u ON p.user_id = u.id
                               JOIN barang b ON p.barang_id = b.id
                               WHERE p.status = 'Menunggu'
                               ORDER BY p.created_at ASC";
                        $result = db_select($conn, $sql);
                        
                        if($result && $result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                                $stok_class = $row['jumlah_tersedia'] >= $row['jumlah_pinjam'] ? 'success' : 'danger';
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                            <td><?php echo escape_output($row['nama_lengkap']); ?></td>
                            <td>
                                <strong><?php echo escape_output($row['nama_barang']); ?></strong><br>
                                <small style="color: #666;"><?php echo escape_output($row['kode_barang']); ?></small>
                            </td>
                            <td><?php echo escape_output($row['jumlah_pinjam']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_pinjam'])); ?></td>
                            <td>
                                <small><?php echo escape_output(substr($row['keterangan'], 0, 50)); ?>...</small>
                            </td>
                            <td>
                                <span class="badge <?php echo $stok_class; ?>">
                                    <?php echo escape_output($row['jumlah_tersedia']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['jumlah_tersedia'] >= $row['jumlah_pinjam']): ?>
                                    <a href="?approve=<?php echo $row['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                       class="btn btn-success btn-sm" 
                                       onclick="return confirm('Setujui peminjaman ini?\n\nStok akan berkurang <?php echo $row['jumlah_pinjam']; ?> unit.')">
                                       ✓ Setujui
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-sm" 
                                            style="background: #ccc; cursor: not-allowed;" 
                                            disabled 
                                            title="Stok tidak mencukupi">
                                        Stok Kurang
                                    </button>
                                <?php endif; ?>
                                
                                <button onclick="showRejectModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['nama_barang'], ENT_QUOTES); ?>')" 
                                        class="btn btn-danger btn-sm">
                                    ✗ Tolak
                                </button>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #999; padding: 40px;">
                                Tidak ada permohonan peminjaman yang menunggu
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Tolak Peminjaman -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <span class="btn-close" onclick="closeRejectModal()">&times;</span>
            <div class="modal-header">Tolak Peminjaman: <span id="modal_barang_name"></span></div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="peminjaman_id" id="reject_id">
                
                <div class="form-group">
                    <label>Alasan Penolakan</label>
                    <textarea name="alasan_tolak" rows="4" required 
                              placeholder="Jelaskan alasan penolakan..."
                              minlength="10"
                              maxlength="500"></textarea>
                    <small style="color: #666;">Minimal 10 karakter</small>
                </div>
                
                <button type="submit" name="reject" class="btn btn-danger" style="width: 100%;">
                    Tolak Peminjaman
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function showRejectModal(id, namaBarang) {
            document.getElementById('reject_id').value = id;
            document.getElementById('modal_barang_name').textContent = namaBarang;
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }
        
        // Close modal saat klik di luar
        window.onclick = function(event) {
            const modal = document.getElementById('rejectModal');
            if (event.target == modal) {
                closeRejectModal();
            }
        }
        
        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>