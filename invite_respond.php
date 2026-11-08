<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
csrf_check();

$uid = current_user_id();
$group_id = (int) ($_POST['group_id'] ?? 0);
$action = $_POST['action'] ?? '';

// WHERE user_id = me: a user can only answer their own invitation
if ($action === 'accept') {
    $n = db_exec("UPDATE group_members SET status = 'accepted'
                  WHERE group_id = ? AND user_id = ? AND status = 'pending'", 'ii', $group_id, $uid);
    if ($n) {
        flash('เข้ากลุ่มแล้ว');
        redirect('group.php?id=' . $group_id);
    }
} elseif ($action === 'decline') {
    $n = db_exec("DELETE FROM group_members
                  WHERE group_id = ? AND user_id = ? AND status = 'pending'", 'ii', $group_id, $uid);
    if ($n) {
        flash('ปฏิเสธคำเชิญแล้ว');
        redirect('index.php');
    }
}

flash('ไม่พบคำเชิญนี้', 'err');
redirect('index.php');
