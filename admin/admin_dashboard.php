<?php
require_once '../database/config.php';

// Cek login dan role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../log/login.php');
    exit();
}

// Get statistics
$total_barang = $conn->query("SELECT COUNT(*) as total FROM barang")->fetch_assoc()['total'];
$total_menunggu = $conn->query("SELECT COUNT(*) as total FROM peminjaman WHERE status='Menunggu'")->fetch_assoc()['total'];
$total_dipinjam = $conn->query("SELECT COUNT(*) as total FROM peminjaman WHERE status='Dipinjam'")->fetch_assoc()['total'];
$total_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='user'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Peminjaman Barang</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">
            <span></span>
            <span>Sistem Peminjaman Barang - ADMIN</span>
        </div>
        <div class="navbar-user">
            <span><?php echo $_SESSION['nama_lengkap']; ?></span>
            <a href="../log/logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="menu-item active">Dashboard</a>
            <a href="admin_approval.php" class="menu-item">Persetujuan 
                <?php if($total_menunggu > 0): ?>
                    <span class="badge-notif"><?php echo $total_menunggu; ?></span>
                <?php endif; ?>
            </a>
            <a href="admin_barang.php" class="menu-item">Data Barang</a>
            <a href="admin_peminjaman.php" class="menu-item">Riwayat Peminjaman</a>
            <a href="admin_users.php" class="menu-item">Pengguna</a>
        </div>
        
        <div class="content">
            <h2 style="margin-bottom: 20px; color: #333;">Dashboard Admin</h2>
            
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-value"><?php echo $total_barang; ?></div>
                    <div class="stat-label">Total Barang</div>
                </div>
                
                <div class="stat-card orange">
                    <div class="stat-value"><?php echo $total_menunggu; ?></div>
                    <div class="stat-label">Menunggu Persetujuan</div>
                </div>
                
                <div class="stat-card green">
                    <div class="stat-value"><?php echo $total_dipinjam; ?></div>
                    <div class="stat-label">Sedang Dipinjam</div>
                </div>
                
                <div class="stat-card purple">
                    <div class="stat-value"><?php echo $total_users; ?></div>
                    <div class="stat-label">Total User</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">Peminjaman Menunggu Persetujuan</div>
                <table>
                    <thead>
                        <tr>
                            <th>Peminjam</th>
                            <th>Barang</th>
                            <th>Tanggal Pinjam</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.*, u.nama_lengkap, b.nama_barang 
                                FROM peminjaman p 
                                JOIN users u ON p.user_id = u.id 
                                JOIN barang b ON p.barang_id = b.id 
                                WHERE p.status = 'Menunggu' 
                                ORDER BY p.created_at DESC
                                LIMIT 5";
                        $result = $conn->query($sql);
                        if($result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $row['nama_lengkap']; ?></td>
                            <td><?php echo $row['nama_barang']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_pinjam'])); ?></td>
                            <td><?php echo $row['jumlah_pinjam']; ?></td>
                            <td><span class="badge warning"><?php echo $row['status']; ?></span></td>
                            <td>
                                <a href="admin_approval.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">Review</a>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999;">Tidak ada peminjaman menunggu</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if($total_menunggu > 5): ?>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="admin_approval.php" class="btn btn-primary">Lihat Semua (<?php echo $total_menunggu; ?>)</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <div class="card-header">Barang Sedang Dipinjam</div>
                <table>
                    <thead>
                        <tr>
                            <th>Peminjam</th>
                            <th>Barang</th>
                            <th>Tanggal Pinjam</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.*, u.nama_lengkap, b.nama_barang 
                                FROM peminjaman p 
                                JOIN users u ON p.user_id = u.id 
                                JOIN barang b ON p.barang_id = b.id 
                                WHERE p.status = 'Dipinjam' 
                                ORDER BY p.tanggal_pinjam DESC
                                LIMIT 5";
                        $result = $conn->query($sql);
                        if($result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $row['nama_lengkap']; ?></td>
                            <td><?php echo $row['nama_barang']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_pinjam'])); ?></td>
                            <td><?php echo $row['jumlah_pinjam']; ?></td>
                            <td><span class="badge success"><?php echo $row['status']; ?></span></td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999;">Tidak ada barang sedang dipinjam</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>