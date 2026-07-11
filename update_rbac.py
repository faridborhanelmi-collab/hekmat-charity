import glob

# 1. Update admin-request-handler.php
with open('admin-request-handler.php', 'r') as f:
    content = f.read()

rbac_check = """
if (!isset($_SESSION['is_superadmin']) || !$_SESSION['is_superadmin']) {
    die(json_encode(['success' => false, 'message' => 'شما فقط دسترسی مشاهده دارید و مجاز به ایجاد تغییرات نیستید.']));
}
"""

if 'is_superadmin' not in content:
    content = content.replace("if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {\n    die(json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']));\n}",
                              "if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {\n    die(json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']));\n}\n" + rbac_check)
    with open('admin-request-handler.php', 'w') as f:
        f.write(content)

# 2. Update admin/*.php files that handle POST
php_files = ['admin/bursary-payments.php', 'admin/sponsorships.php', 'admin/import-statement.php']
rbac_php_check = """
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_SESSION['is_superadmin']) || !$_SESSION['is_superadmin'])) {
    die("شما فقط دسترسی مشاهده دارید و مجاز به ایجاد تغییرات نیستید.");
}
"""

for f in php_files:
    try:
        with open(f, 'r') as file:
            c = file.read()
        if 'is_superadmin' not in c and '$_SERVER' in c:
            c = c.replace("if ($_SERVER['REQUEST_METHOD'] === 'POST')", rbac_php_check + "if ($_SERVER['REQUEST_METHOD'] === 'POST')")
            with open(f, 'w') as file:
                file.write(c)
    except Exception as e:
        pass
