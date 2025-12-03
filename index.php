<?php
require_once 'database/config.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    header('Location:log/login.php');
    exit();
}

// Get statistics
$total_barang = $conn->query("SELECT COUNT(*) as total FROM barang")->fetch_assoc()['total'];
$total_dipinjam = $conn->query("SELECT COUNT(*) as total FROM peminjaman WHERE status='Dipinjam'")->fetch_assoc()['total'];
$total_users = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Peminjaman Barang</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-brand">
            <span></span>
            <span>Sistem Peminjaman Barang</span>
        </div>
        <div class="navbar-user">
            <span>Halo, <?php echo $_SESSION['nama_lengkap']; ?></span>
            <a href="log/logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="index.php" class="menu-item active">Dashboard</a>
            <a href="barang.php" class="menu-item">Data Barang</a>
            <a href="peminjaman.php" class="menu-item">Peminjaman</a>
            <a href="users.php" class="menu-item">Pengguna</a>
        </div>
        
        <div class="content">
            <h2 style="margin-bottom: 20px; color: #333;">Dashboard Overview</h2>
            
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon"></div>
                    <div class="stat-value"><?php echo $total_barang; ?></div>
                    <div class="stat-label">Total Barang</div>
                </div>
                
                <div class="stat-card green">
                    <div class="stat-icon"></div>
                    <div class="stat-value"><?php echo $total_dipinjam; ?></div>
                    <div class="stat-label">Sedang Dipinjam</div>
                </div>
                
                <div class="stat-card purple">
                    <div class="stat-icon"></div>
                    <div class="stat-value"><?php echo $total_users; ?></div>
                    <div class="stat-label">Total Pengguna</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">Peminjaman Aktif</div>
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
                                ORDER BY p.created_at DESC";
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
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999;">Tidak ada peminjaman aktif</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>