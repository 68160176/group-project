<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = current_user_id();

$rows = db_rows('SELECT free_date FROM availability WHERE user_id = ? AND free_date >= ? ORDER BY free_date',
    'is', $uid, today());

// Group by month for a calmer read
$by_month = [];
foreach ($rows as $r) {
    $by_month[substr($r['free_date'], 0, 7)][] = $r['free_date'];
}

$page_title = 'วันว่างของฉัน';
$active = 'free';
require __DIR__ . '/includes/header.php';
?>
<h1>วันว่างของฉัน</h1>
<p class="muted lead">ว่างทั้งหมด <?= count($rows) ?> วัน นับจากวันนี้</p>

<?php if (!$rows): ?>
  <div class="panel empty-panel">
    <span class="empty-art" aria-hidden="true">🗓️</span>
    <p class="empty">ยังไม่ได้เลือกวันว่างเลย ไปกดเลือกในปฏิทินได้เลย</p>
    <a class="btn btn-primary" href="calendar.php">เปิดปฏิทิน</a>
  </div>
<?php else: ?>
  <?php foreach ($by_month as $ym => $dates): ?>
    <section class="month-block">
      <h2><?= TH_MONTHS[(int) substr($ym, 5, 2)] ?> <?= (int) substr($ym, 0, 4) + 543 ?> <span class="count"><?= count($dates) ?> วัน</span></h2>
      <ul class="chips">
        <?php foreach ($dates as $d): ?>
          <li class="chip">
            <span><?= e(th_date($d)) ?></span>
            <form method="post" action="calendar.php">
              <?= csrf_field() ?>
              <input type="hidden" name="date" value="<?= $d ?>">
              <input type="hidden" name="back" value="free_days">
              <button type="submit" class="chip-x" aria-label="เอา <?= e(th_date($d)) ?> ออกจากวันว่าง">เอาออก</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
