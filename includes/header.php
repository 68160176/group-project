<?php
// Expects: $page_title (string), optional $active (nav key)
$uid = current_user_id();
$pending_count = 0;
if ($uid) {
    $r = db_row("SELECT COUNT(*) AS n FROM group_members WHERE user_id = ? AND status = 'pending'", 'i', $uid);
    $pending_count = (int) $r['n'];
}
$active = $active ?? '';
$flash = take_flash();
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title ?? 'นัดเพื่อน') ?> | นัดเพื่อน</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Anuphan:wght@400;500;600;700&family=Mali:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if ($uid): ?>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">☁️</span>นัดเพื่อน</a>
    <nav class="nav" aria-label="เมนูหลัก">
      <a href="index.php" <?= $active === 'home' ? 'aria-current="page"' : '' ?>>หน้าหลัก<?php if ($pending_count): ?> <span class="badge" aria-label="คำเชิญรอตอบ <?= $pending_count ?> รายการ"><?= $pending_count ?></span><?php endif; ?></a>
      <a href="calendar.php" <?= $active === 'calendar' ? 'aria-current="page"' : '' ?>>ปฏิทิน</a>
      <a href="free_days.php" <?= $active === 'free' ? 'aria-current="page"' : '' ?>>วันว่างของฉัน</a>
      <a href="groups.php" <?= $active === 'groups' ? 'aria-current="page"' : '' ?>>กลุ่ม</a>
    </nav>
    <form method="post" action="logout.php" class="logout">
      <?= csrf_field() ?>
      <span class="who"><?= e($_SESSION['username'] ?? '') ?></span>
      <button type="submit" class="btn btn-ghost btn-sm">ออกจากระบบ</button>
    </form>
  </div>
</header>
<?php endif; ?>
<main class="page <?= $uid ? '' : 'page-auth' ?>">
<?php if ($flash): ?>
  <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div>
<?php endif; ?>
