<?php
require_once '../database/config.php';

// Cek login dan role admin
check_login('admin');

$success = '';
$error = '';

// Handle pengembalian barang
if (isset($_GET['return']) && isset($_GET['token'])) {
    if (verify_csrf_token($_GET['token'])) {
        $peminjaman_id = (int)$_GET['return'];
        
        // Mulai transaction
        $conn->begin_transaction();
        
        try {
            // Ambil data peminjaman dengan lock
            $peminjamanQuery = "SELECT p.*, b.jumlah_tersedia, b.nama_barang 
                               FROM peminjaman p 
                               JOIN barang b ON p.barang_id = b.id 
                               WHERE p.id = ? AND p.status = 'Disetujui' 
                               FOR UPDATE";
            $result = db_select($conn, $peminjamanQuery, [$peminjaman_id], "i");
            
            if (!$result || $result->num_rows == 0) {
                throw new Exception('Peminjaman tidak ditemukan atau sudah dikembalikan!');
            }
            
            $peminjaman = $result->fetch_assoc();
            
            // Tambah stok barang kembali
            $new_stock = $peminjaman['jumlah_tersedia'] + $peminjaman['jumlah_pinjam'];
            $updateStockSql = "UPDATE barang SET jumlah_tersedia = ? WHERE id = ?";
            if (!db_execute($conn, $updateStockSql, [$new_stock, $peminjaman['barang_id']], "ii")) {
                throw new Exception('Gagal mengupdate stok barang!');
            }
            
            // Update status peminjaman dan tanggal kembali
            $updatePeminjamanSql = "UPDATE peminjaman 
                                   SET status = 'Dikembalikan', 
                                       tanggal_kembali = CURDATE() 
                                   WHERE id = ?";
            if (!db_execute($conn, $updatePeminjamanSql, [$peminjaman_id], "i")) {
                throw new Exception('Gagal mengupdate status peminjaman!');
            }
            
            // Commit transaction
            $conn->commit();
            $_SESSION['success'] = 'Barang berhasil dikembalikan! Stok bertambah ' . $peminjaman['jumlah_pinjam'] . ' unit.';
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = $e->getMessage();
        }
        
        redirect('admin_peminjaman.php');
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

// Filter status
$filter_status = isset($_GET['status']) ? clean_input($_GET['status']) : 'all';
$where_clause = "";
if ($filter_status != 'all') {
    $where_clause = "AND p.status = '" . $conn->real_escape_string($filter_status) . "'";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Peminjaman - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-tab {
            padding: 10px 20px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #666;
            font-weight: 500;
        }
        .filter-tab:hover {
            border-color: #667eea;
            color: #667eea;
        }
        .filter-tab.active {
            background-color: #667eea;
            color: white;
            border-color: #667eea;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
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
            <a href="admin_approval.php" class="menu-item">Persetujuan</a>
            <a href="admin_barang.php" class="menu-item">Data Barang</a>
            <a href="admin_peminjaman.php" class="menu-item active">Riwayat Peminjaman</a>
            <a href="admin_users.php" class="menu-item">Pengguna</a>
        </div>
        
        <div class="content">
            <h2 style="margin-bottom: 20px; color: #333;">Riwayat Peminjaman</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo escape_output($success); ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo escape_output($error); ?></div>
            <?php endif; ?>
            
            <!-- Statistik -->
            <div class="stats-grid">
                <?php
                $stats = [
                    ['label' => 'Menunggu', 'status' => 'Menunggu', 'color' => '#667eea'],
                    ['label' => 'Disetujui', 'status' => 'Disetujui', 'color' => '#667eea'],
                    ['label' => 'Ditolak', 'status' => 'Ditolak', 'color' => '#667eea'],
                    ['label' => 'Dikembalikan', 'status' => 'Dikembalikan', 'color' => '#667eea']
                ];
                
                foreach ($stats as $stat):
                    $count_result = db_select($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE status = ?", [$stat['status']], "s");
                    $count = $count_result ? $count_result->fetch_assoc()['total'] : 0;
                ?>
                <div class="stat-card">
                    <div class="stat-label"><?php echo $stat['label']; ?></div>
                    <div class="stat-value" style="color: <?php echo $stat['color']; ?>">
                        <?php echo $count; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?status=all" class="filter-tab <?php echo $filter_status == 'all' ? 'active' : ''; ?>">
                    Semua
                </a>
                <a href="?status=Menunggu" class="filter-tab <?php echo $filter_status == 'Menunggu' ? 'active' : ''; ?>">
                    Menunggu
                </a>
                <a href="?status=Disetujui" class="filter-tab <?php echo $filter_status == 'Disetujui' ? 'active' : ''; ?>">
                    Disetujui (Dipinjam)
                </a>
                <a href="?status=Ditolak" class="filter-tab <?php echo $filter_status == 'Ditolak' ? 'active' : ''; ?>">
                    Ditolak
                </a>
                <a href="?status=Dikembalikan" class="filter-tab <?php echo $filter_status == 'Dikembalikan' ? 'active' : ''; ?>">
                    Dikembalikan
                </a>
            </div>
            
            <div class="card">
                <div class="card-header">Daftar Peminjaman</div>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal Pengajuan</th>
                            <th>Peminjam</th>
                            <th>Barang</th>
                            <th>Jumlah</th>
                            <th>Tgl Pinjam</th>
                            <th>Tgl Kembali</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.*, u.nama_lengkap, b.nama_barang, b.kode_barang,
                                       admin.nama_lengkap as admin_name
                               FROM peminjaman p
                               JOIN users u ON p.user_id = u.id
                               JOIN barang b ON p.barang_id = b.id
                               LEFT JOIN users admin ON p.approved_by = admin.id
                               WHERE 1=1 $where_clause
                               ORDER BY p.created_at DESC";
                        $result = db_select($conn, $sql);
                        
                        if($result && $result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                                // Tentukan class badge
                                $badge_class = 'warning';
                                if ($row['status'] == 'Disetujui') $badge_class = 'success';
                                elseif ($row['status'] == 'Ditolak') $badge_class = 'danger';
                                elseif ($row['status'] == 'Dikembalikan') $badge_class = 'info';
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
                                <?php 
                                if ($row['tanggal_kembali']) {
                                    echo date('d/m/Y', strtotime($row['tanggal_kembali']));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo escape_output($row['status']); ?>
                                </span>
                                <?php if ($row['approved_by']): ?>
                                    <br><small style="color: #666;">oleh: <?php echo escape_output($row['admin_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'Disetujui'): ?>
                                    <a href="?return=<?php echo $row['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                       class="btn btn-success btn-sm" 
                                       onclick="return confirm('Konfirmasi pengembalian barang?\n\nStok akan bertambah <?php echo $row['jumlah_pinjam']; ?> unit.')">
                                       Kembalikan
                                    </a>
                                <?php elseif ($row['status'] == 'Ditolak'): ?>
                                    <small style="color: #666;">
                                        Alasan: <?php echo escape_output($row['alasan_tolak']); ?>
                                    </small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #999; padding: 40px;">
                                Tidak ada data peminjaman
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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