<?php
require_once '../database/config.php';

// Cek login dan role user
check_login('user');

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle batal pesanan
if (isset($_GET['batal']) && isset($_GET['token'])) {
    if (verify_csrf_token($_GET['token'])) {
        $id = (int)$_GET['batal'];
        
        // Cek apakah peminjaman milik user dan statusnya masih menunggu
        $check = db_select(
            $conn, 
            "SELECT id FROM peminjaman WHERE id = ? AND user_id = ? AND status = 'Menunggu'", 
            [$id, $user_id], 
            "ii"
        );
        
        if ($check && $check->num_rows > 0) {
            if (db_execute($conn, "DELETE FROM peminjaman WHERE id = ? AND user_id = ?", [$id, $user_id], "ii")) {
                $_SESSION['success'] = 'Pesanan berhasil dibatalkan!';
            } else {
                $_SESSION['error'] = 'Gagal membatalkan pesanan!';
            }
        } else {
            $_SESSION['error'] = 'Pesanan tidak dapat dibatalkan!';
        }
        
        redirect('user_peminjaman.php');
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
    <title>Peminjaman Saya - User</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .section-divider {
            margin: 30px 0;
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
            <a href="user_katalog.php" class="menu-item">Katalog Barang</a>
            <a href="user_peminjaman.php" class="menu-item active">Peminjaman Saya</a>
        </div>
        
        <div class="content">
            <h2 style="margin-bottom: 20px; color: #333;">Peminjaman Saya</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo escape_output($success); ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo escape_output($error); ?></div>
            <?php endif; ?>
            
            <!-- Menunggu Persetujuan -->
            <div class="card">
                <div class="card-header">Menunggu Persetujuan</div>
                <table>
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Tgl Pinjam</th>
                            <th>Jumlah</th>
                            <th>Keterangan</th>
                            <th>Tanggal Pengajuan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.*, b.nama_barang, b.kode_barang 
                                FROM peminjaman p 
                                JOIN barang b ON p.barang_id = b.id 
                                WHERE p.user_id = ? AND p.status = 'Menunggu' 
                                ORDER BY p.created_at DESC";
                        $result = db_select($conn, $sql, [$user_id], "i");
                        
                        if($result && $result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo escape_output($row['nama_barang']); ?></strong><br>
                                <small style="color: #999;"><?php echo escape_output($row['kode_barang']); ?></small>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_pinjam'])); ?></td>
                            <td><?php echo escape_output($row['jumlah_pinjam']); ?></td>
                            <td>
                                <small><?php echo escape_output(substr($row['keterangan'], 0, 50)); ?><?php echo strlen($row['keterangan']) > 50 ? '...' : ''; ?></small>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                            <td><span class="badge warning">Menunggu</span></td>
                            <td>
                                <a href="?batal=<?php echo $row['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Batalkan pesanan ini?')">
                                    ✗ Batal
                                </a>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #999; padding: 40px;">
                                Tidak ada pesanan menunggu
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="section-divider"></div>
            
            <!-- Disetujui - Siap Diambil -->
            <div class="card">
                <div class="card-header">Disetujui - Siap Diambil</div>
                <table>
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Tgl Pinjam</th>
                            <th>Jumlah</th>
                            <th>Disetujui Oleh</th>
                            <th>Waktu Disetujui</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.*, b.nama_barang, b.kode_barang, u.nama_lengkap as admin_name
                                FROM peminjaman p 
                                JOIN barang b ON p.barang_id = b.id 
                                LEFT JOIN users u ON p.approved_by = u.id
                                WHERE p.user_id = ? AND p.status = 'Disetujui' 
                                ORDER BY p.approved_at DESC";
                        $result = db_select($conn, $sql, [$user_id], "i");
                        
                        if($result && $result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo escape_output($row['nama_barang']); ?></strong><br>
                                <small style="color: #999;"><?php echo escape_output($row['kode_barang']); ?></small>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_pinjam'])); ?></td>
                            <td><?php echo escape_output($row['jumlah_pinjam']); ?></td>
                            <td><?php echo escape_output($row['admin_name'] ?: 'Admin'); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['approved_at'])); ?></td>
                            <td><span class="badge success">Disetujui</span></td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999; padding: 40px;">
                                Tidak ada barang disetujui
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="section-divider"></div>
            
            <!-- Ditolak -->
            <div class="card">
                <div class="card-header">Ditolak</div>
                <table>
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Tgl Pinjam</th>
                            <th>Jumlah</th>
                            <th>Alasan Penolakan</th>
                            <th>Ditolak Oleh</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.*, b.nama_barang, b.kode_barang, u.nama_lengkap as admin_name
                                FROM peminjaman p 
                                JOIN barang b ON p.barang_id = b.id 
                                LEFT JOIN users u ON p.approved_by = u.id
                                WHERE p.user_id = ? AND p.status = 'Ditolak' 
                                ORDER BY p.approved_at DESC";
                        $result = db_select($conn, $sql, [$user_id], "i");
                        
                        if($result && $result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo escape_output($row['nama_barang']); ?></strong><br>
                                <small style="color: #999;"><?php echo escape_output($row['kode_barang']); ?></small>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_pinjam'])); ?></td>
                            <td><?php echo escape_output($row['jumlah_pinjam']); ?></td>
                            <td>
                                <small style="color: #dc2626;">
                                    <?php echo escape_output($row['alasan_tolak']); ?>
                                </small>
                            </td>
                            <td><?php echo escape_output($row['admin_name'] ?: 'Admin'); ?></td>
                            <td><span class="badge danger">Ditolak</span></td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999; padding: 40px;">
                                Tidak ada peminjaman ditolak
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="section-divider"></div>
            
            <!-- Riwayat Lengkap -->
            <div class="card">
                <div class="card-header">📜 Riwayat Lengkap</div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Barang</th>
                            <th>Tgl Pinjam</th>
                            <th>Tgl Kembali</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.*, b.nama_barang 
                                FROM peminjaman p 
                                JOIN barang b ON p.barang_id = b.id 
                                WHERE p.user_id = ? 
                                ORDER BY p.created_at DESC";
                        $result = db_select($conn, $sql, [$user_id], "i");
                        
                        if($result && $result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                                $badge_class = '';
                                switch($row['status']) {
                                    case 'Menunggu': $badge_class = 'warning'; break;
                                    case 'Disetujui': $badge_class = 'success'; break;
                                    case 'Ditolak': $badge_class = 'danger'; break;
                                    case 'Dipinjam': $badge_class = 'info'; break;
                                    case 'Dikembalikan': $badge_class = 'secondary'; break;
                                }
                        ?>
                        <tr>
                            <td><?php echo escape_output($row['id']); ?></td>
                            <td><?php echo escape_output($row['nama_barang']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_pinjam'])); ?></td>
                            <td><?php echo $row['tanggal_kembali'] ? date('d/m/Y', strtotime($row['tanggal_kembali'])) : '-'; ?></td>
                            <td><?php echo escape_output($row['jumlah_pinjam']); ?></td>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo escape_output($row['status']); ?></span></td>
                            <td>
                                <?php 
                                if($row['status'] == 'Ditolak' && $row['alasan_tolak']) {
                                    echo '<small style="color: #dc2626;">' . escape_output($row['alasan_tolak']) . '</small>';
                                } elseif($row['status'] == 'Dikembalikan') {
                                    echo '<small style="color: #6b7280;">Selesai</small>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #999; padding: 40px;">
                                Belum ada riwayat
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="user_katalog.php" class="btn btn-primary" style="padding: 15px 40px; font-size: 16px; border-radius: 10px;">
                    🛒 Pinjam Barang Lagi
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>