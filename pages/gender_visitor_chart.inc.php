<?php
/**
 * Plugin Name: SLiMS Graph Network Analysis
 * Plugin URI: https://github.com/erwansetyobudi/slims-graph
 * Description: Plugin untuk menampilkan peta jaringan keterkaitan di SLiMS
 * Version: 1.0.0
 * Author: Erwan Setyo Budi
 * Author URI: https://github.com/erwansetyobudi/
 */

header("Content-Type: text/html; charset=UTF-8");

use SLiMS\DB;

$db = DB::getInstance();

if (!defined('INDEX_AUTH')) {
    die("cannot access this file directly");
} elseif (INDEX_AUTH != 1) {
    die("cannot access this file directly");
}

session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$year = isset($_GET['year']) && $_GET['year'] >= 2000 && $_GET['year'] <= (int)date('Y') ? (int)$_GET['year'] : (int)date('Y');

$query = $db->prepare("
    SELECT MONTH(vc.checkin_date) AS bulan, m.gender, COUNT(*) AS jumlah
    FROM visitor_count vc
    JOIN member m ON vc.member_id = m.member_id
    WHERE YEAR(vc.checkin_date) = :year
    GROUP BY bulan, m.gender
    ORDER BY bulan ASC
    LIMIT :limit
");

$query->bindValue(':year', $year, PDO::PARAM_INT);
$query->bindValue(':limit', $limit, PDO::PARAM_INT);
$query->execute();


$bulanMap = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

$raw = [];
for ($i = 1; $i <= 12; $i++) {
    $nama = $bulanMap[$i - 1];
    $raw[$nama] = ['month' => $nama, 'female' => 0, 'male' => 0];
}

while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $bulan = (int)$row['bulan'];
    $gender = $row['gender'];
    $jumlah = (int)$row['jumlah'];

    if ($bulan < 1 || $bulan > 12) continue; // Hindari error jika bulan tidak valid

    $namaBulan = $bulanMap[$bulan - 1];

    if ($gender == 0) {
        $raw[$namaBulan]['female'] += $jumlah;
    } else {
        $raw[$namaBulan]['male'] += $jumlah;
    }
}


$chartData = array_values($raw);
// --- PREDIKSI KUNJUNGAN ---
$prediksiQuery = $db->query("
    SELECT DATE_FORMAT(checkin_date, '%Y-%m') AS bulan, COUNT(*) AS total
    FROM visitor_count
    GROUP BY bulan
    ORDER BY bulan ASC
");

$x = []; $y = []; $i = 0; $bulanLabel = [];
while ($row = $prediksiQuery->fetch(PDO::FETCH_ASSOC)) {
    $x[] = $i;
    $y[] = (int)$row['total'];
    $bulanLabel[] = $row['bulan'];
    $i++;
}

function linreg($x, $y) {
    $n = count($x);
    $sum_x = array_sum($x);
    $sum_y = array_sum($y);
    $sum_xx = array_sum(array_map(fn($v) => $v * $v, $x));
    $sum_xy = array_sum(array_map(fn($xv, $yv) => $xv * $yv, $x, $y));
    $b = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_xx - $sum_x ** 2);
    $a = ($sum_y - $b * $sum_x) / $n;
    return [$a, $b];
}

[$a, $b] = linreg($x, $y);
$next_month_index = count($x);
$next_year_index = $next_month_index + 11;
$prediksi_bulan_depan = round($a + $b * $next_month_index);
$prediksi_tahun_depan = 0;

for ($j = $next_month_index; $j <= $next_year_index; $j++) {
    $prediksi_tahun_depan += round($a + $b * $j);
}

// Data untuk grafik semua tahun
$tahunan = [];
foreach ($bulanLabel as $idx => $bulan) {
    $tahunan[] = ['bulan' => $bulan, 'jumlah' => $y[$idx]];
}

// Hitung rata-rata kunjungan setiap tahun dari data
$kunjungan_per_tahun = [];

foreach ($bulanLabel as $idx => $bulan) {
    [$tahun, $bulan_num] = explode('-', $bulan);
    if (!isset($kunjungan_per_tahun[$tahun])) {
        $kunjungan_per_tahun[$tahun] = ['total' => 0, 'bulan' => 0];
    }
    $kunjungan_per_tahun[$tahun]['total'] += $y[$idx];
    $kunjungan_per_tahun[$tahun]['bulan'] += 1;
}

