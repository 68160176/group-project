<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user_id()) redirect('index.php');

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = db_row('SELECT id, username, password_hash FROM users WHERE username = ?', 's', $username);

    // Same message for wrong username or wrong password: don't reveal which one exists
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        redirect('index.php');
    }
    $error = 'username หรือรหัสผ่านไม่ถูกต้อง';
}

$page_title = 'เข้าสู่ระบบ';
require __DIR__ . '/includes/header.php';
?>
<section class="auth">
  <a class="brand brand-lg" href="login.php"><span class="brand-mark" aria-hidden="true">☁️</span>นัดเพื่อน</a>
  <h1>ยินดีต้อนรับกลับมา</h1>
  <p class="muted">เข้าสู่ระบบเพื่อดูวันว่างและนัดกับเพื่อน</p>

  <?php if ($error): ?><p class="error" role="alert"><?= e($error) ?></p><?php endif; ?>

  <form method="post" class="stack">
    <?= csrf_field() ?>
    <div class="field">
      <label for="username">Username</label>
      <input id="username" name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
    </div>
    <div class="field">
      <label for="password">รหัสผ่าน</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">เข้าสู่ระบบ</button>
  </form>
  <p class="muted center">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
