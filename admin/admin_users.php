<?php
require_once '../database/config.php';

// Cek login dan role admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../log/login.php');
    exit();
}

// Handle tambah user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_user'])) {
    $username = clean_input($_POST['username']);
    $password = md5($_POST['password']);
    $nama_lengkap = clean_input($_POST['nama_lengkap']);
    $email = clean_input($_POST['email']);
    $role = clean_input($_POST['role']);
    
    $sql = "INSERT INTO users (username, password, nama_lengkap, email, role) 
            VALUES ('$username', '$password', '$nama_lengkap', '$email', '$role')";
    
    if ($conn->query($sql)) {
        $success = "Pengguna berhasil ditambahkan!";
    } else {
        $error = "Gagal menambahkan pengguna: " . $conn->error;
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pengguna - Admin</title>
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
            <a href="admin_dashboard.php" class="menu-item">Dashboard</a>
            <a href="admin_approval.php" class="menu-item">Persetujuan</a>
            <a href="admin_barang.php" class="menu-item">Data Barang</a>
            <a href="admin_peminjaman.php" class="menu-item">Riwayat Peminjaman</a>
            <a href="admin_users.php" class="menu-item active">Pengguna</a>
        </div>
        
        <div class="content">
            <h2 style="margin-bottom: 20px; color: #333;">Data Pengguna</h2>
            
            <?php if(isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">Tambah Pengguna Baru</div>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" required minlength="6">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="tambah_user" class="btn btn-primary">Tambah Pengguna</button>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">Daftar Pengguna</div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Terdaftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT * FROM users ORDER BY id DESC");
                        if($result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['username']; ?></td>
                            <td><?php echo $row['nama_lengkap']; ?></td>
                            <td><?php echo $row['email']; ?></td>
                            <td>
                                <span class="badge <?php echo $row['role']=='admin'?'info':'secondary'; ?>">
                                    <?php echo strtoupper($row['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                            <td>
                                <?php if($row['id'] != $_SESSION['user_id']): ?>
                                <?php else: ?>
                                    <span style="color: #999;">Akun Anda</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #999;">Belum ada data pengguna</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>