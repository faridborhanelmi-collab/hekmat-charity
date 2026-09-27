<?php
header("Content-Type: application/json; charset=utf-8");
session_start();
require_once "includes/db.php";
require_once "includes/auth.php";

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "لطفاً ابتدا وارد حساب کاربری خود شوید."]);
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$is_admin = isset($_SESSION["is_superadmin"]) && $_SESSION["is_superadmin"];

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    $input = $_POST;
}

$action = $input["action"] ?? "change_self";

if ($action === "admin_reset" && $is_admin) {
    $target_id = (int)($input["target_user_id"] ?? 0);
    $new_pwd = trim($input["new_password"] ?? "");
    
    if ($target_id <= 0 || strlen($new_pwd) < 6) {
        echo json_encode(["success" => false, "message" => "شناسه کاربر نامعتبر یا رمز عبور جدید کوتاه است (حداقل ۶ کاراکتر)."]);
        exit();
    }
    
    $hash = password_hash($new_pwd, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hash, $target_id]);
    
    log_activity("بازنشانی رمز عبور توسط مدیر", "user", $target_id, "مدیر کل برای کاربر شناسه {$target_id} رمز عبور جدید تنظیم کرد.");
    echo json_encode(["success" => true, "message" => "رمز عبور کاربر با موفقیت تغییر کرد."]);
    exit();
}

$current_pwd = trim($input["current_password"] ?? "");
$new_pwd = trim($input["new_password"] ?? "");
$confirm_pwd = trim($input["confirm_password"] ?? "");

if (empty($current_pwd) || empty($new_pwd)) {
    echo json_encode(["success" => false, "message" => "لطفاً تمامی فیلدها را پر کنید."]);
    exit();
}

if ($new_pwd !== $confirm_pwd) {
    echo json_encode(["success" => false, "message" => "رمز عبور جدید و تکرار آن با یکدیگر مطابقت ندارند."]);
    exit();
}

if (strlen($new_pwd) < 8) {
    echo json_encode(["success" => false, "message" => "رمز عبور جدید باید برای امنیت بیشتر حداقل ۸ کاراکتر باشد."]);
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(["success" => false, "message" => "کاربر یافت نشد."]);
    exit();
}

$is_hekmat_pwd = in_array($current_pwd, ["Hekmat", "hekmat", "حکمت", "HEKMAT"]);
$pwd_ok = (
    password_verify($current_pwd, $user["password"]) ||
    $current_pwd === $user["password"] ||
    ($is_hekmat_pwd && (password_verify("Hekmat", $user["password"]) || in_array($user["password"], ["Hekmat", "hekmat", "حکمت"])))
);

if (!$pwd_ok) {
    log_activity("تلاش ناموفق تغییر رمز عبور", "user", $user_id, "تلاش برای تغییر رمز عبور با رمز فعلی نادرست برای کاربر {$user['username']}");
    echo json_encode(["success" => false, "message" => "رمز عبور فعلی نادرست است."]);
    exit();
}

$new_hash = password_hash($new_pwd, PASSWORD_BCRYPT);
$upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
$upd->execute([$new_hash, $user_id]);

log_activity("تغییر رمز عبور", "user", $user_id, "کاربر {$user["username"]} رمز عبور خود را با موفقیت تغییر داد.");

echo json_encode(["success" => true, "message" => "رمز عبور شما با موفقیت به‌روزرسانی شد."]);
exit();