$rerata_per_tahun = [];
foreach ($kunjungan_per_tahun as $tahun => $data) {
    $rerata_per_tahun[$tahun] = round($data['total'] / $data['bulan']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token mismatch.');
    }
}



?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Gender Visitor Chart</title>
    <script src="<?php echo SWB; ?>plugins/slims-graph/pages/d3.v7.min.js"></script>
    <script src="<?php echo SWB; ?>plugins/slims-graph/pages/html2canvas.min.js"></script>
    <style>

        .container {
            max-width: 1100px;
            margin: auto;
        }
        .controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1rem;
        }
        .controls input, .controls button {
            padding: 0.4rem 0.7rem;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        .controls button {
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        .controls button:hover {
            background: #0056b3;
        }
        .description {
            margin-top: 2rem;
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        svg {
            width: 100%;
            height: 600px;
        }
    </style>
</head>
<body>
<!-- 
Plugin Name: SLiMS Graph Network Analysis
Plugin URI: https://github.com/erwansetyobudi/slims-graph
Description: Plugin untuk menampilkan peta jaringan keterkaitan di SLiMS
Version: 1.0.0
Author: Erwan Setyo Budi
Author URI: https://github.com/erwansetyobudi 
-->

<div class="container">
<?php include('graphbar.php'); ?>
    <form class="controls" onsubmit="updateLimit(event)">
    <label for="year">Tahun:</label>
    <select class="form-control" id="year" name="year">
        <?php
        $currentYear = date('Y');
        for ($y = $currentYear - 5; $y <= $currentYear; $y++) {
            echo '<option value="' . $y . '"' . ($y == $year ? ' selected' : '') . '>' . $y . '</option>';
        }
        ?>
    </select>

    <label for="limit">Limit Data:</label>
    <input class="form-control" type="number" id="limit" name="limit" min="1" value="<?php echo $limit; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input value="<?php echo htmlspecialchars($limit, ENT_QUOTES, 'UTF-8'); ?>">
    <button class="btn btn-primary" type="submit">Terapkan</button>
    <button class="btn btn-primary" type="button" onclick="saveAsImage()">💾 Simpan JPG</button>
    <button class="btn btn-primary" type="button" onclick="shareUrl()">🔗 Salin Link</button>
    </form>


    <svg id="chart"></svg>

    <div class="description">
        <p><strong>Visualisasi ini</strong> menampilkan jumlah kunjungan perpustakaan berdasarkan jenis kelamin dalam satu tahun. Data ditampilkan dalam bentuk grafik horizontal dengan gaya mirip <em>Icelandic Population Chart</em>.</p>
        <ul>
            <li>🔵 Laki-laki ditampilkan ke kiri (negatif)</li>
            <li>🔴 Perempuan ke kanan (positif)</li>
            <li>Sumbu vertikal (Y) menunjukkan bulan</li>
            <li>Sumbu horizontal (X) menunjukkan jumlah kunjungan</li>
        </ul>
        <p>Grafik ini membantu memahami dinamika kunjungan antara gender sepanjang tahun.</p>
    </div>
    <div class="description" style="margin-top: 2rem;">
        

        <h4>Grafik Total Kunjungan Semua Tahun</h4>
        <svg id="totalChart" width="100%" height="300"></svg>
        <h4>Rata-rata Kunjungan per Tahun</h4>
        <ul>
        <?php foreach ($rerata_per_tahun as $th => $rata): ?>
            <li>📅 Tahun <?php echo $th; ?>: <strong><?php echo $rata; ?></strong> kunjungan per bulan</li>
        <?php endforeach; ?>
        </ul>
        <h4>Prediksi Jumlah Kunjungan</h4>
        <ul>
            <li>📅 Bulan Depan: <strong><?php echo $prediksi_bulan_depan; ?></strong> kunjungan</li>
            <li>📆 Total Tahun Depan: <strong><?php echo $prediksi_tahun_depan; ?></strong> kunjungan</li>
        </ul>


    </div>
    <div class="description" style="margin-top: 2rem; background: #fefefe;">
    <h3>Cara Prediksi Jumlah Kunjungan Perpustakaan </h3>
    <p>Untuk memperkirakan jumlah kunjungan bulan depan dan tahun depan, digunakan metode <strong>regresi linear sederhana</strong> berdasarkan data kunjungan per bulan dari masa lalu.</p>

    <h4>1. Rumus Regresi Linear</h4>
    <p>Persamaan regresi linear:</p>
    <pre>y = a + bx</pre>
    <ul>
        <li><code>y</code> = jumlah kunjungan yang diprediksi</li>
        <li><code>x</code> = urutan bulan ke-n (misal Januari 2021 = 0, Februari 2021 = 1, dst)</li>
        <li><code>a</code> = titik awal (intercept)</li>
        <li><code>b</code> = kenaikan rata-rata kunjungan per bulan (kemiringan garis)</li>
    </ul>

    <h4>2. Langkah Perhitungan</h4>
    <ol>
        <li>Kumpulkan data kunjungan per bulan, misalnya:
            <pre>
            Bulan ke-0 = 1000 kunjungan  
            Bulan ke-1 = 1200  
            Bulan ke-2 = 1100  
            Bulan ke-3 = 1400  
            Bulan ke-4 = 1300
            </pre>
        </li>
        <li>Buat tabel bantu:
            <pre>
            x   y     x²     xy
            0  1000    0      0
            1  1200    1   1200
            2  1100    4   2200
            3  1400    9   4200
            4  1300   16   5200
            </pre>
        </li>
        <li>Hitung total:
            <ul>
                <li>Σx = 10</li>
                <li>Σy = 6000</li>
                <li>Σx² = 30</li>
                <li>Σxy = 12800</li>
                <li>n = 5</li>
            </ul>
        </li>
        <li>Hitung <code>b</code> dan <code>a</code>:
            <pre>
            b = (n·Σxy - Σx·Σy) / (n·Σx² - (Σx)²)
              = (5×12800 - 10×6000) / (5×30 - 100)
              = (64000 - 60000) / (150 - 100) = 80

            a = (Σy - b·Σx) / n
              = (6000 - 80×10) / 5 = 1040
            </pre>
        </li>
        <li>Prediksi bulan depan (x = 5):
            <pre>y = 1040 + 80×5 = 1440 kunjungan</pre>
        </li>
        <li>Prediksi tahun depan (12 bulan ke depan):
            <pre>
            Total prediksi:
            x = 5 → y = 1440  
            x = 6 → y = 1520  
            ...
            x = 16 → y = 2320

            Jumlah total = penjumlahan semua y dari x = 5 sampai 16
            </pre>
        </li>
    </ol>

    <h4>3. Kesimpulan</h4>
    <p>
        Dengan metode regresi linear sederhana, perpustakaan dapat memperkirakan jumlah kunjungan bulan depan dan satu tahun ke depan hanya dengan memanfaatkan tren historis kunjungan perpustakaan. Cocok digunakan ketika tren data relatif stabil dan konsisten dari bulan ke bulan.
    </p>
</div>


</div>

<script>
const data = <?php echo json_encode($chartData); ?>;

const svg = d3.select("#chart");
const margin = {top: 20, right: 50, bottom: 30, left: 100};
const width = +svg.node().getBoundingClientRect().width - margin.left - margin.right;
const height = +svg.node().getBoundingClientRect().height - margin.top - margin.bottom;

const g = svg.append("g").attr("transform", `translate(${margin.left},${margin.top})`);

const y = d3.scaleBand()
    .domain(data.map(d => d.month))
    .range([0, height])
    .padding(0.2);

const x = d3.scaleLinear()
    .domain([
        -d3.max(data, d => d.male),
         d3.max(data, d => d.female)
    ])
    .nice()
    .range([0, width]);

// Sumbu
g.append("g")
    .attr("transform", `translate(0,0)`)
    .call(d3.axisLeft(y));

g.append("g")
    .attr("transform", `translate(0,${height})`)
    .call(d3.axisBottom(x).ticks(5).tickFormat(Math.abs));

// Bar Laki-laki ke kiri
g.selectAll(".male")
    .data(data)
    .enter().append("rect")
    .attr("x", d => x(-d.male))
    .attr("y", d => y(d.month))
    .attr("width", d => x(0) - x(-d.male))
    .attr("height", y.bandwidth())
    .attr("fill", "#1f77b4");

// Bar Perempuan ke kanan
g.selectAll(".female")
    .data(data)
    .enter().append("rect")
    .attr("x", x(0))
    .attr("y", d => y(d.month))
    .attr("width", d => x(d.female) - x(0))
    .attr("height", y.bandwidth())
    .attr("fill", "#e377c2");
</script>

<script>
function updateLimit(event) {
    event.preventDefault();
    const limit = document.getElementById("limit").value;
    const year = document.getElementById("year").value;
    const baseUrl = window.location.href.split('?')[0];
    window.location.href = `${baseUrl}?p=gender_visitor_chart&year=${year}&limit=${limit}`;
}


function saveAsImage() {
    html2canvas(document.querySelector('.container')).then(canvas => {
        let link = document.createElement("a");
        link.download = "gender-visitor-chart.jpg";
        link.href = canvas.toDataURL("image/jpeg", 1.0);
        link.click();
    });
}

function shareUrl() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        alert("Link berhasil disalin!");
    });
}
</script>
<script>
const dataTahunan = <?php echo json_encode($tahunan); ?>;
const svg2 = d3.select("#totalChart");
const margin2 = {top: 40, right: 30, bottom: 50, left: 60};
const width2 = +svg2.node().getBoundingClientRect().width - margin2.left - margin2.right;
const height2 = +svg2.node().getBoundingClientRect().height - margin2.top - margin2.bottom;

