<?php
class StockManager {
    private $filePath;
    private $data = [];

    public function __construct($filePath) {
        $this->filePath = $filePath;
        $this->initDB();
        $this->loadData();
    }

    // Menginisialisasi file jika belum ada
    private function initDB() {
        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode([]));
        }
    }

    // Membaca data dari JSON ke properti class
    private function loadData() {
        $json = file_get_contents($this->filePath);
        $this->data = json_decode($json, true) ?? [];
    }

    // Menyimpan data dari properti class ke JSON
    private function saveData() {
        file_put_contents($this->filePath, json_encode(array_values($this->data), JSON_PRETTY_PRINT));
    }

    // --- CRUD METHODS ---
    public function getAll() {
        return $this->data;
    }

    public function addItem($nama, $stok, $harga) {
        if($stok < 0 || $harga < 0) {
            return false;
        }
        $newItem = [
            'id' => time(),
            'nama' => $nama,
            'stok' => $stok,
            'harga' => $harga
        ];
        $this->data[] = $newItem;
        $this->saveData();
        return true;
    }

    public function updateItem($id, $nama, $stok, $harga) {
        if($stok < 0 || $harga <0){
            return false;
        }
        foreach ($this->data as &$item) {
            if ($item['id'] == $id) {
                $item['nama'] = $nama;
                $item['stok'] = $stok;
                $item['harga'] = $harga;
                break;
            }
        }
        $this->saveData();
        return true;
    }

    public function deleteItem($id) {
        $this->data = array_filter($this->data, function($item) use ($id) {
            return $item['id'] != $id;
        });
        $this->saveData();
    }

    public function getItemById($id) {
        foreach ($this->data as $item) {
            if ($item['id'] == $id) {
                return $item;
            }
        }
        return null;
    }

    public function searchItems($keyword) {
        if (empty($keyword)) return $this->data;
        
        return array_filter($this->data, function($item) use ($keyword) {
            return strpos(strtolower($item['nama']), strtolower($keyword)) !== false;
        });
    }
}

// --- INISIALISASI OBJEK ---
$manager = new StockManager('data.json');

// Variabel untuk View
$editMode = false;
$editData = ['id' => '', 'nama' => '', 'stok' => '', 'harga' => ''];
$searchKeyword = '';

// --- CONTROLLER LOGIC ---
// 1. Handle POST (Create / Update)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = htmlspecialchars($_POST['nama']);
    $stok = intval($_POST['stok']);
    $harga = intval($_POST['harga']);
    $id = $_POST['id'];

   if ($stok < 0 || $harga <0 ) {
        $errorMessage = "⚠️ *Gagal!* Stok atau Harga tidak boleh bernilai negatif.";

        if ($id) {
            $editMode = true;
            $editData = ['id' => $id, 'nama' => $nama, 'stok' => $stok, 'harga' => $harga];
        } else {
            $editData = ['id' => '', 'nama' => $nama, 'stok' => $stok, 'harga' => $harga];
        }
    } else {
        if ($id) {
            $manager->updateItem($id, $nama, $stok, $harga);
        } else {
            $manager->addItem($nama, $stok, $harga);
        }
        
        header("Location: index.php");
        exit();
    }
}

// 2. Handle GET Actions (Delete / Edit / Search)
if (isset($_GET['op'])) {
    $op = $_GET['op'];
    $id = $_GET['id'] ?? null;

    if ($op == 'delete' && $id) {
        $manager->deleteItem($id);
        header("Location: index.php");
        exit();
    }
    
    if ($op == 'edit' && $id) {
        $foundItem = $manager->getItemById($id);
        if ($foundItem) {
            $editMode = true;
            $editData = $foundItem;
        }
    }
}

// 3. Prepare Data for View
if (isset($_GET['q'])) {
    $searchKeyword = $_GET['q'];
    $dataBarang = $manager->searchItems($searchKeyword);
} else {
    $dataBarang = $manager->getAll();
}

