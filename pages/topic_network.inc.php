<?php
/**
 * File: topic_network.inc.php
 * URL: index.php?p=topic_network
 * Plugin URI: https://github.com/erwansetyobudi/slims-graph
 * Description: Plugin untuk menampilkan peta jaringan keterkaitan di SLiMS
 * Version: 1.0.0
 * Author: Erwan Setyo Budi
 * Author URI: https://github.com/erwansetyobudi/
 */

header("Content-Type: text/html; charset=UTF-8");
header("X-Frame-Options: DENY");


use SLiMS\DB;

if (!defined('INDEX_AUTH')) { die("cannot access this file directly"); }

$db = DB::getInstance();

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 500;
$topic_filter = isset($_GET['topic_filter']) ? trim($_GET['topic_filter']) : '';

$query = $db->prepare("SELECT bt.biblio_id, b.publish_year, t.topic
          FROM biblio_topic bt
          JOIN biblio b ON bt.biblio_id = b.biblio_id
          JOIN mst_topic t ON bt.topic_id = t.topic_id
          LIMIT :limit");
$query->bindValue(':limit', $limit, PDO::PARAM_INT);
$query->execute();
$result = $query;


$topicMap = [];
$topicPairs = [];
$minYear = PHP_INT_MAX;
$maxYear = 0;

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $topic = $row['topic'];
    $year = (int) $row['publish_year'];
    $biblio_id = $row['biblio_id'];

    if (!isset($topicMap[$topic])) {
        $topicMap[$topic] = [
            'id' => $topic,
            'group' => 'topic',
            'year' => $year,
            'count' => 1,
            'biblio' => [$biblio_id]
        ];
    } else {
        $topicMap[$topic]['count']++;
        $topicMap[$topic]['year'] = max($year, $topicMap[$topic]['year']);
        $topicMap[$topic]['biblio'][] = $biblio_id;
    }

    if (is_numeric($year)) {
        $minYear = min($minYear, $year);
        $maxYear = max($maxYear, $year);
    }
}

$topicByBiblio = [];
foreach ($topicMap as $topic => $info) {
    foreach ($info['biblio'] as $bid) {
        $topicByBiblio[$bid][] = $topic;
    }
}

$edges = [];
foreach ($topicByBiblio as $topics) {
    sort($topics);
    for ($i = 0; $i < count($topics); $i++) {
        for ($j = $i + 1; $j < count($topics); $j++) {
            if ($topic_filter === '' || stripos($topics[$i], $topic_filter) !== false || stripos($topics[$j], $topic_filter) !== false) {
                $edges[] = ['source' => $topics[$i], 'target' => $topics[$j]];
            }
        }
    }
}

$filteredTopics = [];
foreach ($edges as $link) {
    $filteredTopics[$link['source']] = true;
    $filteredTopics[$link['target']] = true;
}

$nodes = array_filter($topicMap, function ($node) use ($filteredTopics, $topic_filter) {
    return $topic_filter === '' || isset($filteredTopics[$node['id']]);
});

