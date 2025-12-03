<?php
require_once '../database/config.php';

// Cek login dan role user
check_login('user');

$user_id = $_SESSION['user_id'];

// Statistics untuk user dengan prepared statements
$total_dipinjam = 0;
$result = db_select($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE user_id = ? AND status = 'Disetujui'", [$user_id], "i");
if ($result) {
    $total_dipinjam = $result->fetch_assoc()['total'];
}

$total_menunggu = 0;
$result = db_select($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE user_id = ? AND status = 'Menunggu'", [$user_id], "i");
if ($result) {
    $total_menunggu = $result->fetch_assoc()['total'];
}

$total_riwayat = 0;
$result = db_select($conn, "SELECT COUNT(*) as total FROM peminjaman WHERE user_id = ?", [$user_id], "i");
if ($result) {
    $total_riwayat = $result->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard User - Sistem Peminjaman Barang</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .welcome-banner {
            background-color: #667eea;
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }
        .welcome-banner h2 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .welcome-banner p {
            margin: 0;
            opacity: 0.9;
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
            <a href="user_dashboard.php" class="menu-item active">Dashboard</a>
            <a href="user_katalog.php" class="menu-item">Katalog Barang</a>
            <a href="user_peminjaman.php" class="menu-item">Peminjaman Saya</a>
        </div>
        
        <div class="content">
            <div class="welcome-banner">
                <h2>Selamat Datang, <?php echo escape_output($_SESSION['nama_lengkap']); ?>!</h2>
                <p>Kelola peminjaman barang Anda dengan mudah</p>
            </div>
            
            
            <div class="card">
                <div class="card-header">Barang Sedang Dipinjam</div>
                <table>
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Tanggal Pinjam</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.*, b.nama_barang, b.kode_barang 
                                FROM peminjaman p 
                                JOIN barang b ON p.barang_id = b.id 
                                WHERE p.user_id = ? AND p.status = 'Disetujui' 
                                ORDER BY p.tanggal_pinjam DESC";
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
                            <td><span class="badge success"><?php echo escape_output($row['status']); ?></span></td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #999; padding: 40px;">
                                Tidak ada barang yang sedang dipinjam
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card">
                <div class="card-header">Status Peminjaman Terbaru</div>
                <table>
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Tanggal</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.*, b.nama_barang 
                                FROM peminjaman p 
                                JOIN barang b ON p.barang_id = b.id 
                                WHERE p.user_id = ? 
                                ORDER BY p.created_at DESC
                                LIMIT 5";
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
                            <td><?php echo escape_output($row['nama_barang']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_pinjam'])); ?></td>
                            <td><?php echo escape_output($row['jumlah_pinjam']); ?></td>
                            <td><span class="badge <?php echo $badge_class; ?>"><?php echo escape_output($row['status']); ?></span></td>
                            <td>
                                <?php 
                                if($row['status'] == 'Ditolak' && $row['alasan_tolak']) {
                                    echo '<small style="color: #dc2626;">' . escape_output($row['alasan_tolak']) . '</small>';
                                } elseif($row['status'] == 'Disetujui') {
                                    echo '<small style="color: #10b981;">✓ Silakan ambil barang</small>';
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
                            <td colspan="5" style="text-align: center; color: #999; padding: 40px;">
                                Belum ada riwayat peminjaman
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="user_katalog.php" class="btn btn-primary" style="padding: 15px 40px; font-size: 16px; border-radius: 10px;">
                     Lihat Katalog Barang
                </a>
            </div>
        </div>
    </div>
</body>
</html>