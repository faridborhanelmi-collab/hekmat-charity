#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Hekmat Charity - Process New Accepted Students & Sync Donors Sponsorships
"""

import sqlite3
import json
import re

DB_PATH = 'hekmat.db'

def to_farsi_digits(val):
    if val is None:
        return ''
    mapping = {'0': '۰', '1': '۱', '2': '۲', '3': '۳', '4': '۴', '5': '۵', '6': '۶', '7': '۷', '8': '۸', '9': '۹'}
    res = ''
    for ch in str(val):
        res += mapping.get(ch, ch)
    return res

new_candidates_data = [
    {
        'test_id': 80,
        'name': 'مهتاب',
        'surname': 'سام زاده',
        'gender': 'دختر',
        'school': 'دبیرستان فرزانگان مشهد (تیزهوشان)',
        'grade': 'دهم',
        'field_of_study': 'علوم تجربی',
        'talents': 'استعداد درخشان در تحلیل مسائل زیست‌شناسی و ریاضی، قدرت برقراری ارتباط موثر و هوش اجتماعی ۹۸٪، توانمندی استثنایی در خودمدیریتی و مطالعه عمیق.',
        'dreams': 'قبولی در رشته پزشکی دانشگاه علوم پزشکی تهران، پژوهش و توسعه داروسازی نوین، بازگشت به عنوان پزشک خدمتگزار در مناطق کم‌برخوردار.',
        'explanations': 'تحلیل مصاحبه صوتی ۶.۷ دقیقه‌ای: داوطلب با آرامش، متانت و کلامی فاخر گفتگو کرد. تعهد به برنامه‌ریزی درسی و روحیه مثبت او چشمگیر است. تطبیق با روانسنجی: انگیزه پیشرفت ۱۰۷ از ۱۱۶ (از بالاترین رکوردهای بنیاد)، هوش منطقی و بین‌فردی بالای ۹۶٪ و آرامش روانی فوق‌العاده با تنش نزدیک به صفر کاملاً با شواهد مصاحبه همخوانی دارد. پذیرش قطعی در بورس نخبگان حکمت تایید شد.',
        'ai_analysis': 'ارزیابی جامع مصاحبه صوتی داوطلب مهتاب سام‌زاده:\n• ثبات خلقی، لحن محترمانه و خودباوری سالم.\n• انگیزه درونی قوی برای سرآمدی تحصیلی (۱۰۷ از ۱۱۶).\n• سلامت روانی پایدار و بدون اضطراب در محیط پرفشار سمپاد (GSI: 0.03).\n• شایستگی کامل برای بورس سطح A حکمت.'
    },
    {
        'test_id': 90,
        'name': 'صبا',
        'surname': 'کارگر مقدم',
        'gender': 'دختر',
        'school': 'دبیرستان فرزانگان مشهد (تیزهوشان)',
        'grade': 'دهم',
        'field_of_study': 'علوم تجربی',
        'talents': 'نبوغ چندبعدی شناختی و هماهنگی کم‌نظیر نیمکره‌های مغز، ادراک استثنایی در مفاهیم طبیعی و زیستی، ذوق هنری و درک ریتم کامل، فن بیان قوی و قلم شیوا.',
        'dreams': 'تحصیل در تراز اول علوم پزشکی و ژنتیک، نوآوری در درمان‌های زیست‌فناوری و تاسیس بنیاد خیریه پژوهشی برای درمان بیماری‌های صعب‌العلاج.',
        'explanations': 'تحلیل مصاحبه و بررسی کارنامه: ذهن فوق‌العاده پویا و خلاق با بالاترین نمره هوش چندگانه در کل بنیاد (۳۵۷ از ۴۰۰). تطبیق با روانسنجی: ترکیب هوش طبیعت‌گرا و کلامی با انگیزه پیشرفت ۸۴٪، او را به داوطلبی کم‌نظیر برای رتبه‌های برتر کشوری بدل کرده است. سلامت روانی بالا و تمرکز عمیق. پذیرش قطعی در بورس نخبگان حکمت با رتبه عالی A تصویب شد.',
        'ai_analysis': 'ارزیابی جلسه مصاحبه داوطلب صبا کارگر مقدم:\n• استعداد چندبعدی، تفکر شهودی و تحلیلی همزمان.\n• ظرفیت ذهنی بسیار بالا برای پژوهش‌های بین‌رشته‌ای.\n• رکورددار هوش چندگانه گاردنر (۳۵۷ از ۴۰۰).\n• تایید بدون قید و شرط بورس تحصیلی سطح A.'
    },
    {
        'test_id': 99,
        'name': 'حدیث',
        'surname': 'عسکری',
        'gender': 'دختر',
        'school': 'دبیرستان فرزانگان مشهد (تیزهوشان)',
        'grade': 'دهم',
        'field_of_study': 'علوم تجربی',
        'talents': 'تسلط بر روش‌های حل مسئله ریاضی و مباحث فیزیک، انضباط شخصی و اراده آهنین در مطالعه مستمر، تفکر ساختاریافته و دقت بالا در جزئیات.',
        'dreams': 'قبولی در رشته‌های برتر مهندسی/پزشکی دانشگاه‌های طراز اول کشور، هدایت پروژه‌های علمی پیشرفته و ارتقای رفاه خانواده و جامعه.',
        'explanations': 'تحلیل مصاحبه صوتی ۱۴ دقیقه‌ای: گفتگویی صریح، شفاف و باصلابت؛ صداقت در بیان چالش‌های درسی و برنامه‌ریزی برای غلبه بر آنها مشهود بود. تطبیق با روانسنجی: انگیزه پیشرفت ۱۰۷ از ۱۱۶ (تراز سرآمد)، هوش منطقی ۹۰٪ و تعادل هیجانی و روانی. با دریافت بورس و کتب تکمیلی جهش چشمگیری در امتحانات تستی خواهد داشت. پذیرش قطعی صادر شد.',
        'ai_analysis': 'ارزیابی مصاحبه صوتی داوطلب حدیث عسکری:\n• استقلال فکری، مدیریت زمان شخصی، پشتکار بسیار بالا در مواجهه با درس‌های سنگین سمپاد.\n• تطابق عالی روانسنجی با مصاحبه عینی.\n• انگیزه پیشرفت فوق‌العاده (۱۰۷ از ۱۱۶).\n• شایستگی کامل برای بورس حکمت.'
    },
    {
        'test_id': 79,
        'name': 'نازنین زهرا',
        'surname': 'نبی زاده',
        'gender': 'دختر',
        'school': 'دبیرستان فرزانگان مشهد (تیزهوشان)',
        'grade': 'دهم',
        'field_of_study': 'علوم تجربی',
        'talents': 'تمرکز و انضباط ذهنی بالا، درک هنری و تحلیلی همزمان، روحیه همدلانه و مشارکت در فعالیت‌های گروهی، توانمندی در مدیریت استرس امتحانات.',
        'dreams': 'قبولی در رشته داروسازی دانشگاه تهران، تحقیق و سنتز داروهای نوترکیب، کمک به بیماران خاص و حمایت از خانواده.',
        'explanations': 'تحلیل استعدادیابی و بررسی مصاحبه: داوطلب باانگیزه، مصمم و دارای چشم‌اندازی روشن برای موفقیت. تطبیق با روانسنجی: انگیزه پیشرفت ۱۰۴ از ۱۱۶، هوش درون‌فردی ۹۲٪ و سلامت روان پایدار. با همراهی منتور آموزشی برای ارتقای مهارتهای مدیریت هیجان، پتانسیل کسب رتبه‌های درخشان کنکور را دارد. پذیرش بورس تایید شد.',
        'ai_analysis': 'ارزیابی داوطلب نازنین‌زهرا نبی‌زاده:\n• خودآگاهی عمیق و هوش درون‌فردی بالا.\n• انگیزه تحصیلی درخشان (۱۰۴ از ۱۱۶).\n• سلامت روان نرمال و آرامش‌بخش.\n• نیازمند کتب آموزشی تخصصی و همراهی تحصیلی بنیاد.'
    },
    {
        'test_id': 78,
        'name': 'آیدا',
        'surname': 'یگانه',
        'gender': 'دختر',
        'school': 'دبیرستان فرزانگان مشهد (تیزهوشان)',
        'grade': 'دهم',
        'field_of_study': 'علوم تجربی',
        'talents': 'خودآگاهی عمیق و توانایی بالا در هدایت شخصی، قدرت تحلیل منطقی و تجسم فضایی، تسلط بر مفاهیم علوم پایه و تمرکز طولانی بر مسائل پیچیده.',
        'dreams': 'ورود به رشته دندانپزشکی یا پزشکی، راه‌اندازی کلینیک تخصصی و ارائه خدمات درمانی به اقشار محروم جامعه.',
        'explanations': 'تحلیل مصاحبه صوتی ۱۰ دقیقه‌ای: بیانی سنجیده، پرمحتوا و سرشار از خودباوری واقع‌بینانه. تطبیق با روانسنجی: انگیزه پیشرفت ۱۰۲ از ۱۱۶، هوش هیجانی ۶۲٪ (B+)، هوش منطقی و فضایی ۸۶٪. تسلط کامل بر نقاط قوت تحصیلی خود دارد و مشمول بورس کامل نخبگان حکمت ارزیابی شد.',
        'ai_analysis': 'ارزیابی جامع مصاحبه صوتی داوطلب آیدا یگانه:\n• بلوغ فکری، متانت در گفتگو، برنامه مطالعاتی منظم.\n• همخوانی صددرصدی کارنامه کتبی با مصاحبه عینی.\n• تایید اعطای بورس و همراهی آموزشی بنیاد.'
    },
    {
        'test_id': 105,
        'name': 'سها',
        'surname': 'نادری',
        'gender': 'دختر',
        'school': 'دبیرستان فرزانگان مشهد (تیزهوشان)',
        'grade': 'دهم',
        'field_of_study': 'ریاضی فیزیک',
        'talents': 'خلاقیت بصری و تجسم سه‌بعدی فوق‌العاده، درک هارمونی و الگوهای پیچیده، هوش هیجانی و روابط اجتماعی گرم و سازنده، توانایی عملی در پروژه‌های کاربردی.',
        'dreams': 'ورود به رشته مهندسی معماری یا مهندسی پزشکی در برترین دانشگاه‌ها، طراحی سازه‌های پایدار و عام‌المنفعه، ایجاد فرصت‌های شغلی برای جوانان.',
        'explanations': 'تحلیل استعدادیابی و ارزیابی مصاحبه: ترکیب بی‌نظیر هوش فضایی ۹۶٪ با هوش بدنی و هیجانی، او را به داوطلبی با تفکر چندبعدی تبدیل کرده است. تطبیق با روانسنجی: انگیزه پیشرفت ۹۴ از ۱۱۶ و ثبات روانی مطلوب. نیازمند ابزارهای تخصصی تحصیلی جهت شکوفایی حداکثری. بورس نخبگان حکمت تصویب گردید.',
        'ai_analysis': 'ارزیابی جامع داوطلب سها نادری:\n• توانایی خارق‌العاده در پردازش دیداری و فضایی (۹۶٪).\n• روحیه پویا و باانگیزه.\n• شایستگی کامل برای پوشش بورس تحصیلی بنیاد.'
    },
    {
        'test_id': 98,
        'name': 'سارینا',
        'surname': 'نبی',
        'gender': 'دختر',
        'school': 'دبیرستان فرزانگان مشهد (تیزهوشان)',
        'grade': 'دهم',
        'field_of_study': 'ریاضی فیزیک',
        'talents': 'استدلال منطقی قوی، سرعت عمل در حل مسائل محاسباتی، خوداتکایی تحصیلی و انگیزه پیشرفت بی‌نظیر (نمره ۱۰۶).',
        'dreams': 'قبولی در رشته مهندسی کامپیوتر یا هوش مصنوعی در دانشگاه صنعتی شریف، توسعه سیستم‌های نرم‌افزاری ملی و کارآفرینی در صنعت فناوری.',
        'explanations': 'تحلیل مصاحبه و کارنامه: تفکر نقادانه و محاسباتی دقیق؛ انگیزه پیشرفت ۱۰۶ از ۱۱۶ نمایانگر پشتکار استثنایی و خستگی‌ناپذیری است. تطبیق با روانسنجی: هوش منطقی ۸۸٪ و سلامت روان پایدار. با تقویت منابع آموزشی سمپاد، آینده‌ای بسیار درخشان در انتظار اوست. پذیرش بورس تایید شد.',
        'ai_analysis': 'ارزیابی داوطلب سارینا نبی:\n• منطق ریاضی برجسته و تسلط بر محاسبات.\n• انگیزه سرآمد (۱۰۶ از ۱۱۶) و ثبات فردی بالا.\n• تایید بورس ماهانه و بسته آموزشی.'
    },
    {
        'test_id': 93,
        'name': 'تانیا',
        'surname': 'محتمشیان',
        'gender': 'دختر',
        'school': 'دبیرستان فرزانگان مشهد (تیزهوشان)',
        'grade': 'دهم',
        'field_of_study': 'علوم تجربی',
        'talents': 'اشتیاق سوزان به علوم زیستی و محیط زیست، درک عمیق چرخه‌های طبیعی، احساس مسئولیت اجتماعی و اخلاق‌مداری، پشتکار بالا در دروس مفهومی.',
        'dreams': 'قبولی در رشته پزشکی یا دندانپزشکی، فعالیت در تحقیقات دارویی گیاهی و زیستی، ارائه خدمات پزشکی رایگان در مناطق محروم.',
        'explanations': 'تحلیل مصاحبه و روانسنجی: انگیزه پیشرفت عالی (۱۰۴ از ۱۱۶)، هوش طبیعت‌گرا و درون‌فردی بالای ۸۰٪ و سلامت روانی متعادل. داوطلب با مناعت طبع و جدیت تحصیلی پیگیر اهداف خود است. شایستگی دریافت بورس نخبگان حکمت تصویب گردید.',
        'ai_analysis': 'ارزیابی داوطلب تانیا محتمشیان:\n• نگاه علمی به پدیده‌های طبیعی و زیستی.\n• انگیزه تحصیلی ممتاز و روحیه اخلاقی.\n• نیازمند حمایت در تامین کتب کمک‌آموزشی. تایید قطعی بورس.'
    },
    {
        'test_id': 97,
        'name': 'آیدا',
        'surname': 'زنگویی',
        'gender': 'دختر',
        'school': 'دبیرستان فرزانگان مشهد (تیزهوشان)',
        'grade': 'دهم',
        'field_of_study': 'علوم تجربی',
        'talents': 'سرعت پردازش ذهنی بسیار بالا، نبوغ محاسباتی در حل تست‌های تحلیلی، هوش منطقی ۹۴٪، خودشناسی و برنامه‌ریزی هدفمند.',
        'dreams': 'قبولی در رشته‌های ممتاز علوم پزشکی یا مهندسی نوین، نقش‌آفرینی در تولید علم و خودکفایی خانواده.',
        'explanations': 'تحلیل مصاحبه و بررسی زمان پاسخ‌دهی: سرعت فوق‌العاده در پردازش سوالات تستی همراه با نمرات عالی در کارنامه؛ هوش منطقی ۹۴٪ و انگیزه پیشرفت ۱۰۳ از ۱۱۶. سلامت روان پایدار. واجد شایستگی کامل برای اعطای بورس بنیاد حکمت.',
        'ai_analysis': 'ارزیابی داوطلب آیدا زنگویی:\n• سرعت بالای یادگیری و پردازش منطقی.\n• درک فرمول‌ها و روابط ریاضی، اعتماد‌به‌نفس شایسته.\n• تایید بورس نخبگان حکمت.'
    },
    {
        'test_id': 84,
        'name': 'مهدیه',
        'surname': 'محمدنیا',
        'gender': 'دختر',
        'school': 'دبیرستان فرزانگان مشهد (تیزهوشان)',
        'grade': 'دهم',
        'field_of_study': 'علوم تجربی',
        'talents': 'انضباط ذهنی، توانمندی استدلالی، هوش هیجانی ۶۲٪، دقت بالا در اجرای محاسبات عددی، تعادل روانی و روابط اجتماعی صمیمی.',
        'dreams': 'قبولی در رشته پزشکی دانشگاه فردوسی مشهد، تخصص در جراحی و راه‌اندازی برنامه‌های غربالگری سلامت برای دانش‌آموزان نیازمند.',
        'explanations': 'تحلیل مصاحبه و نتایج روانسنجی: داوطلب بسیار منظم، متعهد و واقع‌نگر است. هوش هیجانی خوب (B+) و انگیزه پیشرفت ۹۵ از ۱۱۶ نشانگر پشتکار پایدار وی در مواجهه با چالش‌های کنکور است. سلامت روان مطلوب. پذیرش در بورس حکمت تایید شد.',
        'ai_analysis': 'ارزیابی داوطلب مهدیه محمدنیا:\n• ثبات عاطفی و تفکر منطقی منسجم.\n• انگیزه تحصیلی قوی و پایداری در مطالعه.\n• تایید همراهی آموزشی و مالی بنیاد.'
    },
    {
        'test_id': 94,
        'name': 'مطهره',
        'surname': 'احسان فر',
        'gender': 'دختر',
        'school': 'دبیرستان فرزانگان مشهد (تیزهوشان)',
        'grade': 'دهم',
        'field_of_study': 'علوم تجربی',
        'talents': 'هوش هیجانی برجسته (۶۸٪)، قدرت همدلی و روابط انسانی، هوش فضایی و طبیعت‌گرا، خلاقیت در یادگیری شهودی و کارهای گروهی.',
        'dreams': 'قبولی در رشته پزشکی یا پیراپزشکی، ارائه مراقبت‌های بالینی با شفقت و مهربانی به بیماران نیازمند، الگوی موفقیت برای خانواده.',
        'explanations': 'تحلیل مصاحبه و آزمون: هوش هیجانی سرآمد (۶۸٪) همراه با هوش فضایی ۸۲٪ و طبیعت‌گرا ۸۸٪؛ شخصیتی عاطفی، سخت‌کوش و متعهد به رشد فردی. انگیزه پیشرفت مطلوب. پذیرش در بورس تحصیلی حکمت تصویب شد.',
        'ai_analysis': 'ارزیابی داوطلب مطهره احسان‌فر:\n• ظرفیت هیجانی بالا و سازگاری اجتماعی کم‌نظیر.\n• انگیزه تحصیلی خوب و روحیه فداکارانه.\n• تایید بورس و همراهی منتورینگ.'
    },
    {
        'test_id': 95,
        'name': 'امیرحسین',
        'surname': 'مختاری',
        'gender': 'پسر',
        'school': 'دبیرستان شهید هاشمی‌نژاد مشهد (تیزهوشان)',
        'grade': 'دهم',
        'field_of_study': 'ریاضی فیزیک',
        'talents': 'قبولی در آزمون ورودی بسیار رقابتی سمپاد هاشمی‌نژاد، تعادل میان فعالیت‌های ورزشی-بدنی و محاسباتی، هوش اجتماعی و سازگاری بالا، احساس مسئولیت نسبت به خانواده.',
        'dreams': 'قبولی در رشته‌های مهندسی مکانیک یا عمران دانشگاه‌های ممتاز کشور، کارآفرینی در پروژه‌های عمرانی ملی و ارتقای رفاه اقتصادی خانواده.',
        'explanations': 'تحلیل مصاحبه و روانسنجی: دانش‌آموزی بااخلاق، مسئولیت‌پذیر و دارای اراده استوار. هوش هیجانی ۶۴٪ و انگیزه پیشرفت ۸۹ همراه با سلامت روانی پایدار نشان می‌دهد با دریافت بورس و کتب تخصصی، توان درخشش در المپیادها و کنکور را دارد. پذیرش قطعی شد.',
        'ai_analysis': 'ارزیابی داوطلب امیرحسین مختاری:\n• شایستگی اثبات‌شده با قبولی سمپاد پسرانه هاشمی‌نژاد.\n• پایداری شخصیتی و انگیزه پیشرفت بالا.\n• تایید بورس ماهانه حکمت.'
    },
    {
        'test_id': 81,
        'name': 'سارا',
        'surname': 'شیری',
        'gender': 'دختر',
        'school': 'دبیرستان نمونه دولتی / سمپاد',
        'grade': 'دهم',
        'field_of_study': 'علوم تجربی',
        'talents': 'هوش منطقی و ریاضی ۸۰٪، دقت در حل تمرین‌ها، روحیه خوداتکا و صبور در مواجهه با کاستی‌های مالی، اشتیاق شدید به یادگیری عمیق.',
        'dreams': 'قبولی در دانشگاه دولتی در رشته‌های برتر مهندسی/علوم پایه، ایجاد استقلال مالی و یاری‌رسانی به دانش‌آموزان مستعد همشهری.',
        'explanations': 'تحلیل مصاحبه حضوری با مدیرعامل بنیاد: داوطلب بسیار نجیب، قانع و دارای مناعت طبع است. علیرغم کمبود شدید منابع کمک‌آموزشی، با انگیزه ۸۹ از ۱۱۶ و سلامت روان آرام درس می‌خواند. بورس تحصیلی و بسته کتب درسی به ایشان اختصاص یافت. پذیرش قطعی صادر شد.',
        'ai_analysis': 'ارزیابی مصاحبه داوطلب سارا شیری:\n• نجابت، پشتکار، عدم توقع و انگیزه درونی برای خودساختگی.\n• سلامت روان پایدار و نیازمند کتب آموزشی.\n• تایید بورس نخبگان حکمت.'
    }
]

def run():
    conn = sqlite3.connect(DB_PATH)
    c = conn.cursor()

    # Determine next code & alias
    c.execute('SELECT MAX(CAST(code AS INTEGER)) FROM students WHERE code GLOB "[0-9]*"')
    max_code = c.fetchone()[0] or 362

    c.execute('SELECT alias_name FROM students WHERE alias_name LIKE "حکمت‌جو %"')
    max_alias = 171
    for row in c.fetchall():
        m = re.search(r'(\d+)', row[0] or '')
        if m:
            num = int(m.group(1))
            if num > max_alias:
                max_alias = num

    print(f"Starting next code from: {max_code + 1}")
    print(f"Starting next alias from: حکمت‌جو {max_alias + 1}")

    curr_code = max_code + 1
    curr_alias_num = max_alias + 1

    # =========================================================================
    # PART 1: Process and Add 13 Accepted Candidates
    # =========================================================================
    print("\n--- 1. Processing 13 Accepted Interview Candidates ---")
    for cand in new_candidates_data:
        tid = cand['test_id']
        c.execute("SELECT scores_json, answers_json, mobile FROM interview_tests WHERE id = ?", (tid,))
        t_row = c.fetchone()
        if not t_row:
            print(f"⚠️ Test ID {tid} not found!")
            continue

        scores = json.loads(t_row[0] or '{}')
        hti = scores.get('hti', scores.get('total_score', 0))
        final_grade = scores.get('final_grade', scores.get('grade', 'B+'))

        gardner = scores.get('gardner', {})
        mi_total = gardner.get('total_score', 0)
        mi_grade = gardner.get('grade', 'B+')

        eq = scores.get('seiq', {})
        eq_total = eq.get('total_score', 0)
        eq_grade = eq.get('grade', 'B+')

        hermans = scores.get('amq', {})
        hermans_score = hermans.get('total_score', 0)
        hermans_grade = hermans.get('level', 'بالا')

        scl = scores.get('scl90', {})
        scl_gsi = scl.get('gsi', 0.0)
        scl_risk = scl.get('health_status', 'نرمال')

        # Check existing student
        c.execute("SELECT id, code, alias_name FROM students WHERE name = ? AND surname = ?", (cand['name'], cand['surname']))
        st_row = c.fetchone()

        if st_row:
            sid = st_row[0]
            alias_name = st_row[2] or f"حکمت‌جو {to_farsi_digits(curr_alias_num)}"
            c.execute("""
                UPDATE students SET
                    school = ?, grade = ?, field_of_study = ?, gender = ?,
                    talents = ?, dreams = ?, explanations = ?,
                    alias_name = ?, bursary_eligible = 1, status = 'active'
                WHERE id = ?
            """, (cand['school'], cand['grade'], cand['field_of_study'], cand['gender'],
                  cand['talents'], cand['dreams'], cand['explanations'], alias_name, sid))
            print(f"  🔄 Updated existing student: {cand['name']} {cand['surname']} (ID: {sid}, {alias_name})")
        else:
            code_str = str(curr_code)
            curr_code += 1
            alias_name = f"حکمت‌جو {to_farsi_digits(curr_alias_num)}"
            curr_alias_num += 1

            c.execute("""
                INSERT INTO students (
                    code, name, surname, gender, school, grade, field_of_study,
                    talents, dreams, explanations, alias_name,
                    bursary_eligible, status
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    1, 'active'
                )
            """, (code_str, cand['name'], cand['surname'], cand['gender'],
                  cand['school'], cand['grade'], cand['field_of_study'],
                  cand['talents'], cand['dreams'], cand['explanations'], alias_name))
            sid = c.lastrowid
            print(f"  ✨ Added new student: {cand['name']} {cand['surname']} (ID: {sid}, Code: {code_str}, {alias_name})")

        # Link interview_tests.student_id
        c.execute("UPDATE interview_tests SET student_id = ? WHERE id = ?", (sid, tid))

        # Insert / Update student_psychology
        c.execute("""
            INSERT OR REPLACE INTO student_psychology (
                student_id, hermans_score, hermans_grade,
                mi_total, mi_grade, eq_total, eq_grade,
                scl90_gsi, scl90_risk,
                final_grade, recommendation, hti_score
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?
            )
        """, (sid, hermans_score, hermans_grade,
              mi_total, mi_grade, eq_total, eq_grade,
              scl_gsi, scl_risk,
              final_grade, scores.get('recommendation', cand['explanations']), hti))

        # Update interview_voice_notes if exists
        c.execute("SELECT id FROM interview_voice_notes WHERE test_id = ?", (tid,))
        v_rows = c.fetchall()
        if v_rows:
            for vr in v_rows:
                c.execute("""
                    UPDATE interview_voice_notes SET
                        notes = 'تحلیل مصاحبه صوتی و تطبیق استعدادیابی نخبگان حکمت',
                        ai_analysis = ?
                    WHERE id = ?
                """, (cand['ai_analysis'], vr[0]))
            print(f"    🎙️ Updated {len(v_rows)} voice note(s) for {cand['name']} {cand['surname']}")
        else:
            # If no voice note row exists, insert an interview assessment note
            c.execute("""
                INSERT INTO interview_voice_notes (
                    test_id, candidate_name, notes, ai_analysis
                ) VALUES (?, ?, ?, ?)
            """, (tid, f"{cand['name']} {cand['surname']}",
                  'جلسه مصاحبه تخصصی و تطبیق استعدادیابی نخبگان حکمت', cand['ai_analysis']))
            print(f"    📝 Registered interview assessment note for {cand['name']} {cand['surname']}")

    # =========================================================================
    # PART 2: Re-instate Genuine Sponsorships for Donors
    # =========================================================================
    print("\n--- 2. Registering Genuine Sponsorships for Donors ---")

    # 1. دکتر محمد ثقه‌الاسلامی (ID: 1891) -> 10 students
    c.execute("""
        UPDATE donors SET
            name = 'دکتر محمد',
            surname = 'ثقه‌الاسلامی',
            phone = '09129999999',
            description = 'پزشک متخصص و حامی ۱۰ دانش‌آموز نخبه بنیاد حکمت (تعهد ماهانه ۳۰ میلیون تومان)',
            reminder_active = 1,
            reminder_shares = 10,
            reminder_day = 5,
            reminder_interval_months = 1,
            reminder_channel = 'sms',
            next_reminder_date = '1405/08/05'
        WHERE id = 1891
    """)
    donor_saad_id = 1891

    # 2. مجتبی موسسی (ID: 1890) -> 10 students
    c.execute("""
        UPDATE donors SET
            name = 'مجتبی',
            surname = 'موسسی',
            phone = '09128888888',
            description = 'فعال اقتصادی و حامی ۱۰ دانش‌آموز نخبه بنیاد حکمت (تعهد ماهانه ۳۰ میلیون تومان)',
            reminder_active = 1,
            reminder_shares = 10,
            reminder_day = 21,
            reminder_interval_months = 1,
            reminder_channel = 'whatsapp',
            next_reminder_date = '1405/07/21'
        WHERE id = 1890
    """)
    donor_moasesi_id = 1890

    # 3. احمدرضا بختیاری (ID: 1428) -> 4 students
    c.execute("""
        UPDATE donors SET
            name = 'احمدرضا',
            surname = 'بختیاری',
            description = 'خیّر بزرگوار و حامی ۴ دانش‌آموز بنیاد حکمت (تعهد ماهانه ۱۲ میلیون تومان)',
            reminder_active = 1,
            reminder_shares = 4,
            reminder_day = 1,
            reminder_interval_months = 1,
            reminder_channel = 'sms',
            next_reminder_date = '1405/08/01'
        WHERE id = 1428
    """)
    donor_bakhtiari_id = 1428

    # 4. شاهین رضایی -> 5 students
    c.execute("SELECT id FROM donors WHERE name = 'شاهین' AND surname = 'رضایی'")
    sh_row = c.fetchone()
    if sh_row:
        donor_shahin_id = sh_row[0]
        c.execute("""
            UPDATE donors SET
                reminder_active = 1,
                reminder_shares = 5,
                reminder_day = 1,
                reminder_interval_months = 1,
                reminder_channel = 'sms',
                next_reminder_date = '1405/08/01',
                description = 'خیّر گرامی و حامی ۵ دانش‌آموز بنیاد حکمت (تعهد ماهانه ۱۵ میلیون تومان)'
            WHERE id = ?
        """, (donor_shahin_id,))
    else:
        c.execute("""
            INSERT INTO donors (
                name, surname, join_date, reminder_active, reminder_shares,
                reminder_day, reminder_interval_months, reminder_channel,
                next_reminder_date, description, total_donated
            ) VALUES (
                'شاهین', 'رضایی', '۱۴۰۵/۰۷/۰۱', 1, 5,
                1, 1, 'sms',
                '1405/08/01', 'خیّر گرامی و حامی ۵ دانش‌آموز بنیاد حکمت (تعهد ماهانه ۱۵ میلیون تومان)', 0
            )
        """)
        donor_shahin_id = c.lastrowid

    # 5. مهناز روحبخش (ID: 1869) -> 1 student (Diba Peyvandi)
    c.execute("""
        UPDATE donors SET
            name = 'مهناز',
            surname = 'روحبخش',
            description = 'همکار ارجمند و حامی آموزشی دیبا پیوندی (تعهد ماهانه ۳ میلیون تومان)',
            reminder_active = 1,
            reminder_shares = 1,
            reminder_day = 1,
            reminder_interval_months = 1,
            reminder_channel = 'sms',
            next_reminder_date = '1405/08/01'
        WHERE id = 1869
    """)
    donor_roohbakhsh_id = 1869

    # Update Diba Peyvandi alias
    c.execute("UPDATE students SET alias_name = 'حکمت‌جو ۱۶۳-ب' WHERE id = 1007")

    sponsorship_assignments = [
        # دکتر محمد ثقه‌الاسلامی (10)
        (donor_saad_id, 724, 1, '۱۴۰۵/۰۷/۰۱'), # فاطمه سادات جوادی
        (donor_saad_id, 761, 1, '۱۴۰۵/۰۷/۰۱'), # هستی اسکناطی
        (donor_saad_id, 764, 1, '۱۴۰۵/۰۷/۰۱'), # فاطمه محمودی گاش
        (donor_saad_id, 716, 1, '۱۴۰۵/۰۷/۰۱'), # آیدا سلیمانی
        (donor_saad_id, 750, 1, '۱۴۰۵/۰۷/۰۱'), # محمدحسین سرشاد
        (donor_saad_id, 763, 1, '۱۴۰۵/۰۷/۰۱'), # نرگس حسنی
        (donor_saad_id, 717, 1, '۱۴۰۵/۰۷/۰۱'), # پارسا پرهیزکار
        (donor_saad_id, 760, 1, '۱۴۰۵/۰۷/۰۱'), # الیاس فنایی
        (donor_saad_id, 688, 1, '۱۴۰۵/۰۷/۰۱'), # حانیه دشتبانی
        (donor_saad_id, 685, 1, '۱۴۰۵/۰۷/۰۱'), # ثنا پور دوران

        # مجتبی موسسی (10)
        (donor_moasesi_id, 725, 1, '۱۴۰۵/۰۷/۰۱'), # فاطمه قاسم‌زاده
        (donor_moasesi_id, 728, 1, '۱۴۰۵/۰۷/۰۱'), # کوثر آدینی
        (donor_moasesi_id, 735, 1, '۱۴۰۵/۰۷/۰۱'), # مهسا ارژنگی
        (donor_moasesi_id, 730, 1, '۱۴۰۵/۰۷/۰۱'), # محمد حسام کاظمی
        (donor_moasesi_id, 751, 1, '۱۴۰۵/۰۷/۰۱'), # سید محمد مهدی پسته شکر
        (donor_moasesi_id, 721, 1, '۱۴۰۵/۰۷/۰۱'), # زهرا رضائی
        (donor_moasesi_id, 748, 1, '۱۴۰۵/۰۷/۰۱'), # نازنین زینب اصلاح
        (donor_moasesi_id, 715, 1, '۱۴۰۵/۰۷/۰۱'), # ایمان رسولی
        (donor_moasesi_id, 985, 1, '۱۴۰۵/۰۷/۰۱'), # فرزانه عبدی
        (donor_moasesi_id, 745, 1, '۱۴۰۵/۰۷/۰۱'), # ویانا وحیدی

        # احمدرضا بختیاری (4)
        (donor_bakhtiari_id, 1003, 1, '۱۴۰۵/۰۷/۰۱'), # امیرابوالفضل نوذری
        (donor_bakhtiari_id, 772, 1, '۱۴۰۵/۰۷/۰۱'),  # سیده ستایش محمودی‌زاده
        (donor_bakhtiari_id, 1005, 1, '۱۴۰۵/۰۷/۰۱'), # مهدیه بهاری
        (donor_bakhtiari_id, 720, 1, '۱۴۰۵/۰۷/۰۱'),  # ریحانه حقانی

        # شاهین رضایی (5)
        (donor_shahin_id, 690, 1, '۱۴۰۵/۰۷/۰۱'),  # سیده شقایق محمودی زاده
        (donor_shahin_id, 705, 1, '۱۴۰۵/۰۷/۰۱'),  # سحر خادم حقیقی
        (donor_shahin_id, 762, 1, '۱۴۰۵/۰۷/۰۱'),  # باران تشکری
        (donor_shahin_id, 1000, 1, '۱۴۰۵/۰۷/۰۱'), # مهدیه سالاری
        (donor_shahin_id, 997, 1, '۱۴۰۵/۰۷/۰۱'),  # پریناز نوذری

        # مهناز روحبخش (1)
        (donor_roohbakhsh_id, 1007, 1, '۱۴۰۵/۰۷/۰۱'), # دیبا پیوندی

        # بهنام بهرمن (2 سهم)
        (1306, 767, 2, '۱۴۰۳/۰۷/۰۱'), # کامیار سالاری‌نیا

        # فرید برهان علمی (1 سهم)
        (1305, 1006, 1, '۱۴۰۳/۰۷/۰۱') # زهرا شیردل
    ]

    for did, sid, shares, sdate in sponsorship_assignments:
        c.execute("SELECT id FROM sponsorships WHERE student_id = ? AND status = 'active'", (sid,))
        existing_sp = c.fetchone()
        if existing_sp:
            c.execute("""
                UPDATE sponsorships SET
                    donor_id = ?, shares_count = ?, start_date = ?, status = 'active'
                WHERE id = ?
            """, (did, shares, sdate, existing_sp[0]))
        else:
            c.execute("""
                INSERT INTO sponsorships (donor_id, student_id, shares_count, start_date, status)
                VALUES (?, ?, ?, ?, 'active')
            """, (did, sid, shares, sdate))

    conn.commit()

    # Verify counts
    c.execute("SELECT COUNT(*) FROM sponsorships WHERE status = 'active'")
    active_spons = c.fetchone()[0]
    print(f"\n✅ Total active sponsorships in DB: {active_spons}")

    c.execute("""
        SELECT d.name, d.surname, count(s.id) as cnt
        FROM sponsorships s
        JOIN donors d ON s.donor_id = d.id
        WHERE s.status = 'active'
        GROUP BY s.donor_id
    """)
    for row in c.fetchall():
        print(f"  🤝 {row[0]} {row[1]}: {row[2]} دانش‌آموز تحت پوشش")

    conn.close()

if __name__ == '__main__':
    run()
