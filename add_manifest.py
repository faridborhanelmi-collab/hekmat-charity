import glob

manifest_tag = '    <link rel="manifest" href="manifest.json">\n'

php_files = glob.glob('*.php') + glob.glob('admin/*.php')
for f in php_files:
    try:
        with open(f, 'r', encoding='utf-8') as file:
            content = file.read()
        
        if 'rel="manifest"' not in content and '</head>' in content:
            content = content.replace('</head>', manifest_tag + '</head>')
            with open(f, 'w', encoding='utf-8') as file:
                file.write(content)
            print(f"Added manifest to {f}")
    except Exception as e:
        print(f"Failed on {f}: {e}")
