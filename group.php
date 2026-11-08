<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = current_user_id();
$today = today();
$gid = (int) ($_GET['id'] ?? 0);

// Only accepted members may see the group
$group = db_row(
    "SELECT g.id, g.title, g.owner_id FROM groups_ g
     JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = ? AND gm.status = 'accepted'
     WHERE g.id = ?", 'ii', $uid, $gid);
if (!$group) {
    flash('ไม่พบกลุ่มนี้ หรือคุณยังไม่ได้เป็นสมาชิก', 'err');
    redirect('groups.php');
}
$is_owner = (int) $group['owner_id'] === $uid;

// Dates where every accepted member is free
function common_dates($gid, $today)
{
    return array_column(db_rows(
        "SELECT a.free_date
         FROM availability a
         JOIN group_members gm ON gm.user_id = a.user_id AND gm.status = 'accepted'
         WHERE gm.group_id = ? AND a.free_date >= ?
         GROUP BY a.free_date
         HAVING COUNT(*) = (SELECT COUNT(*) FROM group_members WHERE group_id = ? AND status = 'accepted')
         ORDER BY a.free_date", 'isi', $gid, $today, $gid), 'free_date');
}

$errors = [];
$invite_name = '';
$ev_title = $group['title'];
$ev_note = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$is_owner) {
        http_response_code(403);
        exit('เฉพาะหัวหน้ากลุ่มเท่านั้น');
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'invite') {
        $invite_name = trim($_POST['username'] ?? '');
        $target = db_row('SELECT id FROM users WHERE username = ?', 's', $invite_name);
        if (!$target) {
            $errors['invite'] = 'ไม่พบ username นี้';
        } elseif ((int) $target['id'] === $uid) {
            $errors['invite'] = 'คุณอยู่ในกลุ่มนี้แล้ว';
        } else {
            try {
                db_exec("INSERT INTO group_members (group_id, user_id, status) VALUES (?, ?, 'pending')", 'ii', $gid, $target['id']);
                flash('ส่งคำเชิญถึง ' . $invite_name . ' แล้ว');
                redirect('group.php?id=' . $gid);
            } catch (mysqli_sql_exception $ex) {
                if ($ex->getCode() !== 1062) throw $ex;
                $errors['invite'] = $invite_name . ' ถูกเชิญหรืออยู่ในกลุ่มแล้ว';
            }
        }
    } elseif ($action === 'confirm') {
        $date = $_POST['date'] ?? '';
        $ev_title = trim($_POST['title'] ?? '');
        $ev_note = trim($_POST['note'] ?? '');
        // Re-check on the server: only a date everyone is free on can be confirmed
        if (!in_array($date, common_dates($gid, $today), true)) {
            $errors['confirm'] = 'เลือกวันจากรายการวันที่ทุกคนว่าง';
        } elseif ($ev_title === '' || mb_strlen($ev_title) > 100) {
            $errors['confirm'] = 'ใส่หัวข้อนัด 1-100 ตัวอักษร';
        } else {
            db_exec('INSERT INTO events (group_id, event_date, title, note) VALUES (?, ?, ?, ?)', 'isss',
                $gid, $date, $ev_title, $ev_note);
            flash('ยืนยันนัดแล้ว');
            redirect('group.php?id=' . $gid);
        }
    }
}

$members = db_rows(
    "SELECT u.username, gm.status, u.id = ? AS is_owner FROM group_members gm
     JOIN users u ON u.id = gm.user_id
     WHERE gm.group_id = ? ORDER BY is_owner DESC, gm.status, u.username", 'ii', $group['owner_id'], $gid);
$accepted = count(array_filter($members, fn($m) => $m['status'] === 'accepted'));
$common = common_dates($gid, $today);
$accepted_names = array_column(array_filter($members, fn($m) => $m['status'] === 'accepted'), 'username');

// Who is free on each upcoming date (2+ members), most people first
$who_free = db_rows(
    "SELECT a.free_date, COUNT(*) AS n,
            GROUP_CONCAT(u.username ORDER BY u.username SEPARATOR ',') AS names
     FROM availability a
     JOIN group_members gm ON gm.user_id = a.user_id AND gm.group_id = ? AND gm.status = 'accepted'
     JOIN users u ON u.id = a.user_id
     WHERE a.free_date >= ?
     GROUP BY a.free_date
     HAVING COUNT(*) >= 2
     ORDER BY n DESC, a.free_date
     LIMIT 15", 'is', $gid, $today);
$events = db_rows('SELECT event_date, title, note FROM events WHERE group_id = ? ORDER BY event_date DESC', 'i', $gid);

$page_title = $group['title'];
$active = 'groups';
require __DIR__ . '/includes/header.php';
?>
<a class="back" href="groups.php">กลุ่มทั้งหมด</a>
<h1><?= e($group['title']) ?></h1>
<p class="muted lead">สมาชิก <?= $accepted ?> คน<?= $is_owner ? ' · คุณเป็นหัวหน้ากลุ่ม' : '' ?></p>

<div class="group-layout">
  <div class="group-main">
    <section class="panel">
      <h2>วันที่ทุกคนว่างตรงกัน</h2>
      <?php if ($common): ?>
        <p class="muted small">ว่างครบ <?= count($accepted_names) ?> คน: <?= e(implode(', ', $accepted_names)) ?></p>
      <?php endif; ?>
      <?php if (!$common): ?>
        <p class="empty"><span class="emo" aria-hidden="true">🔍</span> ยังไม่มีวันที่ทุกคนว่างตรงกัน ดูด้านล่างว่าวันไหนเกือบได้แล้ว และยังขาดใคร</p>
      <?php elseif (!$is_owner): ?>
        <ul class="chips">
          <?php foreach ($common as $d): ?><li class="chip"><?= e(th_date($d)) ?></li><?php endforeach; ?>
        </ul>
        <p class="muted small">หัวหน้ากลุ่มจะเลือกวันจากรายการนี้เพื่อยืนยันนัด</p>
      <?php else: ?>
        <form method="post" class="stack">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="confirm">
          <fieldset class="pick">
            <legend>เลือกวันนัด</legend>
            <?php foreach ($common as $i => $d): ?>
              <label class="pick-item">
                <input type="radio" name="date" value="<?= $d ?>" <?= $i === 0 ? 'checked' : '' ?>>
                <span><?= e(th_date($d)) ?></span>
              </label>
            <?php endforeach; ?>
          </fieldset>
          <div class="field">
            <label for="ev-title">นัดทำอะไร</label>
            <input id="ev-title" name="title" maxlength="100" value="<?= e($ev_title) ?>" required>
          </div>
          <div class="field">
            <label for="ev-note">โน้ต (ไม่บังคับ)</label>
            <textarea id="ev-note" name="note" rows="3" placeholder="สถานที่ เวลา หรือของที่ต้องเตรียม"><?= e($ev_note) ?></textarea>
          </div>
          <?php if (isset($errors['confirm'])): ?><p class="error" role="alert"><?= e($errors['confirm']) ?></p><?php endif; ?>
          <button type="submit" class="btn btn-primary">ยืนยันนัด</button>
        </form>
      <?php endif; ?>
    </section>

    <?php if (count($accepted_names) > 1): ?>
    <section class="panel">
      <h2>ใครว่างวันไหนบ้าง</h2>
      <?php if (!$who_free): ?>
        <p class="empty"><span class="emo" aria-hidden="true">🌤️</span> ยังไม่มีวันที่ว่างตรงกันตั้งแต่ 2 คนขึ้นไป</p>
      <?php else: ?>
        <ul class="who-list">
          <?php foreach ($who_free as $row):
              $free_names = explode(',', $row['names']);
              $missing = array_diff($accepted_names, $free_names);
              $all = !$missing;
          ?>
            <li class="who-row <?= $all ? 'is-all' : '' ?>">
              <div class="who-head">
                <strong><?= e(th_date($row['free_date'])) ?></strong>
                <span class="tag <?= $all ? '' : 'tag-wait' ?>"><?= $all ? 'ว่างครบทุกคน' : 'ว่าง ' . (int) $row['n'] . '/' . count($accepted_names) . ' คน' ?></span>
              </div>
              <ul class="name-chips" aria-label="คนที่ว่าง">
                <?php foreach ($free_names as $n): ?><li><?= e($n) ?></li><?php endforeach; ?>
              </ul>
              <?php if ($missing): ?>
                <p class="missing">ยังไม่ว่าง: <?= e(implode(', ', $missing)) ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section>
      <h2>นัดของกลุ่ม</h2>
      <?php if (!$events): ?>
        <p class="empty"><span class="emo" aria-hidden="true">📝</span> ยังไม่มีนัดที่ยืนยัน พอหัวหน้ากลุ่มเลือกวันแล้วจะขึ้นเป็นโน้ตตรงนี้</p>
      <?php else: ?>
        <ul class="notes">
          <?php foreach ($events as $ev): ?>
            <li class="note <?= $ev['event_date'] < $today ? 'is-past' : '' ?> <?= $ev['event_date'] === $today ? 'is-today' : '' ?>">
              <time datetime="<?= $ev['event_date'] ?>"><?= e(th_date($ev['event_date'])) ?><?= $ev['event_date'] === $today ? ' (วันนี้)' : '' ?></time>
              <strong><?= e($ev['title']) ?></strong>
              <?php if ($ev['note'] !== null && $ev['note'] !== ''): ?><p><?= nl2br(e($ev['note'])) ?></p><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <aside class="panel group-side">
    <h2>สมาชิก</h2>
    <ul class="members">
      <?php foreach ($members as $i => $m): ?>
        <li>
          <span class="member-name"><span class="avatar tone-<?= $i % 5 ?>" aria-hidden="true"><?= e(mb_substr($m['username'], 0, 1)) ?></span><?= e($m['username']) ?></span>
          <?php if ($m['is_owner']): ?><small class="tag">หัวหน้า</small>
          <?php elseif ($m['status'] === 'pending'): ?><small class="tag tag-wait">รอตอบรับ</small><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <?php if ($is_owner): ?>
      <form method="post" class="stack invite">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="invite">
        <div class="field">
          <label for="invite-user">เชิญด้วย username</label>
          <input id="invite-user" name="username" value="<?= e($invite_name) ?>" autocomplete="off" required>
          <?php if (isset($errors['invite'])): ?><small class="error"><?= e($errors['invite']) ?></small><?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block">ส่งคำเชิญ</button>
      </form>
      <p class="muted small">ระบบนับเฉพาะคนที่รับคำเชิญแล้วตอนหาวันว่าง</p>
    <?php endif; ?>
  </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
