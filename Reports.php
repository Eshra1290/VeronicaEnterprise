<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
require_once 'db_connect.php';

/* ───── filter flags ───────────────────────────────────────── */
$report_type      = $_GET['report_type'] ?? 'task_progress';
$chosen_date      = trim($_GET['date']   ?? '');
$this_week        = isset($_GET['this_week']);
$this_month       = isset($_GET['this_month']);
$selected_worker  = trim($_GET['worker'] ?? '');
$is_day           = ($chosen_date !== '') && !$this_week && !$this_month;
$show_history     = ($report_type === 'attendance' && isset($_GET['history']) && $selected_worker !== '');

/* helper: work-day count (Mon-Fri) */
function workdays_between($from,$to){
    $days=0;
    for($d=strtotime($from);$d<=strtotime($to);$d=strtotime('+1 day',$d))
        if(date('N',$d)<6)$days++;
    return max($days,1);
}

/* ─── 1. TASK-PROGRESS (unchanged) ─────────────────────────── */
$task_progress=[];$worker_names=$not_started=$in_progress=$completed=[];
if($report_type==='task_progress'){
    $rows=$conn->query("SELECT w.name,
                               SUM(t.status='Not Started') AS ns,
                               SUM(t.status='In Progress') AS ip,
                               SUM(t.status='Completed')   AS c
                          FROM tasks t
                          JOIN users w ON t.worker_id=w.worker_id
                         GROUP BY w.worker_id")->fetch_all(MYSQLI_ASSOC);
    foreach($rows as $r){
        $task_progress[]=$r;
        $worker_names[] =$r['name'];
        $not_started[]  =$r['ns'];
        $in_progress[]  =$r['ip'];
        $completed[]    =$r['c'];
    }
}

/* ─── 2-A. ATTENDANCE SUMMARY (% view) ─────────────────────── */
$attendance=$att_names=$att_counts=$table_counts=[];
if($report_type==='attendance' && !$show_history){

    /* base WHERE + optional partial-name filter */
    $where ="w.role='worker'";
    $types='';$params=[];
    if($selected_worker!==''){
        $where.=" AND w.name LIKE ?";
        $types.='s';
        $params[]='%'.$selected_worker.'%';
    }

    /* date range */
    if($this_week){
        $from=date('Y-m-d',strtotime('monday this week'));
        $to  =date('Y-m-d',strtotime('sunday this week'));
        $expected=5;
    }elseif($this_month){
        $from=date('Y-m-01');$to=date('Y-m-t');
        $expected=workdays_between($from,$to);
    }elseif($is_day){
        $from=$to=$chosen_date;$expected=1;
    }else{
        $this_month=true;$from=date('Y-m-01');$to=date('Y-m-t');
        $expected=workdays_between($from,$to);
    }

    array_unshift($params,$from,$to);
    $types='ss'.$types;

    $stmt=$conn->prepare("SELECT w.name,COUNT(q.id) scans
                            FROM users w
                       LEFT JOIN qr_attendance q
                              ON w.worker_id=q.worker_id
                             AND DATE(q.scanned_at) BETWEEN ? AND ?
                           WHERE $where
                        GROUP BY w.worker_id");
    $stmt->bind_param($types,...$params);
    $stmt->execute();
    $attendance=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach($attendance as $row){
        $pct=min($row['scans'],$expected)/$expected*100;
        $att_names[]  =$row['name'];
        $att_counts[] =round($pct,1);
        $table_counts[]=round($pct,1).' %';
    }
}

/* ─── 2-B. ATTENDANCE HISTORY (timestamp view) ─────────────── */
$history_scans=[];$week_start=$week_end=$week_range_txt='';
$prev_week_start=$next_week_start='';
if($show_history){

    /* look up worker_id once */
    $stmt=$conn->prepare("SELECT worker_id FROM users WHERE name LIKE ? LIMIT 1");
    $like='%'.$selected_worker.'%';
    $stmt->bind_param('s',$like);
    $stmt->execute();
    $stmt->bind_result($worker_id);
    $stmt->fetch();
    $stmt->close();

    if($worker_id){  // only run query if a match exists
        $week_start=$_GET['week_start'] ?? date('Y-m-d',strtotime('monday this week'));
        $week_end  =date('Y-m-d',strtotime($week_start.' +6 days'));

        $stmt=$conn->prepare("SELECT scanned_at
                                FROM qr_attendance
                               WHERE worker_id=?
                                 AND DATE(scanned_at) BETWEEN ? AND ?
                            ORDER BY scanned_at");
        $stmt->bind_param('iss',$worker_id,$week_start,$week_end);
        $stmt->execute();
        $history_scans=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $week_range_txt=date('d M',strtotime($week_start)).' - '.date('d M Y',strtotime($week_end));

        $prev_week_start=date('Y-m-d',strtotime($week_start.' -7 days'));
        $curr_week_start=date('Y-m-d',strtotime('monday this week'));
        if(strtotime($week_start)<strtotime($curr_week_start))
            $next_week_start=date('Y-m-d',strtotime($week_start.' +7 days'));
    }
}
$conn->close();

/* ─── chart texts ──────────────────────────────────────────── */
if($this_week)      $range_txt='(This Week)';
elseif($this_month) $range_txt='(This Month)';
elseif($is_day)     $range_txt='(Day)';
else                $range_txt='';

$chart_label='Attendance % '.$range_txt;
$chart_title='% of Working Days';
$chart_max=100;
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><title>Reports</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
html,body{margin:0;padding:0;height:100%;font-family:Arial,sans-serif}
.wrapper{display:flex;height:100vh}
.sidebar{width:220px;background:#3b2f2f;color:#fff;padding:30px 20px;display:flex;flex-direction:column;gap:20px}
.sidebar a{color:#fff;text-decoration:none;font-weight:bold;padding:10px 0;border-bottom:1px solid #ddd;display:block;transition:.3s}
.sidebar a:hover{background:#f0cfa0;color:#3b2f2f}.sidebar a:last-child{border-bottom:none}
.main{flex-grow:1;display:flex;flex-direction:column}
.topbar{background:#EEE6E2;padding:15px 30px;display:flex;justify-content:space-between;align-items:center}
.content{padding:20px 30px;overflow-y:auto}
.button-group{display:flex;justify-content:center;margin-bottom:20px;gap:10px}
.button-group a,.button-group button{padding:10px 20px;font-weight:bold;border:1px solid #ddd;border-radius:5px;background:#EEE6E2;text-decoration:none;color:#000}
.button-group a:hover,.button-group button:hover{background:#d6c8bf;cursor:pointer}
.report-table{width:100%;border-collapse:collapse;margin-top:20px}
.report-table th,.report-table td{padding:10px;border-bottom:1px solid #ddd;text-align:left}
.report-table th{background:#EEE6E2}
@media print{.sidebar,.button-group{display:none}.content{padding:0}}
</style>
</head><body>
<div class="wrapper">
<div class="sidebar">
  <h2>Admin Panel</h2>
  <a href="admin_dashboard.php">Dashboard</a>
  <a href="TaskSchedule.php">🗓 Task Schedule</a>
  <a href="GenerateAttendance.php">📤 Generate Attendance</a>
  <a href="ActivityMonitoring.php">📈 Activity Monitoring</a>
  <a href="Reports.php?report_type=task_progress">📑 Task Reports</a>
  <a href="StaffManagement.php">👥 Staff Management</a>
  <a href="logout.php">🚪 Logout</a>
</div>

<div class="main">
<div class="topbar"><h2>Reports</h2></div>
<div class="content">

<div class="button-group">
  <a href="Reports.php?report_type=attendance">Attendance Reports</a>
  <a href="Reports.php?report_type=task_progress">Task Progress Reports</a>
</div>

<?php if($report_type==='attendance'): ?>

<?php if($show_history): ?>
  <h3>Attendance History (<?=htmlspecialchars($week_range_txt)?>)</h3>
  <p><strong><?=htmlspecialchars($selected_worker)?></strong></p>

  <div class="button-group">
    <a href="Reports.php?report_type=attendance&history=1&worker=<?=urlencode($selected_worker)?>&week_start=<?=$prev_week_start?>">« Previous Week</a>
    <?php if($next_week_start): ?>
      <a href="Reports.php?report_type=attendance&history=1&worker=<?=urlencode($selected_worker)?>&week_start=<?=$next_week_start?>">Next Week »</a>
    <?php endif; ?>
    <a href="Reports.php?report_type=attendance">Back to Summary</a>
  </div>

  <table class="report-table">
    <thead><tr><th>#</th><th>Date</th><th>Time</th></tr></thead>
    <tbody>
    <?php if(!$history_scans): ?>
      <tr><td colspan="3">No attendance scans for this week.</td></tr>
    <?php else: foreach($history_scans as $i=>$row): ?>
      <tr>
        <td><?=$i+1?></td>
        <td><?=date('Y-m-d',strtotime($row['scanned_at']))?></td>
        <td><?=date('H:i:s',strtotime($row['scanned_at']))?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

<?php else: /* summary view */ ?>
  <h3>Attendance Report</h3>

  <form method="get" style="margin:10px 0;">
    <input type="hidden" name="report_type" value="attendance">
    <label for="date">Date:</label>
    <input type="date" id="date" name="date" value="<?=htmlspecialchars($chosen_date)?>">
    <label for="worker">Worker Name:</label>
    <input type="text" id="worker" name="worker" value="<?=htmlspecialchars($selected_worker)?>" placeholder="Enter worker name">
    <button type="submit">Search</button>
    <button type="submit" name="this_week"  value="1">This&nbsp;Week</button>
    <button type="submit" name="this_month" value="1">This&nbsp;Month</button>
    <button type="submit" name="history"   value="1">View Weekly History</button>
  </form>

  <table class="report-table">
    <thead><tr><th>Worker Name</th><th>Attendance&nbsp;%</th></tr></thead>
    <tbody>
    <?php if(!$attendance): ?>
      <tr><td colspan="2">No attendance data.</td></tr>
    <?php else: foreach($attendance as $i=>$row): ?>
      <tr><td><?=htmlspecialchars($row['name'])?></td><td><?=$table_counts[$i]?></td></tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

  <canvas id="attendanceChart"></canvas>
  <script>
  new Chart(document.getElementById('attendanceChart'),{
    type:'bar',
    data:{
      labels:<?=json_encode($att_names)?>,
      datasets:[{label:'<?=$chart_label?>',data:<?=json_encode($att_counts)?>,
                 backgroundColor:'rgba(255,206,86,.5)',borderColor:'rgba(255,206,86,1)',borderWidth:1}]
    },
    options:{responsive:true,scales:{y:{beginAtZero:true,max:<?=$chart_max?>,
      title:{display:true,text:'<?=$chart_title?>'}}}}
  });
  </script>
<?php endif; ?>

<?php else: /* TASK-PROGRESS */ ?>

  <h3>Task Progress Report</h3>
  <table class="report-table">
    <thead><tr><th>Worker Name</th><th>Not Started</th><th>In Progress</th><th>Completed</th></tr></thead>
    <tbody>
    <?php if(!$task_progress): ?>
      <tr><td colspan="4">No task data.</td></tr>
    <?php else: foreach($task_progress as $r): ?>
      <tr><td><?=htmlspecialchars($r['name'])?></td><td><?=$r['ns']?></td><td><?=$r['ip']?></td><td><?=$r['c']?></td></tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

  <canvas id="taskProgressChart"></canvas>
  <script>
  new Chart(document.getElementById('taskProgressChart'),{
    type:'bar',
    data:{
      labels:<?=json_encode($worker_names)?>,
      datasets:[
        {label:'Not Started',data:<?=json_encode($not_started)?>,backgroundColor:'rgba(255,99,132,.5)'},
        {label:'In Progress',data:<?=json_encode($in_progress)?>,backgroundColor:'rgba(54,162,235,.5)'},
        {label:'Completed',  data:<?=json_encode($completed)?>,  backgroundColor:'rgba(75,192,192,.5)'}
      ]},
      options:{responsive:true,scales:{y:{beginAtZero:true}}}
  });
  </script>

<?php endif; ?>

<div class="button-group"><button onclick="window.print()">Print Report</button></div>
</div></div></div>
</body></html>
