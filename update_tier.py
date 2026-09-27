with open('api-diamond-submit.php', 'r', encoding='utf-8') as f:
    code = f.read()

code = code.replace(
    "(full_name, phone, grade, city, school_name, score, total_questions, percentage, time_spent_seconds, answers_detail, ip_address, status)",
    "(full_name, phone, grade, city, school_name, score, total_questions, percentage, time_spent_seconds, answers_detail, ip_address, status, tier)"
)

code = code.replace(
    "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
    "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
)

code = code.replace(
    "json_encode($detailed_answers, JSON_UNESCAPED_UNICODE),\n        $ip_address\n    ]);",
    "json_encode($detailed_answers, JSON_UNESCAPED_UNICODE),\n        $ip_address,\n        $tier\n    ]);"
)

with open('api-diamond-submit.php', 'w', encoding='utf-8') as f:
    f.write(code)

print("Updated tier in api-diamond-submit.php")
