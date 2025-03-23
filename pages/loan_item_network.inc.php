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
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Item Member Network</title>
    <script src="<?php echo SWB; ?>plugins/slims-graph/pages/d3.v6.min.js"></script>
    <script src="<?php echo SWB; ?>plugins/slims-graph/pages/html2canvas.min.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            
        }
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
<script src="<?php echo SWB; ?>plugins/slims-graph/pages/jspdf.umd.min.js"></script>
</body>
</html>