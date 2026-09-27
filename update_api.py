with open('api-diamond-submit.php', 'r', encoding='utf-8') as f:
    content = f.read()

replacement = """$input_raw = file_get_contents('php://input');
if (empty($input_raw)) {
    $input_raw = @file_get_contents('php://stdin');
}
$data = json_decode($input_raw, true);
if (!$data) {
    $data = $_POST;
}"""

content = content.replace("""$input_raw = file_get_contents('php://input');
$data = json_decode($input_raw, true);

if (!$data) {
    $data = $_POST;
}""", replacement)

with open('api-diamond-submit.php', 'w', encoding='utf-8') as f:
    f.write(content)
print("Updated api-diamond-submit.php")
