<?php
require_once '../database/config.php';

// Cek login dan role admin
check_login('admin');

$success = '';
$error = '';

// Handle tambah/update barang
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_barang'])) {
    // Validasi CSRF Token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid security token!";
    } else {
        $kode = clean_input($_POST['kode_barang']);
        $nama = clean_input($_POST['nama_barang']);
        $jumlah = (int)$_POST['jumlah_tersedia'];
        $kondisi = clean_input($_POST['kondisi']);
        
        // Validasi input
        if (empty($kode) || empty($nama)) {
            $error = "Semua field harus diisi!";
        } elseif ($jumlah < 0) {
            $error = "Jumlah tidak boleh negatif!";
        } elseif (!in_array($kondisi, ['Baik', 'Rusak Ringan', 'Rusak Berat'])) {
            $error = "Kondisi tidak valid!";
        } else {
            // Cek apakah ada barang dengan spesifikasi yang sama (nama, kondisi)
            $check = db_select(
                $conn, 
                "SELECT id, jumlah_tersedia FROM barang WHERE nama_barang = ? AND kondisi = ?", 
                [$nama, $kondisi], 
                "ss"
            );
            
            if ($check && $check->num_rows > 0) {
                // Barang sudah ada, tambahkan jumlahnya
                $existing = $check->fetch_assoc();
                $new_jumlah = $existing['jumlah_tersedia'] + $jumlah;
                
                $sql = "UPDATE barang SET jumlah_tersedia = ?, kode_barang = ? WHERE id = ?";
                
                if (db_execute($conn, $sql, [$new_jumlah, $kode, $existing['id']], "isi")) {
                    $success = "Barang sudah ada! Stok berhasil ditambahkan. Total sekarang: " . $new_jumlah;
                } else {
                    $error = "Gagal menambahkan stok barang!";
                }
            } else {
                // Barang baru, insert
                $sql = "INSERT INTO barang (kode_barang, nama_barang, jumlah_tersedia, kondisi) 
                        VALUES (?, ?, ?, ?)";
                
                if (db_execute($conn, $sql, [$kode, $nama, $jumlah, $kondisi], "ssis")) {
                    $success = "Barang baru berhasil ditambahkan!";
                } else {
                    $error = "Gagal menambahkan barang!";
                }
            }
        }
    }
}

// Handle edit barang
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_barang'])) {
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid security token!";
    } else {
        $id = (int)$_POST['id'];
        $kode = clean_input($_POST['kode_barang']);
        $nama = clean_input($_POST['nama_barang']);
        $jumlah = (int)$_POST['jumlah_tersedia'];
        $kondisi = clean_input($_POST['kondisi']);
        
        // Validasi input
        if (empty($kode) || empty($nama)) {
            $error = "Semua field harus diisi!";
        } elseif ($jumlah < 0) {
            $error = "Jumlah tidak boleh negatif!";
        } elseif (!in_array($kondisi, ['Baik', 'Rusak Ringan', 'Rusak Berat'])) {
            $error = "Kondisi tidak valid!";
        } else {
            // Cek jumlah yang sedang dipinjam
            $check_pinjam = db_select(
                $conn,
                "SELECT COALESCE(SUM(jumlah_pinjam), 0) as total_pinjam 
                 FROM peminjaman 
                 WHERE barang_id = ? AND status IN ('Disetujui', 'Dipinjam')",
                [$id],
                "i"
            );
            
            $total_pinjam = 0;
            if ($check_pinjam && $check_pinjam->num_rows > 0) {
                $row = $check_pinjam->fetch_assoc();
                $total_pinjam = $row['total_pinjam'];
            }
            
            if ($jumlah < $total_pinjam) {
                $error = "Jumlah tidak boleh kurang dari yang sedang dipinjam ($total_pinjam unit)!";
            } else {
                $sql = "UPDATE barang SET kode_barang = ?, nama_barang = ?, jumlah_tersedia = ?, kondisi = ? WHERE id = ?";
                
                if (db_execute($conn, $sql, [$kode, $nama, $jumlah, $kondisi, $id], "ssisi")) {
                    $success = "Barang berhasil diupdate!";
                } else {
                    $error = "Gagal mengupdate barang!";
                }
            }
        }
    }
}

