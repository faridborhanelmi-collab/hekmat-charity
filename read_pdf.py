import sys

def try_read():
    # Try pypdf
    try:
        from pypdf import PdfReader
        print("Using pypdf...")
        reader = PdfReader("about.pdf")
        text = ""
        for page in reader.pages:
            text += page.extract_text() + "\n"
        return text
    except ImportError:
        pass
    except Exception as e:
        print(f"pypdf error: {e}")

    # Try PyPDF2
    try:
        import PyPDF2
        print("Using PyPDF2...")
        reader = PyPDF2.PdfReader("about.pdf")
        text = ""
        for page in reader.pages:
            text += page.extract_text() + "\n"
        return text
    except ImportError:
        pass
    except Exception as e:
        print(f"PyPDF2 error: {e}")
    
    return None

text = try_read()
if text:
    print("--- EXTRACTED TEXT ---")
    print(text)
else:
    print("COULD NOT READ PDF")