$graphData = [
    'nodes' => array_values($nodes),
    'links' => $edges,
    'minYear' => $minYear,
    'maxYear' => $maxYear,
    'topic_filter' => $topic_filter
];

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Topic Network</title>
    <script src="<?php echo SWB; ?>plugins/slims-graph/pages/d3.v6.min.js"></script>
    <script src="<?php echo SWB; ?>plugins/slims-graph/pages/html2canvas.min.js"></script>
    <style>
        body { font-family: Arial; margin: 0; background: #ffffff; }
        #graphBox svg { width: 100%; height: 600px; background-color: #fff; }
        .tooltip {
            position: absolute;
            background: rgba(0,0,0,0.7);
            color: #fff;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 13px;
            pointer-events: none;
            display: none;
        }
        .controls {
            margin: 10px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
        }
        .controls form { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
        .controls label { font-weight: bold; }
        .controls input, .controls button {
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }
        .controls button {
            background-color: #007bff;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        .controls button:hover {
            background-color: #0056b3;
        }
        .legend {
            margin: 10px;
        }
        .description {
            background: #fff;
            margin: 10px;
            padding: 10px;
            border-radius: 6px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
<div class="container"> 
<?php include('graphbar.php'); ?> 
<div class="controls">
    <form onsubmit="updateGraph(event)">
        <label for="limit">Jumlah data:</label>
        <input type="number" id="limit" name="limit" min="1" value="<?php echo $limit; ?>">
        <label for="topicFilter">Topik tertentu:</label>
        <input type="text" id="topicFilter" placeholder="Topik..." value="<?php echo htmlspecialchars($topic_filter); ?>">
        <button type="submit">Terapkan</button>
    </form>
    <div class="buttons">
        <button onclick="zoomHandler.scaleBy(svg.transition().duration(500), 1.2)">Zoom In</button>
        <button onclick="zoomHandler.scaleBy(svg.transition().duration(500), 0.8)">Zoom Out</button>
        <button onclick="saveAsImage()">Simpan JPG</button>
        <button onclick="shareUrl()">Bagikan URL</button>
    </div>
</div>
<div id="graphBox">
    <svg></svg>
    <div id="tooltip" class="tooltip"></div>
</div>
<div class="legend">
    <svg width="300" height="65">
        <defs>
            <linearGradient id="yearGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="#08306b" />
                <stop offset="100%" stop-color="#f6f91c" />
            </linearGradient>
        </defs>
        <rect x="0" y="10" width="300" height="20" fill="url(#yearGradient)" />
        <text x="0" y="45" font-size="12px"><?php echo $minYear; ?></text>
        <text x="150" y="45" font-size="12px" text-anchor="middle"><?php echo intval(($minYear + $maxYear) / 2); ?></text>
        <text x="300" y="45" font-size="12px" text-anchor="end"><?php echo $maxYear; ?></text>
        <text x="150" y="62" font-size="13px" text-anchor="middle" fill="#333">Gradasi warna berdasarkan tahun publikasi</text>
    </svg>
</div>
<div class="description">
    <p>Halaman ini menampilkan <strong>jaringan keterkaitan antar topik</strong> berdasarkan kemunculannya dalam bibliografi yang sama. Ukuran node mencerminkan frekuensi kemunculan topik, dan warna mencerminkan tahun publikasi terakhir (biru untuk lama, kuning untuk terbaru).</p>
</div>
</div>
<script>
const graph = <?php echo json_encode($graphData); ?>;
const svg = d3.select("svg");
const width = +svg.node().getBoundingClientRect().width;
const height = +svg.node().getBoundingClientRect().height;
const g = svg.append("g");

const zoomHandler = d3.zoom().on("zoom", (event) => {
    g.attr("transform", event.transform);
});
svg.call(zoomHandler);

const colorScale = d3.scaleLinear()
    .domain([graph.minYear, graph.maxYear])
    .range(["#08306b", "#f6f91c"]);

const tooltip = d3.select("#tooltip");

const simulation = d3.forceSimulation(graph.nodes)
    .force("link", d3.forceLink(graph.links).id(d => d.id).distance(120))
    .force("charge", d3.forceManyBody().strength(-300))
    .force("center", d3.forceCenter(width / 2, height / 2));

const link = g.selectAll(".link")
    .data(graph.links)
    .enter().append("line")
    .attr("stroke", "#aaa");

const node = g.selectAll(".node")
    .data(graph.nodes)
    .enter().append("g")
    .attr("class", "node")
    .call(d3.drag()
        .on("start", dragstarted)
        .on("drag", dragged)
        .on("end", dragended));

node.append("circle")
    .attr("r", d => 10 + Math.sqrt(d.count) * 2.5)
    .attr("fill", d => colorScale(d.year))
    .on("mouseover", (event, d) => {
        tooltip.style("display", "block")
               .html(`<strong>${d.id}</strong><br>Jumlah: ${d.count}<br>Tahun terakhir: ${d.year}`);
    })
    .on("mousemove", event => {
        tooltip.style("left", (event.pageX + 10) + "px")
               .style("top", (event.pageY - 30) + "px");
    })
    .on("mouseout", () => tooltip.style("display", "none"));

node.append("text")
    .text(d => d.id)
    .attr("dx", 12)
    .attr("dy", 4);

simulation.on("tick", () => {
    link
        .attr("x1", d => d.source.x)
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

function updateGraph(event) {
    event.preventDefault();
    const limit = document.getElementById('limit').value;
    const value = document.getElementById('topicFilter').value.trim();
    const url = new URL(window.location.href);
    url.searchParams.set('topic_filter', value);
    url.searchParams.set('limit', limit);
    window.location.href = url.toString();
}

function saveAsImage() {
    html2canvas(document.getElementById("graphBox"), {
        backgroundColor: "#fff"
    }).then(canvas => {
        let link = document.createElement("a");
        link.download = "topic-network.jpg";
        link.href = canvas.toDataURL("image/jpeg", 1.0);
        link.click();
    });
}

function shareUrl() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        alert("URL berhasil disalin ke clipboard!");
    });
}
</script>
<script src="<?php echo SWB; ?>plugins/slims-graph/pages/html2canvas.min.js"></script>
</body>
</html>
