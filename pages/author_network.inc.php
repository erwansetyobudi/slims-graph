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

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die("cannot access this file directly");
}

if ($sysconf['baseurl'] != '') {
    $_host = $sysconf['baseurl'];
    header("Access-Control-Allow-Origin: $_host", false);
}

do_checkIP('opac');
do_checkIP('opac-member');

// Validasi dan casting limit
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
if ($limit < 1 || $limit > 1000) {
    $limit = 100;
}

// Validasi CSRF jika form dikirim
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['csrf_token'])) {
    if (!CSRF::verify($_GET['csrf_token'])) {
        die("Invalid CSRF token");
    }
}

// Query aman dengan bindValue
$stmt = $db->prepare("SELECT 
                        bt.biblio_id,
                        b.title, b.publish_year, 
                        t.topic,
                        a.author_name
                      FROM 
                        biblio_topic bt
                      JOIN 
                        biblio b ON bt.biblio_id = b.biblio_id
                      JOIN 
                        biblio_author ba ON b.biblio_id = ba.biblio_id
                      JOIN 
                        mst_author a ON ba.author_id = a.author_id
                      JOIN 
                        mst_topic t ON bt.topic_id = t.topic_id
                      LIMIT :limit");

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt;

// Lanjutkan pemrosesan data hasil query seperti sebelumnya...
$nodes = [];
$links = [];
$minYear = PHP_INT_MAX;
$maxYear = 0;

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    // Tambahkan author node
    if (!isset($nodes[$row['author_name']])) {
        $nodes[$row['author_name']] = [
            'id' => $row['author_name'],
            'group' => 'author'
        ];
    }

    // Tambahkan topic node atau update jika sudah ada
    if (!isset($nodes[$row['topic']])) {
        $nodes[$row['topic']] = [
            'id' => $row['topic'],
            'group' => 'topic',
            'year' => $row['publish_year'],
            'count' => 1
        ];
    } else {
        $nodes[$row['topic']]['count'] += 1;
        // Simpan tahun publikasi terbaru
        if ($row['publish_year'] > $nodes[$row['topic']]['year']) {
            $nodes[$row['topic']]['year'] = $row['publish_year'];
        }
    }

    // Simpan tahun min dan max
    if (is_numeric($row['publish_year'])) {
        $minYear = min($minYear, $row['publish_year']);
        $maxYear = max($maxYear, $row['publish_year']);
    }

    // Tambahkan link author -> topic
    $links[] = [
        'source' => $row['author_name'],
        'target' => $row['topic']
    ];
}

$graphData = [
    'nodes' => array_values($nodes),
    'links' => $links,
    'minYear' => $minYear,
    'maxYear' => $maxYear
];

