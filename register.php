<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user_id()) redirect('index.php');

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $errors['username'] = 'ใช้ตัวอักษรอังกฤษ ตัวเลข หรือ _ ยาว 3-30 ตัว';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'รหัสผ่านต้องยาวอย่างน้อย 8 ตัว';
    } elseif ($password !== $confirm) {
        $errors['confirm'] = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
    }

    if (!$errors) {
        try {
            db_exec('INSERT INTO users (username, password_hash) VALUES (?, ?)', 'ss',
                $username, password_hash($password, PASSWORD_DEFAULT));
            flash('สมัครสมาชิกแล้ว เข้าสู่ระบบได้เลย');
            redirect('login.php');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1062) {
                $errors['username'] = 'username นี้มีคนใช้แล้ว';
            } else {
                error_log($ex->getMessage());
                $errors['form'] = 'สมัครไม่สำเร็จ ลองใหม่อีกครั้ง';
            }
        }
    }
}

$page_title = 'สมัครสมาชิก';
require __DIR__ . '/includes/header.php';
?>
<section class="auth">
  <a class="brand brand-lg" href="login.php"><span class="brand-mark" aria-hidden="true">☁️</span>นัดเพื่อน</a>
  <h1>มาเริ่มนัดกันเถอะ</h1>
  <p class="muted">บอกวันว่างของคุณ แล้วหาวันที่เพื่อนๆ ว่างตรงกันได้ง่ายๆ</p>

  <?php if (!empty($errors['form'])): ?><p class="error" role="alert"><?= e($errors['form']) ?></p><?php endif; ?>

  <form method="post" class="stack" novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label for="username">Username</label>
      <input id="username" name="username" value="<?= e($username) ?>" autocomplete="username" required
             aria-describedby="username-help<?= isset($errors['username']) ? ' username-err' : '' ?>">
      <small id="username-help" class="help">เพื่อนจะใช้ชื่อนี้เชิญคุณเข้ากลุ่ม</small>
      <?php if (isset($errors['username'])): ?><small id="username-err" class="error"><?= e($errors['username']) ?></small><?php endif; ?>
    </div>
    <div class="field">
      <label for="password">รหัสผ่าน</label>
      <input id="password" name="password" type="password" autocomplete="new-password" required>
      <?php if (isset($errors['password'])): ?><small class="error"><?= e($errors['password']) ?></small><?php endif; ?>
    </div>
    <div class="field">
      <label for="confirm">ยืนยันรหัสผ่าน</label>
      <input id="confirm" name="confirm" type="password" autocomplete="new-password" required>
      <?php if (isset($errors['confirm'])): ?><small class="error"><?= e($errors['confirm']) ?></small><?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary btn-block">สมัครสมาชิก</button>
  </form>
  <p class="muted center">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