$totalItem = count($dataBarang);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* CSS STYLING */
        :root {
            --primary: #4F46E5;   
            --primary-hover: #4338ca;
            --bg-body: #F3F4F6;   
            --bg-card: #FFFFFF;
            --text-dark: #1F2937;
            --text-light: #6B7280;
            --border: #E5E7EB;
            --danger: #EF4444;
            --success: #10B981;
            --warning: #F59E0B;
            --radius: 12px;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-body); 
            color: var(--text-dark); 
            padding-bottom: 50px; 
            overflow-x: hidden; 
            font-size: 16px; 
        }
        
        .alert-box {
            padding: 15px 25px;
            margin-bottom: 20px;
            border-radius: var(--radius);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .alert-error {
            background-color: #FEE2E2; 
            color: #991B1B;
            border: 1px solid #FCA5A5;
        }

        /* --- HEADER & NAVBAR --- */
        .navbar {
            background: var(--bg-card);
            padding: 1rem 2rem; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative; z-index: 10;
        }
        .navbar h1 { font-size: 1.5rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 10px; }
        .navbar .container { width: 100%; max-width: 100%; padding: 0; margin: 0; }

        /* --- BRAND SLIDER --- */
        .slider-area {
            background: white; padding: 15px 0; border-bottom: 1px solid var(--border);
            margin-bottom: 2rem; overflow: hidden; white-space: nowrap;
        }
        .slider-track { display: inline-block; animation: scroll 40s linear infinite; }
        .brand-box {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 8px 25px; margin: 0 30px; 
            background: #F9FAFB; border: 1px solid var(--border);
            border-radius: 50px; color: var(--text-light);
            font-weight: 600; font-size: 0.95rem; transition: 0.3s;
        }
        .brand-box:hover { color: var(--primary); border-color: var(--primary); background: #EEF2FF; transform: scale(1.05); }
        @keyframes scroll { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

        /* --- LAYOUT UTAMA --- */
        .container { 
            max-width: 98%; 
            margin: 0 auto; 
            padding: 0 20px; 
        }
        
        .stats-grid {
            display: flex; gap: 25px; margin-bottom: 30px; flex-wrap: wrap; 
        }

        .stat-card {
            flex: 1; min-width: 250px;
            background: var(--bg-card); padding: 25px;
            border-radius: var(--radius); box-shadow: var(--shadow);
            display: flex; align-items: center; gap: 20px;
        }
        
        .search-box-header {
            flex: 4; min-width: 350px;
            background: var(--bg-card); padding: 20px;
            border-radius: var(--radius); box-shadow: var(--shadow);
            display: flex; gap: 15px; align-items: center;
        }
        .search-input-header {
            flex: 1; padding: 12px 15px;
            border: 1px solid var(--border); background: #F9FAFB;
            border-radius: 8px; outline: none; font-size: 1rem;
        }
        .search-input-header:focus { border-color: var(--primary); }
        .btn-reset { background: #E5E7EB; color: #374151; padding: 12px; border-radius: 8px; display: flex; align-items: center; text-decoration: none;}

        .stat-icon { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .icon-blue { background: #E0E7FF; color: var(--primary); }
        .stat-info h3 { font-size: 1rem; color: var(--text-light); font-weight: 500; margin-bottom: 5px; }
        .stat-info p { font-size: 2rem; font-weight: 700; color: var(--text-dark); line-height: 1; }

        /* --- GRID CONTENT (FORM & TABLE) --- */
        .content-grid {
            display: grid;
            grid-template-columns: 350px 1fr; 
            gap: 30px;
        }
        @media (max-width: 1200px) { 
            .content-grid { grid-template-columns: 1fr; }
            .stats-grid { flex-direction: column; align-items: stretch; }
        }

        .card { background: var(--bg-card); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid var(--border); font-weight: 700; font-size: 1.15rem; }
        .card-body { padding: 25px; }

        /* --- FORM STYLING --- */
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 0.95rem; font-weight: 600; margin-bottom: 8px; color: var(--text-dark); }
        .form-input {
            width: 100%; padding: 12px 15px;
            border: 1px solid var(--border); border-radius: 8px;
            font-size: 1rem; outline: none; transition: 0.2s;
        }
        .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        
        .btn {
            display: inline-block; width: 100%; padding: 14px;
            border: none; border-radius: 8px;
            font-weight: 600; cursor: pointer; transition: 0.2s;
            text-align: center; text-decoration: none; font-size: 1rem;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: #9CA3AF; color: white; margin-top: 10px; }
        .btn-search { width: auto; padding: 12px 25px; background: var(--text-dark); color: white; }

        /* --- TABLE STYLING --- */
        table { width: 100%; border-collapse: collapse; }
        
        th { 
            text-align: left; padding: 18px 20px; 
            background: #F9FAFB; border-bottom: 1px solid var(--border); 
            font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light); 
        }
        
        td { 
            padding: 18px 20px; 
            border-bottom: 1px solid var(--border); 
            font-size: 1rem; 
            vertical-align: middle;
        }
        
        tr:last-child td { border-bottom: none; }
        table tbody tr td:nth-child(2),
        table tbody tr td:nth-child(3) {
            white-space: nowrap; 
            width: 1%;
        }

        .badge { 
            padding: 6px 12px; border-radius: 20px; 
            font-size: 0.85rem; font-weight: 600; white-space: nowrap; display: inline-block;
        }
        .bg-ok { background: #D1FAE5; color: #065F46; }
        .bg-warn { background: #FEE2E2; color: #991B1B; }

        td.action-links { white-space: nowrap; width: 1%; text-align: right; }
        .action-links a { 
            display: inline-block; margin: 0 8px; 
            text-decoration: none; font-size: 1rem; 
        }
        .edit-link { color: var(--warning); }
        .delete-link { color: var(--danger); }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="container" style="display:flex; justify-content:space-between; width:100%;">
            <h1><i class="fas fa-cubes"></i> Stock Manager</h1>
            <span style="font-size: 0.9rem; color: var(--text-light);">
                <?= date('d M Y') ?>
            </span>
        </div>
    </nav>

    <!-- BRAND SLIDER -->
    <div class="slider-area">
        <div class="slider-track">
            <!-- Group 1 -->
            <div class="brand-box"><i class="fab fa-apple"></i> Apple</div>
            <div class="brand-box"><i class="fab fa-microsoft"></i> Microsoft</div>
            <div class="brand-box"><i class="fas fa-laptop"></i> Asus</div>
            <div class="brand-box"><i class="fas fa-laptop"></i> Lenovo</div>
            <div class="brand-box"><i class="fas fa-laptop"></i> Hp</div>
            <div class="brand-box"><i class="fas fa-microchip"></i> Nvidia</div>
            <div class="brand-box"><i class="fas fa-microchip"></i> AMD</div>
            <div class="brand-box"><i class="fas fa-microchip"></i> Intel</div>
            <div class="brand-box"><i class="fas fa-laptop"></i> Acer</div>
            <div class="brand-box"><i class="fas fa-desktop"></i> Samsung</div>
            <div class="brand-box"><i class="fas fa-memory"></i> Corsair</div>
            <div class="brand-box"><i class="fas fa-memory"></i> Adata XPG</div>
            <div class="brand-box"><i class="fas fa-microchip"></i> Gigabyte</div>
            <div class="brand-box"><i class="fas fa-microchip"></i> AsRock</div>
            <div class="brand-box"><i class="fas fa-mouse"></i> Logitech</div>
            <div class="brand-box"><i class="fas fa-laptop"></i> MSI</div>
            <!-- Group 2  -->
            <div class="brand-box"><i class="fab fa-apple"></i> Apple</div>
            <div class="brand-box"><i class="fab fa-microsoft"></i> Microsoft</div>
            <div class="brand-box"><i class="fas fa-laptop"></i> Asus</div>
            <div class="brand-box"><i class="fas fa-laptop"></i> Lenovo</div>
            <div class="brand-box"><i class="fas fa-laptop"></i> Hp</div>
            <div class="brand-box"><i class="fas fa-microchip"></i> Nvidia</div>
            <div class="brand-box"><i class="fas fa-microchip"></i> AMD</div>
            <div class="brand-box"><i class="fas fa-microchip"></i> Intel</div>
            <div class="brand-box"><i class="fas fa-laptop"></i> Acer</div>
            <div class="brand-box"><i class="fas fa-desktop"></i> Samsung</div>
            <div class="brand-box"><i class="fas fa-memory"></i> Corsair</div>
            <div class="brand-box"><i class="fas fa-memory"></i> Adata XPG</div>
            <div class="brand-box"><i class="fas fa-microchip"></i> Gigabyte</div>
            <div class="brand-box"><i class="fas fa-microchip"></i> AsRock</div>
            <div class="brand-box"><i class="fas fa-mouse"></i> Logitech</div>
            <div class="brand-box"><i class="fas fa-laptop"></i> MSI</div>
        </div>
    </div>

    <div class="container">
        
        <!-- HEADER GRID -->
        <div class="stats-grid">
            
            <!-- Total Barang -->
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-box"></i></div>
                <div class="stat-info">
                    <h3>Total Barang</h3>
                    <p><?= $totalItem ?></p>
                </div>
            </div>

            <!--Search Bar-->
            <form action="" method="GET" class="search-box-header">
                <input type="text" name="q" class="search-input-header" placeholder="Cari nama barang..." value="<?= isset($_GET['q']) ? htmlspecialchars($_GET['q']) : '' ?>">
                <button type="submit" class="btn btn-search"><i class="fas fa-search"></i></button>
                <?php if(isset($_GET['q'])): ?>
                    <a href="index.php" class="btn-reset" title="Reset Pencarian"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>

        </div>

        <div class="content-grid">
            
            <div class="left-panel">
    
    <?php if ($errorMessage): ?>
        <div class="alert-box alert-error">
            <?= $errorMessage ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <?= $editMode ? '<i class="fas fa-edit"></i> Edit Barang' : '<i class="fas fa-plus-circle"></i> Tambah Baru' ?>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <input type="hidden" name="id" value="<?= $editData['id'] ?>">
                
                <div class="form-group">
                    <label class="form-label">Nama Barang</label>
                    <input type="text" name="nama" class="form-input" value="<?= $editData['nama'] ?>" placeholder="Contoh: Laptop Asus" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Stok</label>
                    <input type="number" name="stok" class="form-input" value="<?= $editData['stok'] ?>" placeholder="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Harga Satuan (Rp)</label>
                    <input type="number" name="harga" class="form-input" value="<?= $editData['harga'] ?>" placeholder="0" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <?= $editMode ? 'Simpan Perubahan' : 'Tambah Barang' ?>
                </button>

                <?php if ($editMode): ?>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

            <!-- TABEL DATA -->
            <div class="right-panel">
                <div class="card">
                    <div class="card-body">
                        
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nama Barang</th>
                                        <th>Stok</th>
                                        <th>Harga</th>
                                        <th style="text-align:right;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($dataBarang)): ?>
                                        <tr>
                                            <td colspan="4" style="text-align:center; padding: 30px; color: #9CA3AF;">
                                                <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 10px;"></i><br>
                                                Barang Habis.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($dataBarang as $row): ?>
                                        <tr>
                                            <td style="font-weight: 600; color: var(--text-dark);">
                                                <?= $row['nama'] ?>
                                            </td>
                                            <td>
                                                <?php if($row['stok'] < 5): ?>
                                                    <span class="badge bg-warn"><?= $row['stok'] ?> (Stok Tipis)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-ok"><?= $row['stok'] ?> Pcs</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                Rp <?= number_format($row['harga'], 0, ',', '.') ?>
                                            </td>
                                            <td class="action-links" style="text-align:right;">
                                                <a href="?op=edit&id=<?= $row['id'] ?>" class="edit-link" title="Edit"><i class="fas fa-pen"></i></a>
                                                <a href="?op=delete&id=<?= $row['id'] ?>" class="delete-link" onclick="return confirm('Hapus barang ini?')" title="Hapus"><i class="fas fa-trash"></i></a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