const g2 = svg2.append("g").attr("transform", `translate(${margin2.left},${margin2.top})`);

const x2 = d3.scalePoint()
    .domain(dataTahunan.map(d => d.bulan))
    .range([0, width2]);

const y2 = d3.scaleLinear()
    .domain([0, d3.max(dataTahunan, d => d.jumlah)]).nice()
    .range([height2, 0]);

// Sumbu X
g2.append("g")
    .attr("transform", `translate(0,${height2})`)
    .call(d3.axisBottom(x2).tickValues(x2.domain().filter((d, i) => i % 6 === 0)))
    .selectAll("text")
    .attr("transform", "rotate(-45)")
    .style("text-anchor", "end");

// Sumbu Y
g2.append("g")
    .call(d3.axisLeft(y2));

// Area generator
const area = d3.area()
    .x(d => x2(d.bulan))
    .y0(height2)
    .y1(d => y2(d.jumlah))
    .curve(d3.curveMonotoneX); // smooth line

// Render area
g2.append("path")
    .datum(dataTahunan)
    .attr("fill", "#28a74544")
    .attr("stroke", "#28a745")
    .attr("stroke-width", 2)
    .attr("d", area);
// Garis waktu saat ini (misal: Maret 2025)
const today = new Date();
const currentMonthLabel = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0');

