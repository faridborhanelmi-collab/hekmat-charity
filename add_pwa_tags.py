import os
import glob

tags = """
    <!-- iOS PWA/Homescreen Setup -->
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="بنیاد حکمت">
    <link rel="icon" type="image/png" href="logo.png">
"""

php_files = glob.glob('*.php') + glob.glob('admin/*.php')
for f in php_files:
    try:
        with open(f, 'r', encoding='utf-8') as file:
            content = file.read()
        
        if 'apple-mobile-web-app-capable' not in content and '</head>' in content:
            content = content.replace('</head>', tags + '</head>')
            with open(f, 'w', encoding='utf-8') as file:
                file.write(content)
            print(f"Updated {f}")
    except Exception as e:
        print(f"Failed on {f}: {e}")
