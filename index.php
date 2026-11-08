<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = current_user_id();
$today = today();

// Events of groups I belong to (accepted only)
$event_sql = "SELECT e.event_date, e.title, e.note, g.id AS group_id, g.title AS group_title
              FROM events e
              JOIN groups_ g ON g.id = e.group_id
              JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = ? AND gm.status = 'accepted'";

// "Midnight notification" replacement: shown when the user opens the site on that day
$today_events = db_rows("$event_sql WHERE e.event_date = ? ORDER BY e.title", 'is', $uid, $today);
$upcoming = db_rows("$event_sql WHERE e.event_date > ? ORDER BY e.event_date LIMIT 5", 'is', $uid, $today);

$invites = db_rows(
    "SELECT g.id, g.title, u.username AS owner
     FROM group_members gm
     JOIN groups_ g ON g.id = gm.group_id
     JOIN users u ON u.id = g.owner_id
     WHERE gm.user_id = ? AND gm.status = 'pending'
     ORDER BY g.id DESC", 'i', $uid);

$free = db_row('SELECT COUNT(*) AS n FROM availability WHERE user_id = ? AND free_date >= ?', 'is', $uid, $today);

$page_title = 'หน้าหลัก';
$active = 'home';
require __DIR__ . '/includes/header.php';
?>
<h1>สวัสดี <?= e($_SESSION['username']) ?> <span class="emo" aria-hidden="true">👋</span></h1>
<p class="muted lead"><?= e(th_date($today)) ?></p>

<?php if ($today_events): ?>
  <section class="today" aria-labelledby="today-h">
    <h2 id="today-h"><span class="emo" aria-hidden="true">🎉</span> วันนี้มีนัดนะ อย่าลืมล่ะ</h2>
    <ul class="today-list">
      <?php foreach ($today_events as $ev): ?>
        <li>
          <a href="group.php?id=<?= (int) $ev['group_id'] ?>">
            <strong><?= e($ev['title']) ?></strong>
            <span>กลุ่ม <?= e($ev['group_title']) ?></span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<div class="dash">
  <section class="panel">
    <h2>คำเชิญเข้ากลุ่ม</h2>
    <?php if (!$invites): ?>
      <p class="empty"><span class="emo" aria-hidden="true">💌</span> ยังไม่มีคำเชิญใหม่ ถ้าเพื่อนชวนเข้ากลุ่ม จะเห็นตรงนี้เลย</p>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($invites as $inv): ?>
          <li class="row">
            <div>
              <strong><?= e($inv['title']) ?></strong>
              <small class="muted">เชิญโดย <?= e($inv['owner']) ?></small>
            </div>
            <form method="post" action="invite_respond.php" class="row-actions">
              <?= csrf_field() ?>
              <input type="hidden" name="group_id" value="<?= (int) $inv['id'] ?>">
              <button name="action" value="accept" class="btn btn-primary btn-sm">รับคำเชิญ</button>
              <button name="action" value="decline" class="btn btn-ghost btn-sm">ปฏิเสธ</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h2>นัดที่จะถึง</h2>
    <?php if (!$upcoming): ?>
      <p class="empty"><span class="emo" aria-hidden="true">🌱</span> ยังไม่มีนัดเลย ลองสร้างกลุ่มแล้วชวนเพื่อนดูสิ</p>
      <a class="btn btn-primary btn-sm" href="groups.php">ไปที่หน้ากลุ่ม</a>
    <?php else: ?>
      <ul class="rows">
        <?php foreach ($upcoming as $ev): ?>
          <li class="row">
            <a class="row-link" href="group.php?id=<?= (int) $ev['group_id'] ?>">
              <strong><?= e($ev['title']) ?></strong>
              <small class="muted"><?= e(th_date($ev['event_date'])) ?> · <?= e($ev['group_title']) ?></small>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="panel panel-tint">
    <h2>วันว่างของฉัน</h2>
    <p class="stat"><?= (int) $free['n'] ?> <span>วันที่ว่างนับจากวันนี้</span></p>
    <a class="btn btn-primary btn-sm" href="calendar.php">เลือกวันว่างในปฏิทิน</a>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
