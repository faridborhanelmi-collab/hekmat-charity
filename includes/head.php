<?php
// includes/head.php - Modular SEO Head Component for Hekmat Charity

if (!isset($page_title)) {
    $page_title = 'بنیاد نیکوکاری حکمت | خیریه و بورس تحصیلی دانش‌آموزان مستعد';
}
if (!isset($page_desc)) {
    $page_desc = 'بنیاد نیکوکاری حکمت (شماره ثبت ۷۷۰۷)، حامی و بورس‌دهنده دانش‌آموزان نخبه و کم‌برخوردار در سراسر کشور. با شفافیت ۱۰۰٪ مالی، حامی آینده نخبگان مستعد باشید.';
}

$current_protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$current_host = $_SERVER['HTTP_HOST'] ?? 'hekmatfoundation.org';
$current_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (!isset($canonical_url)) {
    $canonical_url = "https://hekmatfoundation.org" . $current_uri;
}

if (!isset($og_image)) {
    $og_image = "https://hekmatfoundation.org/logo.png";
}

if (!isset($is_private_page)) {
    $is_private_page = false;
}
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($page_title); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($page_desc); ?>">
<meta name="keywords" content="خیریه, خیریه حکمت, بنیاد نیکوکاری حکمت, بورس تحصیلی دانش آموزان نیازمند, کمک به دانش آموزان محروم, نذر آموزشی, حامی دانش آموز, ایتام و نیازمندان, خیریه مشهد">
<meta name="author" content="بنیاد نیکوکاری حکمت">
<meta name="google-site-verification" content="c1b0xwfwNt_EDNHf7oZzzcQzug0x3C1CiHR-KogkTQM" />

<!-- Robots & Canonical -->
<?php if ($is_private_page): ?>
<meta name="robots" content="noindex, nofollow">
<?php else: ?>
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<link rel="canonical" href="<?php echo htmlspecialchars($canonical_url); ?>">
<?php endif; ?>

<!-- Open Graph / Facebook / Telegram / LinkedIn / Eitaa -->
<meta property="og:locale" content="fa_IR">
<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($page_desc); ?>">
<meta property="og:url" content="<?php echo htmlspecialchars($canonical_url); ?>">
<meta property="og:site_name" content="بنیاد نیکوکاری حکمت">
<meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
<meta property="og:image:alt" content="لوگوی رسمی بنیاد نیکوکاری حکمت">

<!-- Twitter Cards -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($page_desc); ?>">
<meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">

<!-- Favicon & Mobile Setup -->
<meta name="theme-color" content="#115e59">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="بنیاد حکمت">
<link rel="icon" type="image/png" href="/logo.png">
<link rel="apple-touch-icon" href="/logo.png">
<link rel="manifest" href="/manifest.json">

<!-- Preload critical fonts for zero-CLS text rendering -->
<link rel="preload" href="/assets/fonts/Vazirmatn-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/Vazirmatn-700.woff2" as="font" type="font/woff2" crossorigin>

<!-- Self-hosted fonts -->
<style>
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:100;font-display:optional;src:url('/assets/fonts/Vazirmatn-100.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:300;font-display:optional;src:url('/assets/fonts/Vazirmatn-300.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:optional;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:optional;src:url('/assets/fonts/Vazirmatn-500.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:optional;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:optional;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
</style>

<!-- Synchronous Tailwind CSS for zero layout shift (19KB gzipped, cached 1yr) -->
<link rel="stylesheet" href="/assets/tailwind.min.css">

<!-- Structured Data: Organization & NGO Schema -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": ["NGO", "Organization"],
      "@id": "https://hekmatfoundation.org/#organization",
      "name": "بنیاد نیکوکاری حکمت",
      "alternateName": ["خیریه حکمت", "موسسه خیریه حکمت مشهد", "Hekmat Foundation", "Hekmat Charity Foundation"],
      "url": "https://hekmatfoundation.org/",
      "logo": {
        "@type": "ImageObject",
        "@id": "https://hekmatfoundation.org/#logo",
        "url": "https://hekmatfoundation.org/logo.png",
        "caption": "لوگو بنیاد نیکوکاری حکمت"
      },
      "image": "https://hekmatfoundation.org/logo.png",
      "description": "بنیاد نیکوکاری حکمت (شماره ثبت ۷۷۰۷)، حامی و بورس‌دهنده دانش‌آموزان نخبه و مستعد در سراسر کشور با شفافیت ۱۰۰٪ مالی.",
      "taxID": "7707",
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "مشهد",
        "addressRegion": "خراسان رضوی",
        "addressCountry": "IR"
      },
      "contactPoint": [
        {
          "@type": "ContactPoint",
          "telephone": "+98-9926724850",
          "contactType": "donor support",
          "availableLanguage": ["Persian"]
        }
      ],
      "sameAs": [
        "https://instagram.com/Hekmattoos_",
        "https://linkedin.com/company/hekmat-charity-foundation"
      ],
      "potentialAction": {
        "@type": "DonateAction",
        "name": "حمایت مالی از نخبگان مستعد",
        "recipient": {
          "@id": "https://hekmatfoundation.org/#organization"
        },
        "target": {
          "@type": "EntryPoint",
          "urlTemplate": "https://hekmatfoundation.org/campaign.php"
        }
      }
    },
    {
      "@type": "WebSite",
      "@id": "https://hekmatfoundation.org/#website",
      "url": "https://hekmatfoundation.org/",
      "name": "بنیاد نیکوکاری حکمت",
      "description": "سامانه رسمی حمایت و بورسیه دانش‌آموزان و نخبگان مستعد",
      "publisher": {
        "@id": "https://hekmatfoundation.org/#organization"
      },
      "inLanguage": "fa-IR"
    }
  ]
}
</script>
