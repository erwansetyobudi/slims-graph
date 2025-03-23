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

// Ambil data dari database
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

$query = $db->prepare("
    SELECT t.topic, COUNT(bt.topic_id) AS jumlah
    FROM biblio_topic bt
    JOIN mst_topic t ON bt.topic_id = t.topic_id
    GROUP BY bt.topic_id, t.topic
    ORDER BY jumlah DESC
    LIMIT $limit
");
$query->execute();

$children = [];
while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $children[] = [
        "name" => $row['topic'],
        "value" => (int)$row['jumlah']
    ];
}

$hierarchy = [
    "name" => "Topik",
    "children" => $children
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Visualisasi Topik - Bubble Chart</title>
    <script src="<?php echo SWB; ?>plugins/slims-graph/pages/d3.v6.min.js"></script>
    
    <style>
 
        .chart-container {
            max-width: 1200px;
            margin: auto;
        }
        h2 {
            text-align: left;
            color: #222;
        }
        form {
            display: flex;
            align-items: left;
            gap: 1rem;
            margin-bottom: 1rem;
            justify-content: left;
        }
        input[type="number"] {
            padding: 0.4rem 0.6rem;
            border-radius: 6px;
            border: 1px solid #ccc;
            width: 100px;
        }
        button {
            padding: 0.4rem 0.8rem;
            border: none;
            border-radius: 6px;
            background-color: #007bff;
            color: white;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        button:hover {
            background-color: #0056b3;
        }
        .controls {
            text-align: left;
            margin-bottom: 2rem;
        }
        .description {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            line-height: 1.6;
        }
        .desform {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            
        }
        svg {
            width: 100%;
            height: 800px;
        }
        text {
            font-size: 12px;
            fill: #fff;
            text-anchor: middle;
            pointer-events: none;
        }
        .topic-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        .topic-table th, .topic-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .topic-table th {
            background-color: #007bff;
            color: white;
        }
        .topic-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .topic-table tr:hover {
            background-color: #f1f1f1;
        }
        .layout-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            align-items: start;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .bubble-chart-box {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            padding: 1rem;
        }

        .text tspan {
            dominant-baseline: middle;
        }
        .text {
            font-size: 12px;
            fill: #fff;
            text-anchor: middle;
            pointer-events: none;
            transition: font-size 0.3s ease, fill-opacity 0.3s ease;
        }
        .tooltip {
            position: absolute;
            background-color: rgba(0,0,0,0.75);
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 13px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s;
            z-index: 10;
        }

    </style>
</head>
<div id="tooltip" class="tooltip"></div>
<body>
<!-- 
Plugin Name: SLiMS Graph Network Analysis
Plugin URI: https://github.com/erwansetyobudi/slims-graph
Description: Plugin untuk menampilkan peta jaringan keterkaitan di SLiMS
Version: 1.0.0
Author: Erwan Setyo Budi
Author URI: https://github.com/erwansetyobudi 
-->

<div class="chart-container">
<?php include('graphbar.php'); ?>
    <div class="layout-grid">
        <!-- KIRI -->
        <div class="sidebar">
            <h3 class="p-4">Visualisasi Jumlah Topik dalam Koleksi</h3>
            <div class="desform">
            <form onsubmit="updateLimit(event)">
                <label for="limit">Tampilkan Topik Teratas:</label>
                <input type="number" id="limit" name="limit" value="<?php echo $limit; ?>" min="1">
                <button type="submit">Terapkan</button>
            </form>

            <div class="controls">
                <button onclick="saveAsImage()">💾 Simpan JPG</button>
                <button onclick="shareUrl()">🔗 Salin Link</button>
            </div>
            </div>

            <div class="description">
                <p><strong>Topic Bubble Chart</strong> adalah visualisasi yang menampilkan jumlah kemunculan topik dalam koleksi perpustakaan. Setiap lingkaran mewakili satu topik, di mana ukuran lingkaran menunjukkan frekuensi kemunculannya dalam katalog.</p>
                <p>Visualisasi ini membantu dalam:</p>
                <ul>
                    <li>Mengetahui topik-topik yang paling sering digunakan dalam pengkatalogan.</li>
                    <li>Menganalisis fokus koleksi perpustakaan berdasarkan topik dominan.</li>
                    <li>Menemukan potensi topik yang kurang terwakili.</li>
                </ul>
                <p>Dengan pendekatan visual seperti ini, pengelola perpustakaan dapat mengambil keputusan berbasis data dalam pengembangan koleksi dan perencanaan akuisisi.</p>
            </div>
        </div>

        <!-- KANAN -->
        <div class="bubble-chart-box">
            <svg id="zoomableChart"></svg>
        </div>
    </div>
    <br>
    <!-- TABEL DI BAWAH -->
    <div class="desform">
    <h3>Daftar Topik dan Jumlah Kemunculan</h3>
    <table class="topic-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Topik</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($children as $index => $topic): ?>
                <tr>
                    <td><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($topic['name']); ?></td>
                    <td><?php echo $topic['value']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>


<script>
const data = <?php echo json_encode($hierarchy); ?>;
const width = 800;
const height = 800;

const svg = d3.select("#zoomableChart")
    .attr("viewBox", `0 0 ${width} ${height}`)
    .style("font", "12px sans-serif");

const color = d3.scaleOrdinal(d3.schemeCategory10);
const pack = data => d3.pack()
    .size([width, height])
    .padding(3)(d3.hierarchy(data).sum(d => d.value));

const root = pack(data);
let focus = root;
let view;

const nodes = svg.append("g")
    .selectAll("g")
    .data(root.descendants())
    .join("g")
    .attr("transform", d => `translate(${d.x},${d.y})`)
    .on("click", (event, d) => {
        if (focus !== d) {
            zoom(d);
            event.stopPropagation();
        }
    });

nodes.append("circle")
    .attr("r", d => d.r)
    .attr("fill", d => d.children ? "#ccc" : color(d.data.name));

const tooltip = d3.select("#tooltip");

nodes.select("circle")
    .on("mouseover", function(event, d) {
        if (!d.children) {
            tooltip.transition().duration(200).style("opacity", 1);
            tooltip.html(`<strong>${d.data.name}</strong><br>Jumlah: ${d.data.value}`);
        }
        d3.select(this).attr("stroke", "#000").attr("stroke-width", 2);
    })
    .on("mousemove", function(event) {
        tooltip
            .style("left", (event.pageX + 15) + "px")
            .style("top", (event.pageY - 28) + "px");
    })
    .on("mouseout", function() {
        tooltip.transition().duration(300).style("opacity", 0);
        d3.select(this).attr("stroke", null);
    });


// Tambahkan grup teks yang bisa dimanipulasi
const labels = svg.append("g")
    .attr("pointer-events", "none")
    .attr("text-anchor", "middle")
    .style("font", "12px sans-serif")
    .selectAll("text")
    .data(root.descendants())
    .join("text")
    .style("fill-opacity", d => d.parent === root ? 1 : 0)
    .style("display", d => d.parent === root ? "inline" : "none")
    .each(function(d) {
        const text = d3.select(this);
        const label = d.data.name.length > 15 ? d.data.name.slice(0, 15) + "…" : d.data.name;
        const count = `(${d.data.value})`;

        text.append("tspan")
            .attr("x", 0)
            .attr("dy", "-0.4em")
            .text(label);

        text.append("tspan")
            .attr("x", 0)
            .attr("dy", "1.1em")
            .text(count);
    });

svg.on("click", () => zoom(root));

function zoom(d) {
    const focus0 = focus;
    focus = d;

    const transition = svg.transition()
        .duration(750)
        .tween("zoom", () => {
            const i = d3.interpolateZoom(view, [focus.x, focus.y, focus.r * 2]);
            return t => zoomTo(i(t));
        });

    labels
        .filter(function(d) {
            return d.parent === focus || this.style.display === "inline";
        })
        .transition(transition)
        .style("fill-opacity", d => d.parent === focus ? 1 : 0)
        .on("start", function(d) {
            if (d.parent === focus) this.style.display = "inline";
        })
        .on("end", function(d) {
            if (d.parent !== focus) this.style.display = "none";
        });
}


function zoomTo(v) {
    const k = width / v[2];
    view = v;

    nodes.attr("transform", d =>
        `translate(${(d.x - v[0]) * k + width / 2},${(d.y - v[1]) * k + height / 2})`
    );

    nodes.select("circle").attr("r", d => d.r * k);

    labels.attr("transform", d =>
        `translate(${(d.x - v[0]) * k + width / 2},${(d.y - v[1]) * k + height / 2})`
    );
}


zoomTo([root.x, root.y, root.r * 2]);
</script>




<script src="<?php echo SWB; ?>plugins/slims-graph/pages/html2canvas.min.js"></script>
<script>
    function updateLimit(event) {
        event.preventDefault();
        const limit = document.getElementById("limit").value;
        const baseUrl = window.location.href.split('?')[0];
        window.location.href = `${baseUrl}?p=topic_chart&limit=${limit}`;
    }

    function saveAsImage() {
        html2canvas(document.querySelector('.chart-container')).then(canvas => {
            let link = document.createElement("a");
            link.download = "topic-bubble-chart.jpg";
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
</body>
</html>