// Temukan bulan yang cocok di dataset
const bulanSekarang = dataTahunan.find(d => d.bulan === currentMonthLabel);
if (bulanSekarang) {
    const xPos = x2(bulanSekarang.bulan);

    // Tambahkan garis vertikal
    g2.append("line")
        .attr("x1", xPos)
        .attr("x2", xPos)
        .attr("y1", 0)
        .attr("y2", height2)
        .attr("stroke", "#ff0000")
        .attr("stroke-dasharray", "4")
        .attr("stroke-width", 2);

    // Tambahkan label "Bulan Ini"
    g2.append("text")
        .attr("x", xPos)
        .attr("y", -10)
        .attr("text-anchor", "middle")
        .attr("fill", "#ff0000")
        .attr("font-size", "12px")
        .text("Bulan Ini");
}


// Titik-titik puncak
g2.selectAll("circle")
    .data(dataTahunan)
    .enter()
    .append("circle")
    .attr("cx", d => x2(d.bulan))
    .attr("cy", d => y2(d.jumlah))
    .attr("r", 4)
    .attr("fill", "#28a745");

// Label nilai pada puncak
g2.selectAll("text.label")
    .data(dataTahunan)
    .enter()
    .append("text")
    .attr("x", d => x2(d.bulan))
    .attr("y", d => y2(d.jumlah) - 10)
    .attr("text-anchor", "middle")
    .attr("font-size", "11px")
    .attr("fill", "#333")
    .text(d => d.jumlah);
</script>


</body>
</html>
