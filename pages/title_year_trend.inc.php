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

$yearNow = date('Y');
$startYear = isset($_GET['start_year']) ? (int)$_GET['start_year'] : $yearNow;
$endYear = isset($_GET['end_year']) ? (int)$_GET['end_year'] : $yearNow;

$query = $db->prepare("
    SELECT YEAR(input_date) AS tahun, MONTH(input_date) AS bulan, COUNT(*) AS jumlah
    FROM biblio
    WHERE YEAR(input_date) BETWEEN :start_year AND :end_year
    GROUP BY tahun, bulan
    ORDER BY tahun, bulan
");
$query->execute([
    'start_year' => $startYear,
    'end_year' => $endYear,
]);

$data = [];
while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $data[] = [
        "year" => (int)$row['tahun'],
        "month" => (int)$row['bulan'],
        "count" => (int)$row['jumlah']
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tren Input Buku per Bulan</title>
    <script src="<?php echo SWB; ?>plugins/slims-graph/pages/d3.v6.min.js"></script>
    <style>
        .chart-container {
            max-width: 1000px;
            margin: auto;
            padding: 30px;
        }
        svg {
            width: 100%;
            height: 500px;
            padding: 30px;
        }
        .form-container {
            margin: 20px 0;
            padding: 30px;
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
        }
        input, button {
            padding: 5px 10px;
            margin-right: 10px;
        }
    </style>
    <script src="<?php echo SWB; ?>plugins/slims-graph/pages/html2canvas.min.js"></script>

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

<div class="chart-container">
<?php include('graphbar.php'); ?>
    <h3>Visualisasi Pertumbuhan Input Buku</h3>
    <div class="form-container">
        <form onsubmit="updateYears(event)">
            Tahun Awal:
            <input type="number" class="form-control" name="start_year" id="start_year" value="<?php echo $startYear; ?>" min="2000">
            Tahun Akhir:
            <input type="number" class="form-control"  name="end_year" id="end_year" value="<?php echo $endYear; ?>" min="2000">
            <br>
            <button class="btn btn-primary" type="submit">Terapkan</button>
            <div class="controls" style="margin: 10px 0;">
            <button type="button" class="btn btn-primary" onclick="saveAsImage()">💾 Simpan JPG</button>
            <button type="button" class="btn btn-primary" onclick="shareUrl()">🔗 Salin Link</button>


            </div>

        </form>

    </div>

    <svg id="areaChart"></svg>
    <div class="description"> <p><strong>Title Year Trend Chart</strong> adalah visualisasi area chart yang menampilkan pertumbuhan jumlah input data buku ke dalam sistem katalog perpustakaan berdasarkan waktu.</p> <p>Setiap titik dalam grafik ini merepresentasikan total jumlah buku yang diinput ke dalam sistem pada bulan tertentu di tahun tertentu. Grafik ini membantu dalam:</p> <ul> <li>Melihat pola aktivitas penginputan buku sepanjang waktu.</li> <li>Mengevaluasi efektivitas dan intensitas proses penginputan koleksi.</li> <li>Menentukan periode aktif dalam pengembangan koleksi perpustakaan.</li> </ul> <p>Pengguna dapat memilih rentang tahun tertentu untuk dianalisis menggunakan formulir filter di atas grafik. Dengan demikian, visualisasi ini memberikan fleksibilitas untuk fokus pada periode waktu spesifik, baik untuk keperluan evaluasi tahunan maupun studi tren jangka panjang.</p> <p>Dengan pendekatan visual seperti ini, pengelola perpustakaan dapat mengambil keputusan berbasis data untuk merencanakan strategi pengadaan, inventarisasi, maupun pendistribusian sumber daya manusia dalam proses penginputan data bibliografi.</p> </div>
    <div id="tooltip" class="tooltip"></div>
</div>

<script>
const rawData = <?php echo json_encode($data); ?>;

const svg = d3.select("#areaChart");
const margin = {top: 20, right: 30, bottom: 50, left: 50};
const width = 1000 - margin.left - margin.right;
const height = 500 - margin.top - margin.bottom;

const g = svg
    .attr("viewBox", [0, 0, width + margin.left + margin.right, height + margin.top + margin.bottom])
    .append("g")
    .attr("transform", `translate(${margin.left},${margin.top})`);

// Format data: buat struktur dengan { date, count }
const parseDate = (y, m) => new Date(y, m - 1);

const data = rawData.map(d => ({
    date: parseDate(d.year, d.month),
    count: d.count
}));

// Skala waktu dan nilai
const x = d3.scaleTime()
    .domain(d3.extent(data, d => d.date))
    .range([0, width]);

const y = d3.scaleLinear()
    .domain([0, d3.max(data, d => d.count)]).nice()
    .range([height, 0]);

// Area generator
const area = d3.area()
    .x(d => x(d.date))
    .y0(height)
    .y1(d => y(d.count));

g.append("path")
    .datum(data)
    .attr("fill", "#69b3a2")
    .attr("d", area);

// Sumbu
g.append("g")
    .attr("transform", `translate(0,${height})`)
    .call(d3.axisBottom(x).tickFormat(d3.timeFormat("%b %Y")).ticks(12))
    .selectAll("text")
    .style("text-anchor", "end")
    .attr("transform", "rotate(-40)");

g.append("g")
    .call(d3.axisLeft(y));

// Tooltip
const tooltip = d3.select("#tooltip");

const focusCircle = g.append("circle")
    .attr("r", 4)
    .style("fill", "#000")
    .style("display", "none");

svg.on("mousemove", function(event) {
    const [xm] = d3.pointer(event);
    const bisectDate = d3.bisector(d => d.date).left;
    const x0 = x.invert(xm - margin.left);
    const i = bisectDate(data, x0, 1);
    const d0 = data[i - 1];
    const d1 = data[i];
    const d = x0 - d0?.date > d1?.date - x0 ? d1 : d0;

    if (!d) return;

    focusCircle
        .style("display", null)
        .attr("cx", x(d.date))
        .attr("cy", y(d.count));

    tooltip
        .style("opacity", 1)
        .html(`<strong>${d3.timeFormat("%B %Y")(d.date)}</strong><br>Jumlah: ${d.count}`)
        .style("left", (event.pageX + 15) + "px")
        .style("top", (event.pageY - 28) + "px");
});

svg.on("mouseleave", function() {
    tooltip.style("opacity", 0);
    focusCircle.style("display", "none");
});

function updateYears(event) {
    event.preventDefault();
    const start = document.getElementById('start_year').value;
    const end = document.getElementById('end_year').value;
    const baseUrl = window.location.href.split('?')[0];
    window.location.href = `${baseUrl}?p=title_year_trend&start_year=${start}&end_year=${end}`;
}
function saveAsImage() {
    html2canvas(document.querySelector('.chart-container')).then(canvas => {
        let link = document.createElement("a");
        link.download = "tren-input-buku.jpg";
        link.href = canvas.toDataURL("image/jpeg", 1.0);
        link.click();
    });
}

function shareUrl() {
    const url = window.location.href;
    navigator.clipboard.writeText(url).then(() => {
        alert("Link berhasil disalin!");
    });
}

</script>
</body>
</html>
