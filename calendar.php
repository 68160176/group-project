<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = current_user_id();
$today = today();

// ---------- Toggle a free day (also used by free_days.php) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $date = $_POST['date'] ?? '';
    if (!valid_date($date) || $date < $today) {
        flash('เลือกได้เฉพาะวันนี้หรือวันถัดไป', 'err');
    } elseif (db_exec('DELETE FROM availability WHERE user_id = ? AND free_date = ?', 'is', $uid, $date) === 0) {
        // Nothing deleted means it wasn't free yet: mark it free
        db_exec('INSERT INTO availability (user_id, free_date) VALUES (?, ?)', 'is', $uid, $date);
    }
    // Only allow known pages as redirect targets
    if (($_POST['back'] ?? '') === 'free_days') redirect('free_days.php');
    redirect('calendar.php?m=' . substr($date, 0, 7));
}

// ---------- Which month to show ----------
$m = $_GET['m'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $m) || !valid_date("$m-01")) $m = date('Y-m');
$first = "$m-01";
$days_in_month = (int) date('t', strtotime($first));
$last = "$m-" . str_pad($days_in_month, 2, '0', STR_PAD_LEFT);
$lead = (int) date('N', strtotime($first)) - 1; // Monday-first grid
$prev = date('Y-m', strtotime("$first -1 month"));
$next = date('Y-m', strtotime("$first +1 month"));

$free = array_column(
    db_rows('SELECT free_date FROM availability WHERE user_id = ? AND free_date BETWEEN ? AND ?', 'iss', $uid, $first, $last),
    'free_date');
$free = array_flip($free);

$events = [];
foreach (db_rows(
    "SELECT e.event_date, e.title FROM events e
     JOIN group_members gm ON gm.group_id = e.group_id AND gm.user_id = ? AND gm.status = 'accepted'
     WHERE e.event_date BETWEEN ? AND ? ORDER BY e.event_date", 'iss', $uid, $first, $last) as $ev) {
    $events[$ev['event_date']][] = $ev['title'];
}

$page_title = 'ปฏิทิน';
$active = 'calendar';
require __DIR__ . '/includes/header.php';
$mon = (int) substr($m, 5, 2);
?>
<div class="cal-head">
  <h1><?= TH_MONTHS[$mon] ?> <?= (int) substr($m, 0, 4) + 543 ?></h1>
  <div class="cal-nav">
    <a class="btn btn-ghost btn-sm" href="?m=<?= $prev ?>" aria-label="เดือนก่อน">เดือนก่อน</a>
    <a class="btn btn-ghost btn-sm" href="?m=<?= date('Y-m') ?>">เดือนนี้</a>
    <a class="btn btn-ghost btn-sm" href="?m=<?= $next ?>" aria-label="เดือนถัดไป">เดือนถัดไป</a>
  </div>
</div>
<p class="muted">กดที่วันเพื่อสลับระหว่าง ว่าง และ ไม่ว่าง ข้อมูลบันทึกทันที</p>

<div class="legend" aria-hidden="true">
  <span><i class="key key-free"></i>ว่าง</span>
  <span><i class="key key-event"></i>มีนัดกลุ่ม</span>
  <span><i class="key key-today"></i>วันนี้</span>
</div>

<form method="post" class="cal">
  <?= csrf_field() ?>
  <?php foreach (['จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส', 'อา'] as $d): ?>
    <div class="cal-dow"><?= $d ?></div>
  <?php endforeach; ?>

  <?php for ($i = 0; $i < $lead; $i++): ?><div class="cal-blank"></div><?php endfor; ?>

  <?php for ($d = 1; $d <= $days_in_month; $d++):
      $date = "$m-" . str_pad($d, 2, '0', STR_PAD_LEFT);
      $is_free = isset($free[$date]);
      $past = $date < $today;
      $cls = 'day' . ($is_free ? ' is-free' : '') . ($past ? ' is-past' : '') . ($date === $today ? ' is-today' : '')
           . (isset($events[$date]) ? ' has-event' : '');
      $label = th_date($date) . ($is_free ? ' ว่าง' : ' ไม่ว่าง') . (isset($events[$date]) ? ' มีนัด: ' . implode(', ', $events[$date]) : '');
  ?>
    <button type="submit" name="date" value="<?= $date ?>" class="<?= $cls ?>"
            aria-pressed="<?= $is_free ? 'true' : 'false' ?>" aria-label="<?= e($label) ?>" <?= $past ? 'disabled' : '' ?>>
      <span class="day-num"><?= $d ?></span>
      <?php if (isset($events[$date])): ?>
        <span class="day-event"><?= e($events[$date][0]) ?><?= count($events[$date]) > 1 ? ' +' . (count($events[$date]) - 1) : '' ?></span>
      <?php elseif ($is_free): ?>
        <span class="day-state">ว่าง</span>
      <?php endif; ?>
    </button>
  <?php endfor; ?>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
