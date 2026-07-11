import glob

sw_script = """
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js');
    });
  }
</script>
"""

php_files = glob.glob('*.php') + glob.glob('admin/*.php')
for f in php_files:
    try:
        with open(f, 'r', encoding='utf-8') as file:
            content = file.read()
        
        if 'serviceWorker.register' not in content and '</body>' in content:
            content = content.replace('</body>', sw_script + '\n</body>')
            with open(f, 'w', encoding='utf-8') as file:
                file.write(content)
            print(f"Added SW to {f}")
    except Exception as e:
        print(f"Failed on {f}: {e}")