// Pastikan session aktif
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Buat CSRF token jika belum ada
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // Fallback jika random_bytes gagal
        $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true));
    }
}


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Author and Keyword Network Analysis</title>
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
            position: relative; /* agar tooltip absolute bekerja benar */
        }
        .graph-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 1rem;
            position: relative;
            margin-bottom: 0.5rem; /* ditambahkan */
        }
        #graphBox > svg {
            width: 100%;
            height: 1000px;
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
            margin-top: 0.5rem; /* dikurangi agar lebih rapat */
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
        .tooltip {
            position: absolute;
            text-align: left;
            padding: 6px 10px;
            background: rgba(0,0,0,0.75);
            color: #fff;
            border-radius: 5px;
            font-size: 13px;
            pointer-events: none;
            z-index: 10;
            display: none;
        }
        .node circle {
            pointer-events: all;
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
  
  <form class="limit-form" method="get" onsubmit="updateGraph(event)">
    <input type="hidden" name="p" value="author_network">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <label for="limit">Limit Query:</label>
    <input type="number" id="limit" name="limit" value="<?php echo htmlspecialchars($limit, ENT_QUOTES, 'UTF-8'); ?>" min="1">
    <button type="submit">Update</button>
  </form>
  <div class="graph-box" id="graphBox">
    <div class="controls">
      <button onclick="zoomHandler.scaleBy(svg.transition().duration(500), 1.2)">Zoom In</button>
      <button onclick="zoomHandler.scaleBy(svg.transition().duration(500), 0.8)">Zoom Out</button>
      <button onclick="saveAsImage()">Save JPG</button>
      <button onclick="shareUrl()">Share</button>
    </div>
    <svg>
      <canvas id="canvasExport" style="display: none;"></canvas>
    </svg>
    <div style="margin-top: 0rem; text-align: center;">
      <svg width="300" height="65">
        <defs>
          <linearGradient id="yearGradient" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="#08306b" />
            <stop offset="100%" stop-color="#f6f91c" />
          </linearGradient>
        </defs>
        <rect x="0" y="10" width="300" height="20" fill="url(#yearGradient)" />
        <text x="0" y="45" font-size="12px"> <?php echo $minYear; ?> </text>
        <text x="130" y="45" font-size="12px" text-anchor="middle"> <?php echo intval(($minYear + $maxYear) / 2); ?> </text>
        <text x="300" y="45" font-size="12px" text-anchor="end"> <?php echo $maxYear; ?> </text>
        <text x="150" y="62" font-size="13px" text-anchor="middle" fill="#333">Gradasi warna berdasarkan tahun publikasi</text>
      </svg>
    </div>
  </div>
  <div class="description">
    <p>
      <strong>Author and keyword network analysis</strong> adalah pendekatan dalam studi bibliometrik yang menggunakan teknik visualisasi jaringan (network analysis) untuk memahami hubungan dan struktur kolaborasi antarpenulis (author network) serta keterkaitan antar topik atau istilah (keyword network) dalam kumpulan publikasi ilmiah.
    </p>
    <p>Dalam <strong>author network analysis</strong>, jaringan dibangun berdasarkan kolaborasi antarpenulis, di mana simpul (nodes) mewakili penulis dan garis penghubung (edges) menunjukkan adanya kerja sama dalam menulis suatu publikasi. Analisis ini berguna untuk mengidentifikasi kelompok kolaboratif, penulis kunci (key authors), dan struktur sosial dalam komunitas ilmiah. </p>
    <p>Sementara itu, <strong>keyword network analysis</strong> memetakan keterhubungan antar kata kunci berdasarkan kemunculannya secara bersamaan (co-occurrence) dalam dokumen yang sama. Simpul dalam jaringan ini mewakili kata kunci, dan garis penghubung menunjukkan seberapa sering dua kata kunci digunakan bersama. Dari sini, peneliti dapat mengidentifikasi tema-tema sentral, hubungan antar topik, serta topik riset yang sedang berkembang atau terpinggirkan. </p>
    <p>Kedua analisis ini sangat bermanfaat dalam eksplorasi struktur dan dinamika pengetahuan di suatu bidang, serta mendukung pengambilan keputusan strategis dalam pengembangan arah riset ke depan.</p>
  </div>
  <div id="tooltip" class="tooltip"></div>

<script>
    const svg = d3.select("#graphBox > svg");
    const width = +svg.node().getBoundingClientRect().width;
    const height = +svg.node().getBoundingClientRect().height;
    const zoomHandler = d3.zoom().on("zoom", (event) => {
        g.attr("transform", event.transform);
    });
    svg.call(zoomHandler);

    const g = svg.append("g");

    let graph = <?php echo json_encode($graphData); ?>;

    function getColorFromString(str) {
        // Buat warna dari hash string
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }

        // Konversi hash ke format warna hex
        let color = '#';
        for (let i = 0; i < 3; i++) {
            const value = (hash >> (i * 8)) & 0xFF;
            color += ('00' + value.toString(16)).substr(-2);
        }
        return color;
    }


    let simulation = d3.forceSimulation(graph.nodes)
        .force("link", d3.forceLink(graph.links).id(d => d.id).distance(120))
        .force("charge", d3.forceManyBody().strength(-300))
        .force("center", d3.forceCenter(width / 2, height / 2))
        .force("x", d3.forceX(width / 2).strength(0.05))
        .force("y", d3.forceY(height / 2).strength(0.05));

    let link = g.selectAll(".link")
        .data(graph.links)
        .enter().append("line")
        .attr("stroke", "black")
        .attr("stroke-width", 1);

    let node = g.selectAll(".node")
        .data(graph.nodes)
        .enter().append("g")
        .attr("class", "node")
        .call(d3.drag()
            .on("start", dragstarted)
            .on("drag", dragged)
            .on("end", dragended));
    const minYear = graph.minYear;
    const maxYear = graph.maxYear;

    function getTopicColor(year) {
        const colorScale = d3.scaleLinear()
            .domain([minYear, maxYear])
            .range(["#08306b", "#f6f91c"]); // warna gradasi kustom kamu

        return colorScale(year || minYear);

    }


    function getTopicRadius(count) {
        return 10 + Math.sqrt(count) * 3; // Ukuran berdasarkan kemunculan
    }

    const tooltip = d3.select("#tooltip");

    node.each(function(d) {
        const isTopic = d.group === "topic";
        const selection = d3.select(this);
        const size = isTopic ? getTopicRadius(d.count || 1) : 20;
        const fill = isTopic ? getTopicColor(d.year || minYear) : getColorFromString(d.id);

        if (isTopic) {
            const hexagonPoints = [];
            const sides = 6;
            const angleStep = (2 * Math.PI) / sides;

            for (let i = 0; i < sides; i++) {
                const angle = angleStep * i;
                const x = size * Math.cos(angle);
                const y = size * Math.sin(angle);
                hexagonPoints.push([x, y]);
            }

            selection.append("polygon")
                .attr("points", hexagonPoints.map(p => p.join(",")).join(" "))
                .attr("fill", fill)
                .style("pointer-events", "all")
                .on("mouseover", function(event) {
                    tooltip
                        .style("display", "block")
                        .html(`<strong>${d.id}</strong><br>Jumlah: ${d.count}<br>Tahun terakhir: ${d.year}`);
                })
                .on("mousemove", function(event) {
                    tooltip
                        .style("left", (event.clientX + 10) + "px")
                        .style("top", (event.clientY - 30) + "px");
                })
                .on("mouseout", function() {
                    tooltip.style("display", "none");
                });
        } else {
            selection.append("circle")
                .attr("r", size)
                .attr("fill", fill)
                .on("mouseover", function(event) {
                    tooltip
                        .style("display", "block")
                        .html(`<strong>${d.id}</strong>`);
                })
                .on("mousemove", function(event) {
                    tooltip
                        .style("left", (event.clientX + 10) + "px")
                        .style("top", (event.clientY - 30) + "px");
                })
                .on("mouseout", function() {
                    tooltip.style("display", "none");
                });
        }
    });


    node.append("text")
        .attr("dy", 4)
        .attr("dx", d => d.group === "topic" ? -20 : -30)
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
        const baseUrl = window.location.href.split('?')[0]; // Ambil base URL tanpa parameter
        window.location.href = `${baseUrl}?p=author_network&limit=${limit}`;
    }
</script>
<script src="<?php echo SWB; ?>plugins/slims-graph/pages/jspdf.umd.min.js"></script>
</body>
</html>
