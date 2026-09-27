import json

questions = [
    {
        "id": 1,
        "category": "ماتریس ۲×۲: تبدیل پوسته به پرشدگی",
        "title": "کدام گزینه، جای خالی (؟) در ماتریس بالا را به درستی کامل می‌کند؟",
        "graphic": """<div class="inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto">
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><circle cx="40" cy="40" r="28" fill="none" stroke="#38bdf8" stroke-width="5"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><circle cx="40" cy="40" r="28" fill="#38bdf8"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><rect x="15" y="15" width="50" height="50" fill="none" stroke="#fbbf24" stroke-width="5" rx="4"/></svg>
            </div>
            <div class="w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black shadow-[0_0_15px_rgba(45,212,191,0.2)]">
                ?
            </div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="15" y="15" width="50" height="50" fill="none" stroke="#fbbf24" stroke-width="5" rx="4"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="15" y="15" width="50" height="50" fill="#fbbf24" rx="4"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,15 15,65 65,65" fill="#fbbf24"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="40" cy="40" r="25" fill="#fbbf24"/></svg>"""
        ],
        "correct": 2
    },
    {
        "id": 2,
        "category": "ماتریس ۲×۲: تصاعد شمارش عناصر",
        "title": "کدام گزینه، جای خالی (؟) در ماتریس بالا را به درستی کامل می‌کند؟",
        "graphic": """<div class="inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto">
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><circle cx="40" cy="40" r="12" fill="#34d399"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><circle cx="26" cy="40" r="10" fill="#34d399"/><circle cx="54" cy="40" r="10" fill="#34d399"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><polygon points="26,30 36,40 26,50 16,40" fill="#c084fc"/><polygon points="54,30 64,40 54,50 44,40" fill="#c084fc"/></svg>
            </div>
            <div class="w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black shadow-[0_0_15px_rgba(45,212,191,0.2)]">
                ?
            </div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,28 52,40 40,52 28,40" fill="#c084fc"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="26,30 36,40 26,50 16,40" fill="#c084fc"/><polygon points="54,30 64,40 54,50 44,40" fill="#c084fc"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="18,32 26,40 18,48 10,40" fill="#c084fc"/><polygon points="40,32 48,40 40,48 32,40" fill="#c084fc"/><polygon points="62,32 70,40 62,48 54,40" fill="#c084fc"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="20" cy="40" r="8" fill="#c084fc"/><circle cx="40" cy="40" r="8" fill="#c084fc"/><circle cx="60" cy="40" r="8" fill="#c084fc"/><circle cx="40" cy="20" r="8" fill="#c084fc"/></svg>"""
        ],
        "correct": 3
    },
    {
        "id": 3,
        "category": "دنباله هندسی: افزایش اضلاع (Raven Set C)",
        "title": "کدام شکل هندسی، گام چهارم از این دنباله قانون‌مند است؟",
        "graphic": """<div class="grid grid-cols-4 gap-2 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 max-w-sm mx-auto">
            <div class="h-20 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 60 60" class="w-12 h-12"><polygon points="30,12 10,48 50,48" fill="#38bdf8"/></svg>
            </div>
            <div class="h-20 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 60 60" class="w-12 h-12"><rect x="14" y="14" width="32" height="32" fill="#38bdf8" rx="2"/></svg>
            </div>
            <div class="h-20 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 60 60" class="w-12 h-12"><polygon points="30,10 50,25 42,48 18,48 10,25" fill="#38bdf8"/></svg>
            </div>
            <div class="h-20 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-2xl font-black">
                ?
            </div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="40" cy="40" r="26" fill="#38bdf8"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,12 60,20 68,40 60,60 40,68 20,60 12,40 20,20" fill="#38bdf8"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><path d="M30 15 H50 V30 H65 V50 H50 V65 H30 V50 H15 V30 H30 Z" fill="#38bdf8"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,12 64,26 64,54 40,68 16,54 16,26" fill="#38bdf8"/></svg>"""
        ],
        "correct": 4
    },
    {
        "id": 4,
        "category": "دوران فضایی: چرخش ۹۰ درجه ساعتگرد",
        "title": "کدام گزینه، جای خالی (؟) در ماتریس جهت‌ها را به درستی کامل می‌کند؟",
        "graphic": """<div class="inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto">
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><line x1="40" y1="65" x2="40" y2="18" stroke="#f43f5e" stroke-width="6" stroke-linecap="round"/><polygon points="40,10 26,26 54,26" fill="#f43f5e"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><line x1="15" y1="40" x2="62" y2="40" stroke="#f43f5e" stroke-width="6" stroke-linecap="round"/><polygon points="70,40 54,26 54,54" fill="#f43f5e"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><line x1="40" y1="15" x2="40" y2="62" stroke="#f43f5e" stroke-width="6" stroke-linecap="round"/><polygon points="40,70 26,54 54,54" fill="#f43f5e"/></svg>
            </div>
            <div class="w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black">
                ?
            </div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><line x1="65" y1="40" x2="18" y2="40" stroke="#f43f5e" stroke-width="6" stroke-linecap="round"/><polygon points="10,40 26,26 26,54" fill="#f43f5e"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><line x1="15" y1="40" x2="62" y2="40" stroke="#f43f5e" stroke-width="6" stroke-linecap="round"/><polygon points="70,40 54,26 54,54" fill="#f43f5e"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><line x1="40" y1="65" x2="40" y2="18" stroke="#f43f5e" stroke-width="6" stroke-linecap="round"/><polygon points="40,10 26,26 54,26" fill="#f43f5e"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><line x1="60" y1="20" x2="25" y2="55" stroke="#f43f5e" stroke-width="6" stroke-linecap="round"/><polygon points="20,60 35,55 25,45" fill="#f43f5e"/></svg>"""
        ],
        "correct": 1
    },
    {
        "id": 5,
        "category": "اشکال هم‌مرکز و جای‌گیری درونی",
        "title": "کدام گزینه، جای خالی (؟) در ماتریس بالا را به درستی کامل می‌کند؟",
        "graphic": """<div class="inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto">
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><rect x="14" y="14" width="52" height="52" fill="none" stroke="#2dd4bf" stroke-width="4" rx="4"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><rect x="14" y="14" width="52" height="52" fill="none" stroke="#2dd4bf" stroke-width="4" rx="4"/><circle cx="40" cy="40" r="14" fill="#38bdf8"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><polygon points="40,12 68,66 12,66" fill="none" stroke="#fbbf24" stroke-width="4"/></svg>
            </div>
            <div class="w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black">
                ?
            </div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,12 68,66 12,66" fill="none" stroke="#fbbf24" stroke-width="4"/><rect x="30" y="38" width="20" height="20" fill="#38bdf8"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="40" cy="40" r="26" fill="none" stroke="#fbbf24" stroke-width="4"/><polygon points="40,25 55,55 25,55" fill="#38bdf8"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,12 68,66 12,66" fill="none" stroke="#fbbf24" stroke-width="4"/><circle cx="40" cy="48" r="12" fill="#38bdf8"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,12 68,66 12,66" fill="#fbbf24"/></svg>"""
        ],
        "correct": 3
    },
    {
        "id": 6,
        "category": "تقاطع و تکمیل متقارن خطوط",
        "title": "کدام گزینه، جای خالی (؟) در ماتریس بالا را به درستی کامل می‌کند؟",
        "graphic": """<div class="inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto">
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><line x1="15" y1="40" x2="65" y2="40" stroke="#a78bfa" stroke-width="6" stroke-linecap="round"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><line x1="15" y1="40" x2="65" y2="40" stroke="#a78bfa" stroke-width="6" stroke-linecap="round"/><line x1="40" y1="15" x2="40" y2="65" stroke="#a78bfa" stroke-width="6" stroke-linecap="round"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><line x1="18" y1="18" x2="62" y2="62" stroke="#34d399" stroke-width="6" stroke-linecap="round"/></svg>
            </div>
            <div class="w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black">
                ?
            </div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><line x1="15" y1="40" x2="65" y2="40" stroke="#34d399" stroke-width="6"/><line x1="40" y1="15" x2="40" y2="65" stroke="#34d399" stroke-width="6"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><line x1="18" y1="18" x2="62" y2="62" stroke="#34d399" stroke-width="6" stroke-linecap="round"/><line x1="62" y1="18" x2="18" y2="62" stroke="#34d399" stroke-width="6" stroke-linecap="round"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><line x1="15" y1="25" x2="65" y2="25" stroke="#34d399" stroke-width="6"/><line x1="15" y1="55" x2="65" y2="55" stroke="#34d399" stroke-width="6"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><line x1="62" y1="18" x2="18" y2="62" stroke="#34d399" stroke-width="6" stroke-linecap="round"/></svg>"""
        ],
        "correct": 2
    },
    {
        "id": 7,
        "category": "موقعیت ماهواره‌ای و انتقال نقطه‌ای در رئوس",
        "title": "کدام گزینه، جای خالی (؟) در ماتریس بالا را به درستی کامل می‌کند؟",
        "graphic": """<div class="inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto">
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><rect x="18" y="18" width="44" height="44" fill="none" stroke="#64748b" stroke-width="3"/><circle cx="18" cy="18" r="7" fill="#f43f5e"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><rect x="18" y="18" width="44" height="44" fill="none" stroke="#64748b" stroke-width="3"/><circle cx="62" cy="18" r="7" fill="#f43f5e"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><rect x="18" y="18" width="44" height="44" fill="none" stroke="#64748b" stroke-width="3"/><circle cx="18" cy="62" r="7" fill="#f43f5e"/></svg>
            </div>
            <div class="w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black">
                ?
            </div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="18" y="18" width="44" height="44" fill="none" stroke="#64748b" stroke-width="3"/><circle cx="40" cy="40" r="7" fill="#f43f5e"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="18" y="18" width="44" height="44" fill="none" stroke="#64748b" stroke-width="3"/><circle cx="18" cy="18" r="7" fill="#f43f5e"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="18" y="18" width="44" height="44" fill="none" stroke="#64748b" stroke-width="3"/><circle cx="62" cy="18" r="7" fill="#f43f5e"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="18" y="18" width="44" height="44" fill="none" stroke="#64748b" stroke-width="3"/><circle cx="62" cy="62" r="7" fill="#f43f5e"/></svg>"""
        ],
        "correct": 4
    },
    {
        "id": 8,
        "category": "انعکاس عمودی ۱۸۰ درجه و تغییر حالت سطح",
        "title": "کدام گزینه، جای خالی (؟) در ماتریس بالا را به درستی کامل می‌کند؟",
        "graphic": """<div class="inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto">
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><polygon points="40,15 65,65 15,65" fill="none" stroke="#38bdf8" stroke-width="4"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><polygon points="40,65 65,15 15,15" fill="#38bdf8"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><polygon points="40,15 65,35 55,68 25,68 15,35" fill="none" stroke="#fbbf24" stroke-width="4"/></svg>
            </div>
            <div class="w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black">
                ?
            </div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,65 65,45 55,12 25,12 15,45" fill="#fbbf24"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,15 65,35 55,68 25,68 15,35" fill="#fbbf24"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,65 65,45 55,12 25,12 15,45" fill="none" stroke="#fbbf24" stroke-width="4"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,65 65,15 15,15" fill="#fbbf24"/></svg>"""
        ],
        "correct": 1
    },
    {
        "id": 9,
        "category": "ماتریس ۳×۳: گرادیان مقیاس و اندازه (Raven APM)",
        "title": "کدام گزینه، خانه نهم از این ماتریس استاندارد را کامل می‌کند؟",
        "graphic": """<div class="inline-grid grid-cols-3 gap-2 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 max-w-xs mx-auto">
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="30" cy="30" r="8" fill="#2dd4bf"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="30" cy="30" r="14" fill="#2dd4bf"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="30" cy="30" r="22" fill="#2dd4bf"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><polygon points="30,20 20,40 40,40" fill="#a78bfa"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><polygon points="30,14 14,46 46,46" fill="#a78bfa"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><polygon points="30,8 6,52 54,52" fill="#a78bfa"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><rect x="22" y="22" width="16" height="16" fill="#fbbf24"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><rect x="16" y="16" width="28" height="28" fill="#fbbf24"/></svg></div>
            <div class="w-16 h-16 bg-teal-950/40 rounded-lg border border-dashed border-teal-400 flex items-center justify-center text-teal-300 font-bold text-xl">?</div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="28" y="28" width="24" height="24" fill="#fbbf24"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="20" y="20" width="40" height="40" fill="#fbbf24"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="10" y="10" width="60" height="60" fill="#fbbf24" rx="4"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="40" cy="40" r="30" fill="#fbbf24"/></svg>"""
        ],
        "correct": 3
    },
    {
        "id": 10,
        "category": "ماتریس جمع و ادغام عناصر (A + B = C)",
        "title": "کدام گزینه، حاصل انطباق و ترکیب دو شکل سطر دوم را نشان می‌دهد؟",
        "graphic": """<div class="inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto">
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><circle cx="40" cy="40" r="28" fill="none" stroke="#38bdf8" stroke-width="4"/><line x1="40" y1="20" x2="40" y2="60" stroke="#f43f5e" stroke-width="4"/><line x1="20" y1="40" x2="60" y2="40" stroke="#f43f5e" stroke-width="4"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><circle cx="40" cy="40" r="28" fill="none" stroke="#38bdf8" stroke-width="4"/><line x1="40" y1="20" x2="40" y2="60" stroke="#f43f5e" stroke-width="4"/><line x1="20" y1="40" x2="60" y2="40" stroke="#f43f5e" stroke-width="4"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><polygon points="40,12 68,40 40,68 12,40" fill="none" stroke="#34d399" stroke-width="4"/><line x1="26" y1="26" x2="54" y2="54" stroke="#fbbf24" stroke-width="4"/><line x1="54" y1="26" x2="26" y2="54" stroke="#fbbf24" stroke-width="4"/></svg>
            </div>
            <div class="w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black">
                ?
            </div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="40" cy="40" r="28" fill="none" stroke="#34d399" stroke-width="4"/><line x1="26" y1="26" x2="54" y2="54" stroke="#fbbf24" stroke-width="4"/><line x1="54" y1="26" x2="26" y2="54" stroke="#fbbf24" stroke-width="4"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,12 68,40 40,68 12,40" fill="none" stroke="#34d399" stroke-width="4"/><line x1="26" y1="26" x2="54" y2="54" stroke="#fbbf24" stroke-width="4"/><line x1="54" y1="26" x2="26" y2="54" stroke="#fbbf24" stroke-width="4"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,12 68,40 40,68 12,40" fill="none" stroke="#34d399" stroke-width="4"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="15" y="15" width="50" height="50" fill="none" stroke="#34d399" stroke-width="4"/><line x1="26" y1="26" x2="54" y2="54" stroke="#fbbf24" stroke-width="4"/><line x1="54" y1="26" x2="26" y2="54" stroke="#fbbf24" stroke-width="4"/></svg>"""
        ],
        "correct": 2
    },
    {
        "id": 11,
        "category": "ماتریس توزیع ویژگی‌ها (مربع لاتین - SPM Set E)",
        "title": "با توجه به قانون سطرها و ستون‌ها، کدام قطعه در خانه (؟) قرار می‌گیرد؟",
        "graphic": """<div class="inline-grid grid-cols-3 gap-2 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 max-w-xs mx-auto">
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="30" cy="30" r="18" fill="#38bdf8"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><rect x="14" y="14" width="32" height="32" fill="none" stroke="#38bdf8" stroke-width="3" stroke-dasharray="3"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><polygon points="30,12 12,48 48,48" fill="none" stroke="#38bdf8" stroke-width="3"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><rect x="14" y="14" width="32" height="32" fill="none" stroke="#38bdf8" stroke-width="3"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><polygon points="30,12 12,48 48,48" fill="#38bdf8"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="30" cy="30" r="18" fill="none" stroke="#38bdf8" stroke-width="3" stroke-dasharray="3"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><polygon points="30,12 12,48 48,48" fill="none" stroke="#38bdf8" stroke-width="3" stroke-dasharray="3"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="30" cy="30" r="18" fill="none" stroke="#38bdf8" stroke-width="3"/></svg></div>
            <div class="w-16 h-16 bg-teal-950/40 rounded-lg border border-dashed border-teal-400 flex items-center justify-center text-teal-300 font-bold text-xl">?</div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="18" y="18" width="44" height="44" fill="none" stroke="#38bdf8" stroke-width="4"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="18" y="18" width="44" height="44" fill="none" stroke="#38bdf8" stroke-width="4" stroke-dasharray="4"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><polygon points="40,14 16,66 64,66" fill="#38bdf8"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="18" y="18" width="44" height="44" fill="#38bdf8" rx="2"/></svg>"""
        ],
        "correct": 4
    },
    {
        "id": 12,
        "category": "دوران زاویه‌ای قطاع‌های دایره‌ای (Pie Slices)",
        "title": "کدام دایره قطاعی، جای خالی (؟) در ماتریس را به درستی کامل می‌کند؟",
        "graphic": """<div class="inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto">
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><circle cx="40" cy="40" r="28" fill="none" stroke="#64748b" stroke-width="3"/><path d="M40 40 L40 12 A28 28 0 0 1 68 40 Z" fill="#2dd4bf"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><circle cx="40" cy="40" r="28" fill="none" stroke="#64748b" stroke-width="3"/><path d="M40 40 L68 40 A28 28 0 0 1 40 68 Z" fill="#2dd4bf"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><circle cx="40" cy="40" r="28" fill="none" stroke="#64748b" stroke-width="3"/><path d="M40 40 L40 68 A28 28 0 0 1 12 40 Z" fill="#2dd4bf"/></svg>
            </div>
            <div class="w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black">
                ?
            </div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="40" cy="40" r="28" fill="none" stroke="#64748b" stroke-width="3"/><path d="M40 40 L68 40 A28 28 0 0 1 40 68 Z" fill="#2dd4bf"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="40" cy="40" r="28" fill="none" stroke="#64748b" stroke-width="3"/><path d="M40 40 L40 68 A28 28 0 0 1 12 40 Z" fill="#2dd4bf"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="40" cy="40" r="28" fill="none" stroke="#64748b" stroke-width="3"/><path d="M40 40 L12 40 A28 28 0 0 1 40 12 Z" fill="#2dd4bf"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="40" cy="40" r="28" fill="#2dd4bf"/></svg>"""
        ],
        "correct": 3
    },
    {
        "id": 13,
        "category": "برهم‌نهی منطقی خطوط (Raven Advanced APM)",
        "title": "کدام گزینه، جای خالی (؟) در این ترکیب برهم‌نهی خطوط را پر می‌کند؟",
        "graphic": """<div class="inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto">
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><line x1="15" y1="40" x2="65" y2="40" stroke="#38bdf8" stroke-width="6"/><line x1="40" y1="15" x2="40" y2="65" stroke="#38bdf8" stroke-width="6"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><line x1="15" y1="40" x2="65" y2="40" stroke="#38bdf8" stroke-width="6"/><line x1="40" y1="15" x2="40" y2="65" stroke="#38bdf8" stroke-width="6"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><line x1="18" y1="18" x2="62" y2="62" stroke="#f59e0b" stroke-width="6"/><line x1="62" y1="18" x2="18" y2="62" stroke="#f59e0b" stroke-width="6"/></svg>
            </div>
            <div class="w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black">
                ?
            </div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><line x1="18" y1="18" x2="62" y2="62" stroke="#f59e0b" stroke-width="6"/><line x1="62" y1="18" x2="18" y2="62" stroke="#f59e0b" stroke-width="6"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><line x1="15" y1="40" x2="65" y2="40" stroke="#f59e0b" stroke-width="6"/><line x1="40" y1="15" x2="40" y2="65" stroke="#f59e0b" stroke-width="6"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><line x1="18" y1="18" x2="62" y2="62" stroke="#f59e0b" stroke-width="6"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="20" y="20" width="40" height="40" fill="none" stroke="#f59e0b" stroke-width="4"/></svg>"""
        ],
        "correct": 1
    },
    {
        "id": 14,
        "category": "ماتریس تصاعد کمی نقاط (Naglieri NNAT3)",
        "title": "کدام آرایش نقطه‌ای، جای خالی (؟) در ماتریس را به درستی کامل می‌کند؟",
        "graphic": """<div class="inline-grid grid-cols-3 gap-2 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 max-w-xs mx-auto">
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="30" cy="30" r="5" fill="#38bdf8"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="20" cy="30" r="5" fill="#38bdf8"/><circle cx="40" cy="30" r="5" fill="#38bdf8"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="16" cy="30" r="4.5" fill="#38bdf8"/><circle cx="30" cy="30" r="4.5" fill="#38bdf8"/><circle cx="44" cy="30" r="4.5" fill="#38bdf8"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="20" cy="30" r="5" fill="#34d399"/><circle cx="40" cy="30" r="5" fill="#34d399"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="16" cy="30" r="4.5" fill="#34d399"/><circle cx="30" cy="30" r="4.5" fill="#34d399"/><circle cx="44" cy="30" r="4.5" fill="#34d399"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="20" cy="20" r="4.5" fill="#34d399"/><circle cx="40" cy="20" r="4.5" fill="#34d399"/><circle cx="20" cy="40" r="4.5" fill="#34d399"/><circle cx="40" cy="40" r="4.5" fill="#34d399"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="16" cy="30" r="4.5" fill="#c084fc"/><circle cx="30" cy="30" r="4.5" fill="#c084fc"/><circle cx="44" cy="30" r="4.5" fill="#c084fc"/></svg></div>
            <div class="w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center"><svg viewBox="0 0 60 60" class="w-10 h-10"><circle cx="20" cy="20" r="4.5" fill="#c084fc"/><circle cx="40" cy="20" r="4.5" fill="#c084fc"/><circle cx="20" cy="40" r="4.5" fill="#c084fc"/><circle cx="40" cy="40" r="4.5" fill="#c084fc"/></svg></div>
            <div class="w-16 h-16 bg-teal-950/40 rounded-lg border border-dashed border-teal-400 flex items-center justify-center text-teal-300 font-bold text-xl">?</div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="20" cy="40" r="6" fill="#c084fc"/><circle cx="40" cy="40" r="6" fill="#c084fc"/><circle cx="60" cy="40" r="6" fill="#c084fc"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="25" cy="25" r="6" fill="#c084fc"/><circle cx="55" cy="25" r="6" fill="#c084fc"/><circle cx="25" cy="55" r="6" fill="#c084fc"/><circle cx="55" cy="55" r="6" fill="#c084fc"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="25" cy="20" r="5" fill="#c084fc"/><circle cx="55" cy="20" r="5" fill="#c084fc"/><circle cx="25" cy="40" r="5" fill="#c084fc"/><circle cx="55" cy="40" r="5" fill="#c084fc"/><circle cx="25" cy="60" r="5" fill="#c084fc"/><circle cx="55" cy="60" r="5" fill="#c084fc"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="22" cy="22" r="5.5" fill="#c084fc"/><circle cx="58" cy="22" r="5.5" fill="#c084fc"/><circle cx="40" cy="40" r="5.5" fill="#c084fc"/><circle cx="22" cy="58" r="5.5" fill="#c084fc"/><circle cx="58" cy="58" r="5.5" fill="#c084fc"/></svg>"""
        ],
        "correct": 4
    },
    {
        "id": 15,
        "category": "تقارن آینه‌ای و انعکاس مرکب فضایی (Raven APM)",
        "title": "کدام شکل، تصویر آینه‌ای و متقارن دقیق شکل مجهول را نشان می‌دهد؟",
        "graphic": """<div class="inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto">
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><path d="M25 20 L25 60 L60 60" fill="none" stroke="#38bdf8" stroke-width="5" stroke-linecap="round"/><circle cx="60" cy="60" r="8" fill="#f43f5e"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><path d="M55 20 L55 60 L20 60" fill="none" stroke="#38bdf8" stroke-width="5" stroke-linecap="round"/><circle cx="20" cy="60" r="8" fill="#f43f5e"/></svg>
            </div>
            <div class="w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center">
                <svg viewBox="0 0 80 80" class="w-16 h-16"><path d="M25 55 L25 25 L55 25" fill="none" stroke="#fbbf24" stroke-width="5" stroke-linecap="round"/><polygon points="55,18 62,25 55,32 48,25" fill="#34d399"/></svg>
            </div>
            <div class="w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black">
                ?
            </div>
        </div>""",
        "options": [
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><path d="M25 55 L25 25 L55 25" fill="none" stroke="#fbbf24" stroke-width="5" stroke-linecap="round"/><polygon points="55,18 62,25 55,32 48,25" fill="#34d399"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><path d="M55 55 L55 25 L25 25" fill="none" stroke="#fbbf24" stroke-width="5" stroke-linecap="round"/><polygon points="25,18 32,25 25,32 18,25" fill="#34d399"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><path d="M25 25 L25 55 L55 55" fill="none" stroke="#fbbf24" stroke-width="5" stroke-linecap="round"/><polygon points="55,48 62,55 55,62 48,55" fill="#34d399"/></svg>""",
            """<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><path d="M55 55 L55 25 L25 25" fill="none" stroke="#fbbf24" stroke-width="5" stroke-linecap="round"/></svg>"""
        ],
        "correct": 2
    }
]

js_code = "        const questionsData = " + json.dumps(questions, ensure_ascii=False, indent=12) + ";\n"

with open("almas.php", "r") as f:
    content = f.read()

start_mark = "        const questionsData = ["
end_mark = "        let currentQuestionIdx = 0;"

s_pos = content.find(start_mark)
e_pos = content.find(end_mark)

if s_pos != -1 and e_pos != -1:
    new_content = content[:s_pos] + js_code + "\n" + content[e_pos:]
    with open("almas.php", "w") as f:
        f.write(new_content)
    print("almas.php updated successfully with 15 100% visual questions!")
else:
    print("Could not find start or end mark!", s_pos, e_pos)
