-- Database untuk sistem peminjaman barang
CREATE DATABASE IF NOT EXISTS db_peminjaman;
USE db_peminjaman;

-- Tabel pengguna
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel barang (tanpa kolom kategori)
CREATE TABLE barang (
    id INT PRIMARY KEY AUTO_INCREMENT,
    kode_barang VARCHAR(20) UNIQUE NOT NULL,
    nama_barang VARCHAR(100) NOT NULL,
    jumlah_tersedia INT DEFAULT 0,
    kondisi ENUM('Baik', 'Rusak Ringan', 'Rusak Berat') DEFAULT 'Baik',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel peminjaman dengan status persetujuan
CREATE TABLE peminjaman (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    barang_id INT NOT NULL,
    tanggal_pinjam DATE NOT NULL,
    tanggal_kembali DATE,
    jumlah_pinjam INT NOT NULL,
    status ENUM('Menunggu', 'Disetujui', 'Ditolak', 'Dipinjam', 'Dikembalikan') DEFAULT 'Menunggu',
    keterangan TEXT,
    alasan_tolak TEXT,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (barang_id) REFERENCES barang(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Insert data dummy
INSERT INTO users (username, password, nama_lengkap, email, role) VALUES 
('admin', MD5('admin123'), 'Administrator', 'admin@example.com', 'admin'),
('user', MD5('user123'), 'User Demo', 'user@example.com', 'user');

-- Insert barang dummy (tanpa kategori)
INSERT INTO barang (kode_barang, nama_barang, jumlah_tersedia, kondisi) VALUES
('BRG001', 'Laptop Dell Latitude 7490', 5, 'Baik'),
('BRG002', 'Proyektor Epson EB-X41', 3, 'Baik'),
('BRG003', 'Kursi Rapat Plastik', 20, 'Baik'),
('BRG004', 'Whiteboard 120x80cm', 10, 'Baik'),
('BRG005', 'Kamera Canon EOS 80D', 2, 'Baik'),
('BRG006', 'Microphone Wireless Shure', 4, 'Baik'),
('BRG007', 'Printer HP LaserJet Pro', 3, 'Baik'),
('BRG008', 'Speaker Aktif Yamaha', 6, 'Baik'),
('BRG009', 'Meja Lipat 180x60cm', 15, 'Baik'),
('BRG010', 'LCD Monitor 24 inch', 8, 'Baik');