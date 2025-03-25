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
header("X-Frame-Options: DENY");

use SLiMS\Url;
use SLiMS\DB;
use SLiMS\Json;
use Volnix\CSRF\CSRF;

$db = DB::getInstance(); 

if (!defined('INDEX_AUTH')) {
    die("cannot access this file directly");
} elseif (INDEX_AUTH != 1) {
    die("cannot access this file directly");
}

if ($sysconf['baseurl'] != '') {
    $_host = $sysconf['baseurl'];
    header("Access-Control-Allow-Origin: $_host", false);
}

do_checkIP('opac');
do_checkIP('opac-member');

// Ambil nilai limit dari form, default 100
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$itemCodeFilter = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';


$itemCodeFilter = isset($_GET['item_code']) ? trim($_GET['item_code']) : '';

if ($itemCodeFilter !== '') {
    $query = $db->prepare("SELECT item_code, member_id FROM loan WHERE item_code = ? LIMIT $limit");
    $query->execute([$itemCodeFilter]);
} else {
    $query = $db->prepare("SELECT item_code, member_id FROM loan ORDER BY item_code LIMIT $limit");
    $query->execute();
}

$result = $query; // assign ke $result agar loop tetap berjalan


$nodes = [];
$links = [];
$itemColors = []; // Untuk menyimpan warna unik per item

function generateColor($str) {
    // Buat warna unik berdasarkan item_code
    $hash = md5($str);
    return '#' . substr($hash, 0, 6); // Ambil 6 karakter pertama dari hash sebagai warna HEX
}

// Proses data untuk membentuk nodes & links
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $item_code = $row['item_code'];
    $member_id = $row['member_id'];

    // Tambahkan item_code sebagai node kotak jika belum ada
    if (!isset($nodes[$item_code])) {
        $itemColors[$item_code] = generateColor($item_code); // Simpan warna unik untuk item
        $nodes[$item_code] = [
            'id' => $item_code,
            'group' => 'item'
        ];
    }

    // Tambahkan member sebagai node lingkaran jika belum ada
    if (!isset($nodes[$member_id])) {
        $nodes[$member_id] = [
            'id' => $member_id,
            'group' => 'member'
        ];
    }

    // Hubungkan member dengan item_code
    $links[] = [
        'source' => $member_id,
        'target' => $item_code
    ];
}

// Konversi array ke JSON
$graphData = [
    'nodes' => array_values($nodes),
    'links' => $links,
    'itemColors' => $itemColors // Kirim warna item ke frontend
];


?>
<?php
// Ambil data peminjaman per bulan dari tabel loan
$loanStatQuery = $db->query("SELECT DATE_FORMAT(loan_date, '%Y-%m') AS bulan, COUNT(item_code) AS total FROM loan GROUP BY bulan ORDER BY bulan ASC");

$bulanList = [];
$jumlahList = [];
$tahunMap = [];
$i = 0;

while ($row = $loanStatQuery->fetch(PDO::FETCH_ASSOC)) {
    $bulanList[] = $row['bulan'];
    $jumlahList[] = (int) $row['total'];
    $tahun = substr($row['bulan'], 0, 4);
    if (!isset($tahunMap[$tahun])) {
        $tahunMap[$tahun] = ['total' => 0, 'bulan' => 0];
    }
    $tahunMap[$tahun]['total'] += (int) $row['total'];
    $tahunMap[$tahun]['bulan']++;
    $i++;
}

// Hitung rata-rata per tahun
$rataRataPerTahun = [];
foreach ($tahunMap as $thn => $data) {
    $rataRataPerTahun[$thn] = round($data['total'] / $data['bulan']);
}

// Prediksi dengan regresi linear sederhana
$totalX = count($jumlahList);
$x = range(0, $totalX - 1);
$sum_x = array_sum($x);
$sum_y = array_sum($jumlahList);
$sum_xx = array_sum(array_map(fn($v) => $v * $v, $x));
$sum_xy = array_sum(array_map(fn($xi, $yi) => $xi * $yi, $x, $jumlahList));


$b = ($totalX * $sum_xy - $sum_x * $sum_y) / ($totalX * $sum_xx - $sum_x ** 2);
$a = ($sum_y - $b * $sum_x) / $totalX;

$prediksiBulanDepan = round($a + $b * $totalX);
$prediksiTahunDepan = 0;
for ($j = $totalX; $j < $totalX + 12; $j++) {
    $prediksiTahunDepan += round($a + $b * $j);
}