// Handle hapus barang
if (isset($_GET['hapus']) && isset($_GET['token'])) {
    if (verify_csrf_token($_GET['token'])) {
        $id = (int)$_GET['hapus'];
        
        // Cek apakah barang sedang dipinjam
        $check_peminjaman = db_select(
            $conn, 
            "SELECT id FROM peminjaman WHERE barang_id = ? AND status IN ('Menunggu', 'Disetujui', 'Dipinjam')", 
            [$id], 
            "i"
        );
        
        if ($check_peminjaman && $check_peminjaman->num_rows > 0) {
            $_SESSION['error'] = "Barang tidak dapat dihapus karena sedang dalam proses peminjaman!";
        } else {
            if (db_execute($conn, "DELETE FROM barang WHERE id = ?", [$id], "i")) {
                $_SESSION['success'] = "Barang berhasil dihapus!";
            } else {
                $_SESSION['error'] = "Gagal menghapus barang!";
            }
        }
        redirect('admin_barang.php');
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
    <title>Data Barang - Admin</title>
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
            margin: 3% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            overflow-y: auto;
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
        .btn-group {
            display: flex;
            gap: 10px;
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
            <a href="admin_barang.php" class="menu-item active">Data Barang</a>
            <a href="admin_peminjaman.php" class="menu-item">Riwayat Peminjaman</a>
            <a href="admin_users.php" class="menu-item">Pengguna</a>
        </div>
        
        <div class="content">
            <h2 style="margin-bottom: 20px; color: #333;">Data Barang</h2>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo escape_output($success); ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo escape_output($error); ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">Tambah Barang Baru</div>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kode Barang</label>
                            <input type="text" name="kode_barang" required 
                                   placeholder="Contoh: BRG001"
                                   pattern="[A-Z0-9]+"
                                   title="Hanya huruf kapital dan angka"
                                   maxlength="20">
                        </div>
                        
                        <div class="form-group">
                            <label>Nama Barang</label>
                            <input type="text" name="nama_barang" required 
                                   placeholder="Contoh: Laptop Dell"
                                   maxlength="100">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Jumlah Tersedia</label>
                        <input type="number" name="jumlah_tersedia" required 
                               min="1" max="9999" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Kondisi</label>
                        <select name="kondisi" required>
                            <option value="Baik">Baik</option>
                            <option value="Rusak Ringan">Rusak Ringan</option>
                            <option value="Rusak Berat">Rusak Berat</option>
                        </select>
                    </div>
                    
                    <div style="background: #f0f9ff; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #3b82f6;">
                        <strong>Catatan:</strong> Jika barang dengan nama dan kondisi yang sama sudah ada, jumlah akan otomatis ditambahkan ke stok yang ada.
                    </div>
                    
                    <button type="submit" name="tambah_barang" class="btn btn-primary">
                        Tambah Barang
                    </button>
                </form>
            </div>
            
            <div class="card">
                <div class="card-header">Daftar Barang</div>
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Barang</th>
                            <th>Jumlah</th>
                            <th>Kondisi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = db_select($conn, "SELECT * FROM barang ORDER BY id DESC");
                        if($result && $result->num_rows > 0):
                            while($row = $result->fetch_assoc()):
                                // Tentukan class badge berdasarkan kondisi
                                $badge_class = 'success';
                                if ($row['kondisi'] == 'Rusak Ringan') {
                                    $badge_class = 'warning';
                                } elseif ($row['kondisi'] == 'Rusak Berat') {
                                    $badge_class = 'danger';
                                }
                        ?>
                        <tr>
                            <td><?php echo escape_output($row['kode_barang']); ?></td>
                            <td><?php echo escape_output($row['nama_barang']); ?></td>
                            <td><?php echo escape_output($row['jumlah_tersedia']); ?></td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo escape_output($row['kondisi']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <button onclick="showEditModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES); ?>)" 
                                            class="btn btn-primary btn-sm">
                                        Edit
                                    </button>
                                    <a href="?hapus=<?php echo $row['id']; ?>&token=<?php echo $csrf_token; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Yakin hapus barang ini?')">
                                       Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999;">
                                Belum ada data barang
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit Barang -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="btn-close" onclick="closeEditModal()">&times;</span>
            <div class="modal-header">Edit Data Barang</div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Kode Barang</label>
                        <input type="text" name="kode_barang" id="edit_kode" required 
                               pattern="[A-Z0-9]+"
                               title="Hanya huruf kapital dan angka"
                               maxlength="20">
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Barang</label>
                        <input type="text" name="nama_barang" id="edit_nama" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label>Jumlah Tersedia</label>
                        <input type="number" name="jumlah_tersedia" id="edit_jumlah" required min="0" max="9999">
                        <small style="color: #666;" id="warning_pinjam"></small>
                    </div>
                
                <div class="form-group">
                    <label>Kondisi</label>
                    <select name="kondisi" id="edit_kondisi" required>
                        <option value="Baik">Baik</option>
                        <option value="Rusak Ringan">Rusak Ringan</option>
                        <option value="Rusak Berat">Rusak Berat</option>
                    </select>
                </div>
                
                <button type="submit" name="edit_barang" class="btn btn-primary" style="width: 100%;">
                    Update Barang
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function showEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_kode').value = data.kode_barang;
            document.getElementById('edit_nama').value = data.nama_barang;
            document.getElementById('edit_jumlah').value = data.jumlah_tersedia;
            document.getElementById('edit_kondisi').value = data.kondisi;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal saat klik di luar
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
        
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>