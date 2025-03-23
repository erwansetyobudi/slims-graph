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
$publisherFilter = isset($_GET['publisher_name']) ? trim($_GET['publisher_name']) : '';

$nodes = [];
$links = [];
$topicColors = [];

function generateColor($str) {
    $hash = md5($str);
    return '#' . substr($hash, 0, 6);
}

if ($publisherFilter !== '') {
    $query = $db->prepare("
        SELECT p.publisher_name, t.topic
        FROM biblio b
        JOIN mst_publisher p ON b.publisher_id = p.publisher_id
        JOIN biblio_topic bt ON b.biblio_id = bt.biblio_id
        JOIN mst_topic t ON bt.topic_id = t.topic_id
        WHERE p.publisher_name LIKE ?
        LIMIT $limit
    ");
    $query->execute(["%$publisherFilter%"]);
} else {
    $query = $db->prepare("
        SELECT p.publisher_name, t.topic
        FROM biblio b
        JOIN mst_publisher p ON b.publisher_id = p.publisher_id
        JOIN biblio_topic bt ON b.biblio_id = bt.biblio_id
        JOIN mst_topic t ON bt.topic_id = t.topic_id
        LIMIT $limit
    ");
    $query->execute();
}

while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $publisher = $row['publisher_name'];
    $topic = $row['topic'];

    if (!isset($nodes[$publisher])) {
        $nodes[$publisher] = [
            'id' => $publisher,
            'group' => 'publisher'
        ];
    }

    if (!isset($nodes[$topic])) {
        $topicColors[$topic] = generateColor($topic);
        $nodes[$topic] = [
            'id' => $topic,
            'group' => 'topic'
        ];
    }

    $links[] = [
        'source' => $publisher,
        'target' => $topic
    ];
}

$graphData = [
    'nodes' => array_values($nodes),
    'links' => $links,
    'topicColors' => $topicColors
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

    <label for="publisher_name">Cari Publisher:</label>
    <input type="text" id="publisher_name" name="publisher_name" value="<?php echo htmlspecialchars($publisherFilter); ?>">

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
    <p><strong>Analisis jaringan antara publisher dan topic</strong> merupakan pendekatan visual yang bertujuan untuk memahami kecenderungan penerbit dalam menerbitkan buku-buku dengan topik tertentu. Dalam visualisasi ini, simpul (nodes) mewakili dua entitas berbeda: <strong>penerbit (publisher)</strong> dan <strong>topik (topic)</strong>. Hubungan antar keduanya digambarkan melalui garis penghubung (edge) yang menunjukkan bahwa sebuah penerbit telah menerbitkan minimal satu buku dengan topik tersebut.</p> <p>Melalui analisis ini, pengguna dapat mengidentifikasi pola-pola berikut:</p> <ul> <li><strong>Kecenderungan topik:</strong> Topik-topik apa yang paling sering diterbitkan oleh masing-masing penerbit.</li> <li><strong>Kepakaran penerbit:</strong> Apakah suatu penerbit terfokus pada bidang/topik tertentu atau justru menerbitkan dalam berbagai topik.</li> <li><strong>Topik populer:</strong> Topik-topik yang diterbitkan oleh banyak penerbit sekaligus, menandakan relevansi atau tren dalam penerbitan.</li> <li><strong>Penerbit dominan:</strong> Penerbit yang memiliki banyak koneksi ke berbagai topik menunjukkan pengaruh atau cakupan penerbitan yang luas.</li> </ul> <p>Analisis ini sangat berguna dalam studi bibliometrik, manajemen koleksi perpustakaan, maupun strategi akuisisi bahan pustaka. Dengan memahami hubungan antara penerbit dan topik, institusi dapat mengambil keputusan berbasis data dalam pengembangan koleksi dan kemitraan dengan penerbit.</p>
    </div>
</div>

<script>
let graph = <?php echo json_encode($graphData); ?>;
const topicColors = graph.topicColors || {};

function getNodeColor(d) {
    if (d.group === "topic") return topicColors[d.id] || "#ccc";
    return "#007bff"; // warna untuk publisher
}

const svg = d3.select("svg");
const width = +svg.node().getBoundingClientRect().width;
const height = +svg.node().getBoundingClientRect().height;
const zoomHandler = d3.zoom().on("zoom", (event) => {
    g.attr("transform", event.transform);
});
svg.call(zoomHandler);

const g = svg.append("g");

let simulation = d3.forceSimulation(graph.nodes)
    .force("link", d3.forceLink(graph.links).id(d => d.id).distance(120))
    .force("charge", d3.forceManyBody().strength(-300))
    .force("center", d3.forceCenter(width / 2, height / 2));

// Tambahkan edges
let link = g.selectAll(".link")
    .data(graph.links)
    .enter().append("line")
    .attr("stroke", "black")
    .attr("stroke-width", 1);

// Tambahkan nodes
let node = g.selectAll(".node")
    .data(graph.nodes)
    .enter().append("g")
    .attr("class", "node")
    .call(d3.drag()
        .on("start", dragstarted)
        .on("drag", dragged)
        .on("end", dragended));

// Bentuk visual: kotak untuk topic, lingkaran untuk publisher
node.each(function(d) {
    if (d.group === "topic") {
        d3.select(this).append("rect")
            .attr("x", -20).attr("y", -20)
            .attr("width", 40).attr("height", 40)
            .attr("rx", 4)
            .attr("fill", getNodeColor(d));
    } else {
        d3.select(this).append("circle")
            .attr("r", 20)
            .attr("fill", getNodeColor(d));
    }
});

// Tambahkan label
node.append("text")
    .attr("dy", 4)
    .attr("dx", d => d.group === "topic" ? -20 : -30)
    .text(d => d.id);

// Animasi
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
    const publisherName = document.getElementById('publisher_name').value;
    const baseUrl = window.location.href.split('?')[0];
    window.location.href = `${baseUrl}?p=publisher_topic_network&limit=${limit}&publisher_name=${encodeURIComponent(publisherName)}`;
}


</script>
<script src="<?php echo SWB; ?>plugins/slims-graph/pages/jspdf.umd.min.js"></script>
</body>
</html>