// Siapkan data JS
$loanChartData = [];
foreach ($bulanList as $idx => $bulan) {
    $loanChartData[] = ['bulan' => $bulan, 'jumlah' => $jumlahList[$idx]];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        CSRF::validate();
    } catch (\Exception $e) {
        die('CSRF token mismatch');
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Item Member Network</title>
    <script src="<?php echo SWB; ?>plugins/slims-graph/pages/d3.v6.min.js"></script>
    <script src="<?php echo SWB; ?>plugins/slims-graph/pages/html2canvas.min.js"></script>

    <style>

        .container {
            max-width: 1100px;
            margin: auto;
        }
        .graph-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 1rem;
            position: relative;
        }
        svg {
            width: 100%;
            height: 600px;
        }
        .controls {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 0.5rem;
        }
        .controls button {
            background: #007bff;
            border: none;
            color: white;
            padding: 0.4rem 0.6rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .controls button:hover {
            background: #0056b3;
        }
        .description {
            background: #fff;
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            line-height: 1.6;
        }
        .limit-form {
            margin-bottom: 1rem;
        }
        .limit-form input {
            padding: 0.4rem;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .limit-form button {
            padding: 0.4rem 0.6rem;
            border-radius: 5px;
            border: none;
            background: #007bff;
            color: white;
            cursor: pointer;
        }
        .limit-form button:hover {
            background: #0056b3;
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
    <form class="limit-form" onsubmit="updateGraph(event)">
    <label for="limit">Limit Query:</label>
    <input type="number" id="limit" name="limit" value="<?php echo $limit; ?>" min="1">
    <input type="hidden" name="csrf_token" value="<?php echo CSRF::getToken(); ?>">
    <label for="item_code">Cari Item Code:</label>
    <input type="text" id="item_code" name="item_code" value="<?php echo htmlspecialchars($itemCodeFilter); ?>">

    <button type="submit">Update</button>
    </form>


    <div class="graph-box" id="graphBox">
        <div class="controls">
            <button onclick="zoomHandler.scaleBy(svg.transition().duration(500), 1.2)">Zoom In</button>
            <button onclick="zoomHandler.scaleBy(svg.transition().duration(500), 0.8)">Zoom Out</button>
            <button onclick="saveAsImage()">Save JPG</button>
            <button onclick="shareUrl()">Share</button>
        </div>
        <svg><canvas id="canvasExport" style="display: none;"></canvas></svg>
    </div>

    <div class="description">
    <p><strong>Loan Item Network Analysis</strong> adalah visualisasi jaringan yang bertujuan untuk mengidentifikasi hubungan antar anggota perpustakaan (<em>member</em>) berdasarkan aktivitas peminjaman koleksi yang sama. Dalam jaringan ini, setiap simpul (<em>node</em>) mewakili seorang anggota, sementara garis penghubung (<em>edge</em>) antara dua anggota menunjukkan bahwa keduanya pernah meminjam <strong>item yang sama</strong>.</p> <p>Analisis ini bermanfaat untuk:</p> <ul> <li>Mendeteksi <strong>pola peminjaman bersama</strong> antar pengguna, seperti anggota dalam satu kelas, kelompok belajar, atau komunitas dengan minat yang sama.</li> <li>Menemukan <strong>kelompok pengguna aktif</strong> yang saling terkait melalui penggunaan koleksi yang sama.</li> <li>Menganalisis <strong>potensi kolaborasi</strong> atau interaksi antar pengguna berdasarkan minat literasi yang tumpang tindih.</li> </ul> <p>Visualisasi ini membantu pustakawan, peneliti, atau pengelola sistem perpustakaan dalam memahami dinamika penggunaan koleksi secara sosial, dan dapat menjadi dasar dalam merancang layanan personalisasi, rekomendasi buku, atau pengembangan komunitas pengguna di perpustakaan.</p>
    </div>
</div>

<div class="description" style="margin-top:2rem">
    <h4>Statistik Peminjaman Sepanjang Waktu</h4>
    <svg id="loanChart" width="100%" height="300"></svg>
    <h4>Rata-rata Peminjaman per Tahun</h4>
    <ul>
        <?php foreach ($rataRataPerTahun as $tahun => $rata) {
            echo "<li><strong>" . htmlspecialchars($tahun) . "</strong>: " . htmlspecialchars($rata) . " peminjaman/bulan</li>";

        } ?>
    </ul>
    <h4>Prediksi Jumlah Peminjaman</h4>
    <ul>
        <li>📅 Bulan Depan: <strong><?php echo $prediksiBulanDepan; ?></strong> peminjaman</li>
        <li>📆 Tahun Depan: <strong><?php echo $prediksiTahunDepan; ?></strong> peminjaman</li>
    </ul>

</div>
<div class="description" style="margin-top: 2rem; background: #fefefe;">
    <h3>Cara Prediksi Jumlah Peminjaman Buku</h3>
    <p>Untuk memperkirakan jumlah peminjaman bulan depan dan tahun depan, digunakan metode <strong>regresi linear sederhana</strong> berdasarkan jumlah peminjaman buku per bulan.</p>

    <h4>1. Rumus Regresi Linear</h4>
    <p>Persamaan umum:</p>
    <pre>y = a + bx</pre>
    <ul>
        <li><code>y</code> = jumlah peminjaman yang diprediksi</li>
        <li><code>x</code> = urutan bulan (0 = bulan pertama, 1 = bulan kedua, dst)</li>
        <li><code>a</code> = titik awal (intercept)</li>
        <li><code>b</code> = pertambahan rata-rata (kemiringan garis)</li>
    </ul>

    <h4>2. Langkah Perhitungan Manual</h4>
    <ol>
        <li>Kumpulkan data jumlah peminjaman buku per bulan, contoh:
            <pre>
            Bulan ke-0: 1500  
            Bulan ke-1: 1600  
            Bulan ke-2: 1550  
            Bulan ke-3: 1700  
            Bulan ke-4: 1650
            </pre>
        </li>
        <li>Buat tabel bantu:
            <pre>
            x   y     x²     xy
            0  1500    0      0
            1  1600    1   1600
            2  1550    4   3100
            3  1700    9   5100
            4  1650   16   6600
            </pre>
        </li>
        <li>Hitung total:
            <ul>
                <li>Σx = 10</li>
                <li>Σy = 8000</li>
                <li>Σx² = 30</li>
                <li>Σxy = 16400</li>
                <li>n = 5</li>
            </ul>
        </li>
        <li>Hitung <code>b</code> dan <code>a</code>:
            <pre>
            b = (n·Σxy - Σx·Σy) / (n·Σx² - (Σx)²)
              = (5×16400 - 10×8000) / (5×30 - 100)
              = (82000 - 80000) / (150 - 100) = 400 / 50 = 8

            a = (Σy - b·Σx) / n
              = (8000 - 8×10) / 5 = (8000 - 80) / 5 = 7920 / 5 = 1584
            </pre>
        </li>
        <li>Prediksi bulan depan (x = 5):
            <pre>y = 1584 + 8×5 = <strong>1624 peminjaman</strong></pre>
        </li>
        <li>Prediksi tahun depan (12 bulan ke depan):
            <pre>
            x = 5 → y = 1624  
            x = 6 → y = 1632  
            x = 7 → y = 1640  
            ...  
            x = 16 → y = 1728

            Jumlah tahun depan = total y dari x=5 sampai x=16
            </pre>
        </li>
    </ol>

    <h4>3. Kesimpulan</h4>
    <p>
        Dengan memanfaatkan data historis peminjaman dan metode regresi linear, perpustakaan bisa memprediksi tren kebutuhan koleksi dan mengoptimalkan layanan peminjaman secara lebih proaktif.
    </p>
</div>


<script>
    const svg = d3.select("svg");
    const width = +svg.node().getBoundingClientRect().width;
    const height = +svg.node().getBoundingClientRect().height;
    const zoomHandler = d3.zoom().on("zoom", (event) => {
        g.attr("transform", event.transform);
    });
    svg.call(zoomHandler);

    const g = svg.append("g");

let graph = <?php echo json_encode($graphData); ?>;

// Fungsi untuk mengambil warna dari item
const itemColors = graph.itemColors || {};

function getNodeColor(d) {
    if (d.group === "item") return itemColors[d.id] || "#ccc"; // Warna unik untuk item_code
    return "#007bff"; // Warna biru untuk member
}

// Force Simulation
let simulation = d3.forceSimulation(graph.nodes)
    .force("link", d3.forceLink(graph.links).id(d => d.id).distance(120))
    .force("charge", d3.forceManyBody().strength(-300))
    .force("center", d3.forceCenter(width / 2, height / 2));

// Buat edges (links)
let link = g.selectAll(".link")
    .data(graph.links)
    .enter().append("line")
    .attr("stroke", "black")
    .attr("stroke-width", 1);

// Buat nodes
let node = g.selectAll(".node")
    .data(graph.nodes)
    .enter().append("g")
    .attr("class", "node")
    .call(d3.drag()
        .on("start", dragstarted)
        .on("drag", dragged)
        .on("end", dragended));

// Tambahkan bentuk berbeda untuk member dan item_code
node.each(function(d) {
    if (d.group === "item") {
        d3.select(this).append("rect")
            .attr("x", -15).attr("y", -15)
            .attr("width", 30).attr("height", 30)
            .attr("rx", 4) // Sudut sedikit melengkung
            .attr("fill", getNodeColor(d));
    } else {
        d3.select(this).append("circle")
            .attr("r", 20)
            .attr("fill", getNodeColor(d));
    }
});

// Tambahkan teks label
node.append("text")
    .attr("dy", 4)
    .attr("dx", d => d.group === "item" ? -10 : -15)
    .text(d => d.id);


    simulation.on("tick", () => {
        link.attr("x1", d => d.source.x)
            .attr("y1", d => d.source.y)
            .attr("x2", d => d.target.x)
            .attr("y2", d => d.target.y);

        node.attr("transform", d => `translate(${d.x},${d.y})`);
    });

    function dragstarted(event, d) {
        if (!event.active) simulation.alphaTarget(0.3).restart();
        d.fx = d.x;
        d.fy = d.y;
    }

    function dragged(event, d) {
        d.fx = event.x;
        d.fy = event.y;
    }

    function dragended(event, d) {
        if (!event.active) simulation.alphaTarget(0);
        d.fx = null;
        d.fy = null;
    }

    // Fungsi untuk menyimpan sebagai JPG menggunakan html2canvas
function saveAsImage() {
    let graphBox = document.getElementById("graphBox");
    
    // Simpan warna asli
    let originalBg = graphBox.style.backgroundColor;

    // Pastikan latar belakang putih
    graphBox.style.backgroundColor = "#fff";

    html2canvas(graphBox, {
        backgroundColor: "#fff"  // Pastikan latar belakang putih
    }).then(canvas => {
        let link = document.createElement("a");
        link.download = "author-network.jpg";
        link.href = canvas.toDataURL("image/jpeg", 1.0);
        link.click();

        // Kembalikan warna asli setelah mengambil gambar
        graphBox.style.backgroundColor = originalBg;
    });
}


    // Fungsi share URL tetap sama
    function shareUrl() {
        navigator.clipboard.writeText(window.location.href).then(() => {
            alert("Link halaman berhasil disalin!");
        });
    }

    // Fungsi untuk memperbarui grafik dengan limit yang baru
function updateGraph(event) {
    event.preventDefault();
    const limit = document.getElementById('limit').value;
    const itemCode = document.getElementById('item_code').value;
    const baseUrl = window.location.href.split('?')[0];
    window.location.href = `${baseUrl}?p=loan_item_network&limit=${limit}&item_code=${encodeURIComponent(itemCode)}`;
}

</script>
<script>
const dataLoan = <?php echo json_encode($loanChartData); ?>;
const svgLoan = d3.select("#loanChart");
const marginL = {top: 40, right: 30, bottom: 50, left: 60};
const widthL = +svgLoan.node().getBoundingClientRect().width - marginL.left - marginL.right;
const heightL = +svgLoan.node().getBoundingClientRect().height - marginL.top - marginL.bottom;
const gLoan = svgLoan.append("g").attr("transform", `translate(${marginL.left},${marginL.top})`);

const xLoan = d3.scalePoint()
    .domain(dataLoan.map(d => d.bulan))
    .range([0, widthL]);

const yLoan = d3.scaleLinear()
    .domain([0, d3.max(dataLoan, d => d.jumlah)]).nice()
    .range([heightL, 0]);

// Sumbu
gLoan.append("g")
    .attr("transform", `translate(0,${heightL})`)
    .call(d3.axisBottom(xLoan).tickValues(xLoan.domain().filter((d, i) => i % 6 === 0)))
    .selectAll("text")
    .attr("transform", "rotate(-45)")
    .style("text-anchor", "end");

gLoan.append("g")
    .call(d3.axisLeft(yLoan));

// Area
const areaLoan = d3.area()
    .x(d => xLoan(d.bulan))
    .y0(heightL)
    .y1(d => yLoan(d.jumlah))
    .curve(d3.curveMonotoneX);

gLoan.append("path")
    .datum(dataLoan)
    .attr("fill", "#17a2b833")
    .attr("stroke", "#17a2b8")
    .attr("stroke-width", 2)
    .attr("d", areaLoan);

// Titik dan label

gLoan.selectAll("circle")
    .data(dataLoan)
    .enter()
    .append("circle")
    .attr("cx", d => xLoan(d.bulan))
    .attr("cy", d => yLoan(d.jumlah))
    .attr("r", 3)
    .attr("fill", "#17a2b8");

gLoan.selectAll("text.label")
    .data(dataLoan)
    .enter()
    .append("text")
    .attr("x", d => xLoan(d.bulan))
    .attr("y", d => yLoan(d.jumlah) - 10)
    .attr("text-anchor", "middle")
    .attr("font-size", "10px")
    .attr("fill", "#333")
    .text(d => d.jumlah);
</script>

<script src="<?php echo SWB; ?>plugins/slims-graph/pages/jspdf.umd.min.js"></script>
</body>
</html>
