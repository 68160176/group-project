<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = current_user_id();
$error = '';
$title = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title = trim($_POST['title'] ?? '');
    if ($title === '' || mb_strlen($title) > 100) {
        $error = 'ตั้งชื่อกลุ่ม 1-100 ตัวอักษร';
    } else {
        // Group + owner membership must be saved together
        mysqli_begin_transaction($conn);
        try {
            db_exec('INSERT INTO groups_ (owner_id, title) VALUES (?, ?)', 'is', $uid, $title);
            $gid = mysqli_insert_id($conn);
            db_exec("INSERT INTO group_members (group_id, user_id, status) VALUES (?, ?, 'accepted')", 'ii', $gid, $uid);
            mysqli_commit($conn);
            flash('สร้างกลุ่มแล้ว เชิญเพื่อนได้เลย');
            redirect('group.php?id=' . $gid);
        } catch (mysqli_sql_exception $ex) {
            mysqli_rollback($conn);
            error_log($ex->getMessage());
            $error = 'สร้างกลุ่มไม่สำเร็จ ลองใหม่อีกครั้ง';
        }
    }
}

$groups = db_rows(
    "SELECT g.id, g.title, u.username AS owner, g.owner_id,
            (SELECT COUNT(*) FROM group_members m WHERE m.group_id = g.id AND m.status = 'accepted') AS members,
            (SELECT MIN(e.event_date) FROM events e WHERE e.group_id = g.id AND e.event_date >= ?) AS next_event
     FROM groups_ g
     JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = ? AND gm.status = 'accepted'
     JOIN users u ON u.id = g.owner_id
     ORDER BY g.id DESC", 'si', today(), $uid);

$page_title = 'กลุ่ม';
$active = 'groups';
require __DIR__ . '/includes/header.php';
?>
<h1>กลุ่มของฉัน</h1>

<form method="post" class="create-group panel">
  <?= csrf_field() ?>
  <div class="field grow">
    <label for="title">สร้างกลุ่มใหม่</label>
    <input id="title" name="title" maxlength="100" value="<?= e($title) ?>" placeholder="เช่น ทำโปรเจกต์ Database" required>
    <?php if ($error): ?><small class="error"><?= e($error) ?></small><?php endif; ?>
  </div>
  <button type="submit" class="btn btn-primary">สร้างกลุ่ม</button>
</form>

<?php if (!$groups): ?>
  <div class="panel empty-panel">
    <span class="empty-art" aria-hidden="true">🧸</span>
    <p class="empty">ยังไม่มีกลุ่มเลย ตั้งชื่อกลุ่มด้านบน หรือรอเพื่อนชวนก็ได้</p>
  </div>
<?php else: ?>
  <ul class="group-grid">
    <?php foreach ($groups as $g): ?>
      <li>
        <a class="group-card tone-<?= (int) $g['id'] % 5 ?>" href="group.php?id=<?= (int) $g['id'] ?>">
          <strong><?= e($g['title']) ?></strong>
          <span class="muted"><?= (int) $g['members'] ?> คน · หัวหน้า <?= $g['owner_id'] == $uid ? 'คุณ' : e($g['owner']) ?></span>
          <span class="next"><?= $g['next_event'] ? 'นัดถัดไป ' . e(th_date($g['next_event'])) : 'ยังไม่มีนัด' ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
