<?php
// session_start();

// if (!isset($_SESSION['staff_id'])) {
//   header('Location: login.php');
//   exit;
// }

declare(strict_types=1);

/**
 * 課題提出用（ログイン無し）なので、
 * 管理画面では “自分の病院だけ” を固定表示にする。
 */
const ADMIN_HOSPITAL_CODE = 'tokyo-clinic';