<?php
/* @var int $total @var float $avg @var array $categories @var array $cells
   @var array $indicators @var array $recent */
$catOrder = ['Excluded','Low','Moderate','Included'];
$catCounts = array_map(fn($c) => $categories[$c] ?? 0, $catOrder);
$cellNames = array_keys($cells);
$cellCounts = array_values(array_map(fn($c) => $c['count'], $cells));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Digital Inclusion Dashboard</title>
<link rel="stylesheet" href="assets/app.css"><script src="assets/chart.js"></script></head><body>
<header>
  <strong>Digital Inclusion — Nyarugunga Sector</strong>
  <a href="index.php?page=logout">Logout</a>
</header>
<div class="wrap">
  <div class="cards">
    <div class="card"><span>Respondents</span><b><?= $total ?></b></div>
    <div class="card"><span>Avg inclusion score</span><b><?= $avg ?></b></div>
    <div class="card"><span>% home internet</span><b><?= $indicators['home_internet_pct'] ?>%</b></div>
    <div class="card"><span>% cannot afford data</span><b><?= $indicators['cannot_afford_pct'] ?>%</b></div>
    <div class="card"><span>% no digital skills</span><b><?= $indicators['no_skills_pct'] ?>%</b></div>
  </div>
  <div class="grid">
    <div class="panel"><h3>Category distribution</h3><canvas id="catChart"></canvas></div>
    <div class="panel"><h3>Respondents by cell</h3><canvas id="cellChart"></canvas></div>
    <div class="panel"><h3>Key indicators (%)</h3><canvas id="indChart"></canvas></div>
    <div class="panel">
      <h3>Recent responses</h3>
      <table><tr><th>Cell</th><th>Score</th><th>Category</th><th>When</th></tr>
        <?php foreach ($recent as $r): ?>
        <tr><td><?= htmlspecialchars($r['cell']) ?></td><td><?= $r['score'] ?></td>
            <td><?= htmlspecialchars($r['category']) ?></td><td><?= htmlspecialchars($r['created_at']) ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
<script>
new Chart(catChart, {type:'doughnut', data:{labels:<?= json_encode($catOrder) ?>,
  datasets:[{data:<?= json_encode($catCounts) ?>,
  backgroundColor:['#b00','#e67','#fb4','#1b5']}]}});
new Chart(cellChart, {type:'bar', data:{labels:<?= json_encode($cellNames) ?>,
  datasets:[{label:'Respondents', data:<?= json_encode($cellCounts) ?>, backgroundColor:'#0b3d2e'}]},
  options:{plugins:{legend:{display:false}}}});
new Chart(indChart, {type:'bar', data:{labels:['Home internet','Cannot afford','No skills'],
  datasets:[{label:'%', data:[<?= $indicators['home_internet_pct'] ?>,<?= $indicators['cannot_afford_pct'] ?>,<?= $indicators['no_skills_pct'] ?>],
  backgroundColor:['#1b5','#b00','#e67']}]},
  options:{scales:{y:{max:100}},plugins:{legend:{display:false}}}});
</script>
</body></html>
