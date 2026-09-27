<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Access Control - Financial Only
require_financial_access();

// ----------------------------------------------------
// TIME HORIZON SELECTION
// ----------------------------------------------------
$period = isset($_GET['period']) ? $_GET['period'] : '2years';

$start_date = '1403/06/01';
$period_label = '۲ سال اخیر (شهریور ۱۴۰۳ تا شهریور ۱۴۰۵)';

switch ($period) {
    case '6months':
        $start_date = '1404/12/01';
        $period_label = '۶ ماه اخیر (اسفند ۱۴۰۴ تا شهریور ۱۴۰۵)';
        break;
    case '1year':
        $start_date = '1404/06/01';
        $period_label = '۱ سال اخیر (شهریور ۱۴۰۴ تا شهریور ۱۴۰۵)';
        break;
    case '1405':
        $start_date = '1405/01/01';
        $period_label = 'سال جاری (۱۴۰۵)';
        break;
    case '1404':
        $start_date = '1404/01/01';
        $period_label = 'از ابتدای سال ۱۴۰۴ تا کنون';
        break;
    case 'all':
        $start_date = '1390/01/01';
        $period_label = 'کل سوابق از ابتدای فعالیت';
        break;
    case '2years':
    default:
        $start_date = '1403/06/01';
        $period_label = '۲ سال اخیر (شهریور ۱۴۰۳ تا شهریور ۱۴۰۵)';
        $period = '2years';
        break;
}

// ----------------------------------------------------
// EXPORT TO EXCEL / CSV
// ----------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Hekmat_Donor_Behavior_Report_' . date('Y-m-d') . '.csv');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ردیف', 'نام و نام خانوادگی', 'تلفن', 'شماره کارت', 'وضعیت و رفتار مالی', 'تعداد واریزی', 'مجموع واریزی در بازه (ریال)', 'میانگین هر تراکنش', 'اولین واریز', 'آخرین واریز', 'واریزی ۶ ماه اخیر', 'واریزی ۶ ماه قبل‌تر']);
    
    $query = "
        SELECT d.id, d.name, d.surname, d.phone, d.card_number,
               COUNT(dn.id) as donation_count,
               SUM(dn.amount) as period_total,
               MIN(dn.date) as first_donation,
               MAX(dn.date) as last_donation,
               SUM(CASE WHEN dn.date >= '1404/12/01' THEN dn.amount ELSE 0 END) as recent_6m_total,
               SUM(CASE WHEN dn.date >= '1404/06/01' AND dn.date < '1404/12/01' THEN dn.amount ELSE 0 END) as prev_6m_total
        FROM donors d
        JOIN donations dn ON d.id = dn.donor_id
        WHERE dn.date >= ?
        GROUP BY d.id
        ORDER BY period_total DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$start_date]);
    $rows = $stmt->fetchAll();
    
    foreach ($rows as $idx => $r) {
        $last_d = $r['last_donation'] ?? '';
        $first_d = $r['first_donation'] ?? '';
        $r_tot = (float)$r['recent_6m_total'];
        $p_tot = (float)$r['prev_6m_total'];
        $cnt = (int)$r['donation_count'];
        
        $status_label = 'فعال عادی';
        if ($last_d >= '1405/03/01') {
            if ($first_d >= '1405/01/01') {
                $status_label = 'نیکوکار جدید';
            } elseif ($p_tot > 0 && $r_tot >= $p_tot * 1.2) {
                $status_label = 'روند افزایشی (رشد کمک)';
            } elseif ($p_tot > 0 && $r_tot <= $p_tot * 0.75) {
                $status_label = 'روند کاهشی (کاهش کمک)';
            } elseif ($cnt >= 6) {
                $status_label = 'منظم و پایدار';
            } else {
                $status_label = 'فعال';
            }
        } else {
            if ($cnt == 1) {
                $status_label = 'تک‌پرداخت / موردی';
            } else {
                $status_label = 'راکد / نیازمند پیگیری';
            }
        }
        
        $avg_tx = $cnt > 0 ? round($r['period_total'] / $cnt) : 0;
        
        fputcsv($output, [
            $idx + 1,
            $r['name'] . ' ' . $r['surname'],
            $r['phone'] ?: '---',
            $r['card_number'] ?: '---',
            $status_label,
            $cnt,
            $r['period_total'],
            $avg_tx,
            $first_d,
            $last_d,
            $r_tot,
            $p_tot
        ]);
    }
    fclose($output);
    exit();
}

// ----------------------------------------------------
// FETCH DONOR BEHAVIOR DATA
// ----------------------------------------------------
$query = "
    SELECT d.id, d.name, d.surname, d.phone, d.card_number, d.photo_path, d.total_donated as lifetime_total,
           COUNT(dn.id) as donation_count,
           SUM(dn.amount) as period_total,
           MIN(dn.date) as first_donation,
           MAX(dn.date) as last_donation,
           (
               SELECT amount 
               FROM donations dn2 
               WHERE dn2.donor_id = d.id 
               ORDER BY dn2.date DESC, dn2.id DESC 
               LIMIT 1
           ) as last_donation_amount,
           SUM(CASE WHEN dn.date >= '1404/12/01' THEN dn.amount ELSE 0 END) as recent_6m_total,
           SUM(CASE WHEN dn.date >= '1404/06/01' AND dn.date < '1404/12/01' THEN dn.amount ELSE 0 END) as prev_6m_total,
           COUNT(CASE WHEN dn.date >= '1404/12/01' THEN dn.id ELSE NULL END) as recent_6m_count,
           COUNT(CASE WHEN dn.date >= '1404/06/01' AND dn.date < '1404/12/01' THEN dn.id ELSE NULL END) as prev_6m_count
    FROM donors d
    JOIN donations dn ON d.id = dn.donor_id
    WHERE dn.date >= ?
    GROUP BY d.id
    ORDER BY period_total DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute([$start_date]);
$raw_donors = $stmt->fetchAll();

// Aggregation & Behavioral Classification
$donors_data = [];
$total_collected = 0;
$total_tx_count = 0;

$count_increased = 0;
$count_decreased = 0;
$count_steady = 0;
$count_lapsed = 0;
$count_new = 0;
$count_active = 0;
$count_onetime = 0;

foreach ($raw_donors as $r) {
    $last_d = $r['last_donation'] ?? '';
    $first_d = $r['first_donation'] ?? '';
    $r_tot = (float)$r['recent_6m_total'];
    $p_tot = (float)$r['prev_6m_total'];
    $r_cnt = (int)($r['recent_6m_count'] ?? 0);
    $p_cnt = (int)($r['prev_6m_count'] ?? 0);
    $cnt = (int)$r['donation_count'];
    $period_tot = (float)$r['period_total'];
    
    $total_collected += $period_tot;
    $total_tx_count += $cnt;
    
    $r_avg = ($r_cnt > 0) ? ($r_tot / $r_cnt) : 0;
    $p_avg = ($p_cnt > 0) ? ($p_tot / $p_cnt) : 0;
    
    // Growth percentage
    $growth_pct = 0;
    if ($p_tot > 0) {
        $growth_pct = round((($r_tot - $p_tot) / $p_tot) * 100);
    } elseif ($r_tot > 0 && $p_tot == 0) {
        $growth_pct = 100;
    }
    
    // Status Determination
    $is_recent_active = ($last_d >= '1405/03/01');
    
    if ($is_recent_active) {
        if ($first_d >= '1405/01/01') {
            $status_code = 'new';
            $status_label = 'نیکوکار جدید';
            $status_color = 'bg-blue-50 text-blue-700 border-blue-200';
            $status_icon = '🆕';
            $count_new++;
        } elseif (($p_tot > 0 && $r_tot >= $p_tot * 1.15) || ($p_avg > 0 && $r_avg >= $p_avg * 1.15 && $r_cnt >= 2)) {
            $status_code = 'increased';
            $status_label = 'روند افزایشی';
            $status_color = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            $status_icon = '📈';
            $count_increased++;
        } elseif ($r_cnt >= 4 || ($cnt >= 6 && $r_cnt >= 2 && $r_avg >= $p_avg * 0.80)) {
            $status_code = 'steady';
            $status_label = 'منظم و پایدار';
            $status_color = 'bg-teal-50 text-teal-700 border-teal-200';
            $status_icon = '💎';
            $count_steady++;
        } elseif ($p_tot > 0 && $r_tot <= $p_tot * 0.65 && $r_cnt < $p_cnt && $r_cnt <= 2) {
            $status_code = 'decreased';
            $status_label = 'روند کاهشی';
            $status_color = 'bg-amber-50 text-amber-700 border-amber-200';
            $status_icon = '📉';
            $count_decreased++;
        } else {
            $status_code = 'active';
            $status_label = 'فعال';
            $status_color = 'bg-green-50 text-green-700 border-green-200';
            $status_icon = '🟢';
            $count_active++;
        }
    } else {
        if ($cnt == 1) {
            $status_code = 'onetime';
            $status_label = 'تک‌پرداخت / موردی';
            $status_color = 'bg-slate-50 text-slate-600 border-slate-200';
            $status_icon = '🔹';
            $count_onetime++;
        } else {
            $status_code = 'lapsed';
            $status_label = 'راکد / نیازمند تماس';
            $status_color = 'bg-rose-50 text-rose-700 border-rose-200';
            $status_icon = '⚠️';
            $count_lapsed++;
        }
    }
    
    $avg_amount = $cnt > 0 ? round($period_tot / $cnt) : 0;
    
    $donors_data[] = [
        'id' => (int)$r['id'],
        'name' => (string)($r['name'] ?? ''),
        'surname' => (string)($r['surname'] ?? ''),
        'full_name' => trim(($r['name'] ?? '') . ' ' . ($r['surname'] ?? '')),
        'phone' => (string)($r['phone'] ?? ''),
        'card_number' => (string)($r['card_number'] ?? ''),
        'photo_path' => (string)($r['photo_path'] ?? ''),
        'donation_count' => $cnt,
        'period_total' => $period_tot,
        'lifetime_total' => (float)($r['lifetime_total'] ?? $period_tot),
        'avg_amount' => $avg_amount,
        'first_donation' => (string)$first_d,
        'last_donation' => (string)$last_d,
        'last_donation_amount' => (float)($r['last_donation_amount'] ?? 0),
        'recent_6m_total' => $r_tot,
        'prev_6m_total' => $p_tot,
        'growth_pct' => $growth_pct,
        'status_code' => $status_code,
        'status_label' => $status_label,
        'status_color' => $status_color,
        'status_icon' => $status_icon,
    ];
}

$total_donors_count = count($donors_data);
$avg_donation_per_donor = $total_donors_count > 0 ? round($total_collected / $total_donors_count) : 0;

// ----------------------------------------------------
// MONTHLY BREAKDOWN FOR CHART
// ----------------------------------------------------
$chart_stmt = $pdo->prepare("
    SELECT SUBSTR(date, 1, 7) as ym, COUNT(*) as tx_count, SUM(amount) as total_amount
    FROM donations
    WHERE date >= ?
    GROUP BY ym
    ORDER BY ym ASC
");
$chart_stmt->execute([$start_date]);
$monthly_chart_raw = $chart_stmt->fetchAll();

$max_monthly = 0;
foreach ($monthly_chart_raw as $m) {
    if ((float)$m['total_amount'] > $max_monthly) {
        $max_monthly = (float)$m['total_amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سامانه جامع تحلیل و رفتارشناسی خیرین | بنیاد حکمت</title>
<style>
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:100;font-display:swap;src:url('/assets/fonts/Vazirmatn-100.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:300;font-display:swap;src:url('/assets/fonts/Vazirmatn-300.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:swap;src:url('/assets/fonts/Vazirmatn-500.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
</style>
<link rel="stylesheet" href="/assets/tailwind.min.css">
<script defer src="/assets/alpine.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
                    colors: { primary: { 900: '#00141e', 800: '#115e59', 600: '#14b8a6' } }
                }
            }
        }
    </script>
    <style>
        @media print {
            body { background: white !important; color: black !important; font-size: 11px !important; }
            nav, header, .no-print, button, form, .filter-bar, input[type="checkbox"] { display: none !important; }
            .print-only { display: block !important; }
            .card { border: 1px solid #ddd !important; box-shadow: none !important; padding: 10px !important; border-radius: 0 !important; }
            table { width: 100% !important; border-collapse: collapse !important; margin-top: 10px !important; }
            th, td { border: 1px solid #cbd5e1 !important; padding: 6px 8px !important; font-size: 10px !important; }
            th { background-color: #f1f5f9 !important; color: #0f172a !important; }
            tr { page-break-inside: avoid !important; }
        }
        .pdf-export-mode {
            background: white !important;
            padding: 20px !important;
            color: #1e293b !important;
            font-family: 'Vazirmatn', sans-serif !important;
        }
    </style>
    <link rel="apple-touch-icon" href="../logo.png">
    <link rel="icon" type="image/png" href="../logo.png">
    <script>
        const RAW_DONORS_DATA = <?php echo json_encode($donors_data, JSON_UNESCAPED_UNICODE); ?>;
        
        function donorAnalytics() {
            return {
                searchQuery: '',
                activeFilter: 'all',
                selectedIds: [],
                isGeneratingPdf: false,
                donors: RAW_DONORS_DATA || [],
                
                get filteredList() {
                    const q = (this.searchQuery || '').toLowerCase().trim();
                    return (this.donors || []).filter(d => {
                        const matchStatus = (this.activeFilter === 'all') || (d.status_code === this.activeFilter);
                        const matchQuery = (q === '') || 
                                           (d.full_name && d.full_name.toLowerCase().includes(q)) || 
                                           (d.phone && String(d.phone).includes(q)) || 
                                           (d.card_number && String(d.card_number).includes(q));
                        return matchStatus && matchQuery;
                    });
                },
                
                formatCard(card) {
                    if (!card) return '';
                    const s = String(card).trim();
                    return s.length >= 10 ? '💳 ' + s.substring(0,6) + '...' + s.substring(s.length-4) : '💳 ' + s;
                },
                
                formatNameInitial(name) {
                    if (!name) return 'خ';
                    return String(name).trim().substring(0, 1) || 'خ';
                },
                
                get exportList() {
                    if (this.selectedIds.length > 0) {
                        return (this.donors || []).filter(d => this.selectedIds.includes(d.id));
                    }
                    return this.filteredList;
                },
                
                get selectedTotalAmount() {
                    return this.exportList.reduce((sum, d) => sum + (Number(d.period_total) || 0), 0);
                },
                
                get isAllSelected() {
                    const list = this.filteredList;
                    if (!list || list.length === 0) return false;
                    return list.every(d => this.selectedIds.includes(d.id));
                },
                
                toggleSelectAll() {
                    const visibleIds = this.filteredList.map(d => d.id);
                    const allSelected = visibleIds.every(id => this.selectedIds.includes(id));
                    if (allSelected) {
                        this.selectedIds = this.selectedIds.filter(id => !visibleIds.includes(id));
                    } else {
                        visibleIds.forEach(id => {
                            if (!this.selectedIds.includes(id)) this.selectedIds.push(id);
                        });
                    }
                },
                
                toggleSelect(id) {
                    const idx = this.selectedIds.indexOf(id);
                    if (idx > -1) {
                        this.selectedIds.splice(idx, 1);
                    } else {
                        this.selectedIds.push(id);
                    }
                },
                
                isSelected(id) {
                    return this.selectedIds.includes(id);
                },
                
                clearSelection() {
                    this.selectedIds = [];
                },
                
                downloadPdf(onlySelected = false) {
                    this.printReport(onlySelected);
                },
                
                printReport(onlySelected = false) {
                    const list = onlySelected ? (this.donors || []).filter(d => this.selectedIds.includes(d.id)) : this.exportList;
                    if (!list || list.length === 0) {
                        alert('هیچ خیری برای چاپ انتخاب نشده است.');
                        return;
                    }

                    const totalAmount = list.reduce((sum, d) => sum + (Number(d.period_total) || 0), 0);
                    const totalCount = list.length;
                    
                    let rowsHtml = '';
                    list.forEach((d, idx) => {
                        let trendText = '---';
                        if (d.status_code === 'increased') trendText = '+' + d.growth_pct + '% رشد';
                        else if (d.status_code === 'decreased') trendText = d.growth_pct + '% افت';
                        else if (d.status_code === 'steady') trendText = 'مستمر و پایدار';
                        else if (d.status_code === 'new') trendText = 'عضویت جدید';
                        else if (d.status_code === 'lapsed') trendText = 'عدم واریز > ۳ ماه';

                        rowsHtml += `
                            <tr>
                                <td style="text-align:center; font-weight:bold;">${idx + 1}</td>
                                <td>
                                    <strong>${d.full_name}</strong>
                                    ${d.phone ? '<br><small style="color:#64748b;">' + d.phone + '</small>' : ''}
                                </td>
                                <td style="text-align:center;">
                                    <span style="display:inline-block; padding:2px 6px; border-radius:4px; font-size:9px; border:1px solid #cbd5e1; background:#f8fafc;">
                                        ${d.status_label}
                                    </span>
                                </td>
                                <td style="text-align:center; font-family:monospace;">${d.last_donation || '---'}</td>
                                <td style="text-align:center; font-weight:bold;">${d.donation_count}</td>
                                <td style="text-align:left; font-family:monospace; direction:ltr;">${Number(d.last_donation_amount).toLocaleString('fa-IR')}</td>
                                <td style="text-align:center; font-size:9px;">${trendText}</td>
                                <td style="text-align:left; font-family:monospace; font-weight:bold; direction:ltr;">${Number(d.period_total).toLocaleString('fa-IR')}</td>
                            </tr>
                        `;
                    });

                    const printWindow = window.open('', '_blank', 'width=1100,height=800');
                    if (!printWindow) {
                        alert('لطفاً به مرورگر اجازه باز کردن پنجره چاپ را بدهید.');
                        return;
                    }

                    const printDoc = `
                        <!DOCTYPE html>
                        <html lang="fa" dir="rtl">
                        <head>
                            <meta charset="UTF-8">
                            <title>گزارش رفتارشناسی و مشارکت نیکوکاران | بنیاد حکمت</title>
                            <style>
                                @page { size: A4 landscape; margin: 8mm; }
                                * { box-sizing: border-box; }
                                body { font-family: 'Vazirmatn', Tahoma, sans-serif; direction: rtl; margin: 0; padding: 15px; color: #1e293b; background: white; }
                                .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0f766e; padding-bottom: 10px; margin-bottom: 12px; }
                                .title { font-size: 16px; font-weight: 900; color: #00141e; margin: 0; }
                                .sub { font-size: 11px; color: #64748b; margin-top: 3px; }
                                .meta { font-size: 10px; line-height: 1.6; color: #334155; text-align: left; }
                                .summary-cards { display: flex; gap: 12px; margin-bottom: 12px; }
                                .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 12px; flex: 1; }
                                .card-title { font-size: 9px; color: #64748b; }
                                .card-val { font-size: 12px; font-weight: 900; color: #0f766e; margin-top: 2px; }
                                table { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 5px; }
                                th, td { border: 1px solid #cbd5e1; padding: 5px 7px; text-align: right; }
                                th { background-color: #f1f5f9; color: #0f172a; font-weight: 900; text-align: center; }
                                tr:nth-child(even) { background-color: #fafafa; }
                                tr { page-break-inside: avoid; }
                                .footer { margin-top: 15px; font-size: 9px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 6px; }
                            </style>
                        </head>
                        <body>
                            <div class="header">
                                <div>
                                    <h1 class="title">بنیاد خیریه و نیکوکاری حکمت</h1>
                                    <div class="sub">گزارش تحلیلی و رفتارشناسی نیکوکاران ${onlySelected ? '(موارد منتخب)' : ''}</div>
                                </div>
                                <div class="meta">
                                    <div><strong>تاریخ تهیه گزارش:</strong> <?php echo toFarsiDigits(date('Y/m/d')); ?></div>
                                    <div><strong>بازه زمانی:</strong> <?php echo $period_label; ?></div>
                                </div>
                            </div>

                            <div class="summary-cards">
                                <div class="card">
                                    <div class="card-title">تعداد نیکوکاران این گزارش</div>
                                    <div class="card-val">${totalCount.toLocaleString('fa-IR')} نفر</div>
                                </div>
                                <div class="card">
                                    <div class="card-title">مجموع مبالغ جذب شده</div>
                                    <div class="card-val">${Number(totalAmount).toLocaleString('fa-IR')} ریال</div>
                                </div>
                                <div class="card">
                                    <div class="card-title">معادل به تومان</div>
                                    <div class="card-val">${Number(Math.round(totalAmount / 10000000)).toLocaleString('fa-IR')} میلیون تومان</div>
                                </div>
                            </div>

                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 35px;">ردیف</th>
                                        <th>نام و مشخصات نیکوکار</th>
                                        <th style="width: 95px;">وضعیت رفتار مالی</th>
                                        <th style="width: 75px;">آخرین واریزی</th>
                                        <th style="width: 55px;">تعداد</th>
                                        <th style="width: 95px;">آخرین مبلغ (ریال)</th>
                                        <th style="width: 85px;">روند اخیر</th>
                                        <th style="width: 110px;">مجموع در بازه (ریال)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${rowsHtml}
                                </tbody>
                            </table>

                            <div class="footer">
                                سامانه مدیریت و هوش مالی بنیاد خیریه حکمت — صفحه چاپ و خروجی PDF
                            </div>

                            <script>
                                window.onload = function() {
                                    setTimeout(function() {
                                        window.print();
                                    }, 300);
                                };
                            <\/script>
                        </body>
                        </html>
                    `;

                    printWindow.document.open();
                    printWindow.document.write(printDoc);
                    printWindow.document.close();
                },
                
                exportCsv(onlySelected = false) {
                    const list = onlySelected ? (this.donors || []).filter(d => this.selectedIds.includes(d.id)) : this.exportList;
                    if (!list || list.length === 0) {
                        alert('هیچ خیری برای خروجی اکسل انتخاب نشده است.');
                        return;
                    }

                    let csvContent = '\uFEFF';
                    csvContent += 'ردیف,نام و مشخصات,شماره تماس,شماره کارت,وضعیت رفتار مالی,آخرین تاریخ واریز,تعداد واریزی,آخرین مبلغ واریزی (ریال),میانگین هر واریزی (ریال),روند تغییرات اخیر,مجموع واریزی در بازه (ریال),مجموع کل سوابق (ریال)\n';
                    list.forEach((d, idx) => {
                        let trend = '---';
                        if (d.status_code === 'increased') trend = '+' + d.growth_pct + '% رشد';
                        else if (d.status_code === 'decreased') trend = d.growth_pct + '% افت';
                        else if (d.status_code === 'steady') trend = 'مستمر و بدون نوسان';
                        else if (d.status_code === 'new') trend = 'عضویت جدید';
                        else if (d.status_code === 'lapsed') trend = 'عدم واریز > ۳ ماه';
                        
                        const name = (d.full_name || '').replace(/"/g, '""');
                        const phone = (d.phone || '').replace(/"/g, '""');
                        const card = (d.card_number || '').replace(/"/g, '""');
                        const status = (d.status_label || '').replace(/"/g, '""');
                        const lastDate = (d.last_donation || '').replace(/"/g, '""');
                        
                        csvContent += `"${idx+1}","${name}","${phone}","${card}","${status}","${lastDate}","${d.donation_count}","${d.last_donation_amount}","${d.avg_amount}","${trend}","${d.period_total}","${d.lifetime_total}"\n`;
                    });

                    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = (onlySelected ? 'گزارش_خیرین_منتخب_بنیاد_حکمت.csv' : 'گزارش_جامع_خیرین_بنیاد_حکمت.csv');
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            };
        }
        window.donorAnalytics = donorAnalytics;
        document.addEventListener('alpine:init', () => {
            Alpine.data('donorAnalytics', donorAnalytics);
        });
    </script>
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased" x-data="donorAnalytics()">

    <!-- Dashboard Navigation -->
    <?php 
    $base_url = '../';
    include __DIR__ . '/../includes/dashboard-nav.php'; 
    ?>

    <!-- Header -->
    <header class="bg-gradient-to-l from-primary-900 via-primary-800 to-indigo-950 text-white py-14 no-print shadow-xl relative overflow-hidden">
        <div class="absolute -right-20 -top-20 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl"></div>
        <div class="container mx-auto px-6 relative z-10">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-teal-500/20 border border-teal-400/30 rounded-full text-teal-300 text-xs font-bold mb-3">
                        <span>📊</span> گزارش تحلیلی و هوش مالی حامیان
                    </div>
                    <h1 class="text-3xl md:text-4xl font-black mb-2">رصد رفتار و تراز مشارکت نیکوکاران</h1>
                    <p class="text-teal-100/80 text-xs md:text-sm max-w-2xl leading-relaxed">
                        تحلیل جامع واریزی‌ها، تفکیک حامیان با روند افزایشی/کاهشی، شناسایی حامیان منظم و پیگیری خودکار حامیان راکد در بازه ۲ ساله.
                    </p>
                </div>
                
                <!-- Period Selector & Actions -->
                <div class="flex flex-wrap items-center gap-3">
                    <form method="GET" class="flex items-center gap-2">
                        <select name="period" onchange="this.form.submit()" class="bg-white/10 text-white text-xs font-black border border-white/20 rounded-2xl px-4 py-3 focus:outline-none focus:bg-primary-900 cursor-pointer backdrop-blur-md">
                            <option value="2years" <?php echo $period === '2years' ? 'selected' : ''; ?>>📅 ۲ سال اخیر (۱۴۰۳ تا ۱۴۰۵)</option>
                            <option value="1year" <?php echo $period === '1year' ? 'selected' : ''; ?>>📅 ۱ سال اخیر</option>
                            <option value="6months" <?php echo $period === '6months' ? 'selected' : ''; ?>>📅 ۶ ماه اخیر</option>
                            <option value="1405" <?php echo $period === '1405' ? 'selected' : ''; ?>>📅 سال ۱۴۰۵</option>
                            <option value="1404" <?php echo $period === '1404' ? 'selected' : ''; ?>>📅 سال ۱۴۰۴</option>
                            <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>📅 کل تاریخچه</option>
                        </select>
                    </form>
                    
                    <button @click="downloadPdf(selectedIds.length > 0)" :disabled="isGeneratingPdf" class="bg-teal-500 hover:bg-teal-400 text-primary-900 px-5 py-3 rounded-2xl text-xs font-black transition-all shadow-lg flex items-center gap-2 disabled:opacity-50 cursor-pointer" title="دانلود خروجی PDF">
                        <span x-show="!isGeneratingPdf">📄</span>
                        <span x-show="isGeneratingPdf" class="animate-spin text-sm">⏳</span>
                        <span x-text="isGeneratingPdf ? 'در حال تولید PDF...' : (selectedIds.length > 0 ? 'دانلود PDF منتخب‌ها (' + selectedIds.length + ')' : 'دانلود گزارش PDF')"></span>
                    </button>

                    <button @click="exportCsv(selectedIds.length > 0)" class="bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-3 rounded-2xl text-xs font-black transition-all shadow-lg flex items-center gap-2 cursor-pointer" title="دانلود خروجی کامل یا منتخب اکسل">
                        <span>📥</span>
                        <span x-text="selectedIds.length > 0 ? 'اکسل منتخب‌ها (' + selectedIds.length + ')' : 'خروجی اکسل'"></span>
                    </button>

                    <button @click="printReport(selectedIds.length > 0)" class="bg-white/10 hover:bg-white/20 text-white px-4 py-3 rounded-2xl text-xs font-black transition-all border border-white/15 cursor-pointer" title="چاپ مستقیم گزارش">
                        <span>🖨️</span> چاپ
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 py-10 -mt-8 relative z-20 pb-36">
        
        <!-- Printable Report Container for PDF and Print -->
        <div id="report-export-container">
            <!-- Print / PDF Header -->
            <div class="hidden print-only mb-6 text-center border-b border-gray-200 pb-4">
                <div class="flex justify-between items-center mb-3">
                    <div class="text-right">
                        <h2 class="text-lg font-black text-gray-900">بنیاد خیریه و نیکوکاری حکمت</h2>
                        <p class="text-[11px] text-gray-500">سامانه جامع گزارش‌های مالی و رفتارشناسی حامیان</p>
                    </div>
                    <div class="text-center">
                        <h1 class="text-xl font-black text-primary-900" x-text="selectedIds.length > 0 ? 'گزارش اختصاصی نیکوکاران منتخب' : 'گزارش جامع رفتار و تراز مشارکت نیکوکاران'"></h1>
                        <p class="text-xs text-gray-600 mt-1">بازه زمانی: <?php echo $period_label; ?></p>
                    </div>
                    <div class="text-left text-[10px] text-gray-500 font-mono">
                        <div>تاریخ تهیه: <?php echo toFarsiDigits(date('Y/m/d')); ?></div>
                        <div>تعداد افراد: <span x-text="exportList.length"></span> نفر</div>
                    </div>
                </div>
            </div>

            <!-- 1. Executive Summary Cards (KPIs) -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-8 no-print">
                <!-- Total Amount -->
                <div class="bg-white p-5 rounded-[2rem] shadow-sm border border-gray-100 flex flex-col justify-between col-span-2 md:col-span-2">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] text-gray-400 font-bold">مجموع جذب سرمایه (ریال)</span>
                        <span class="w-8 h-8 bg-teal-50 text-teal-600 rounded-xl flex items-center justify-center text-sm">💰</span>
                    </div>
                    <div>
                        <div class="text-2xl lg:text-3xl font-black text-primary-900" dir="ltr"><?php echo toFarsiDigits(number_format($total_collected)); ?></div>
                        <div class="text-[10px] text-teal-600 font-bold mt-1">معادل <?php echo toFarsiDigits(number_format(round($total_collected / 10000000))); ?> میلیون تومان</div>
                    </div>
                </div>

                <!-- Active Donors Count -->
                <div class="bg-white p-5 rounded-[2rem] shadow-sm border border-gray-100 flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] text-gray-400 font-bold">خیرین مشارکت‌کننده</span>
                        <span class="w-7 h-7 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-xs">👥</span>
                    </div>
                    <div>
                        <div class="text-2xl font-black text-primary-900"><?php echo toFarsiDigits($total_donors_count); ?> <span class="text-[10px] text-gray-400">حامی</span></div>
                        <div class="text-[9px] text-gray-400 mt-1"><?php echo toFarsiDigits($total_tx_count); ?> تراکنش ثبت شده</div>
                    </div>
                </div>

                <!-- Increased Trend -->
                <div class="bg-white p-5 rounded-[2rem] shadow-sm border border-emerald-100 flex flex-col justify-between hover:shadow-md transition-all cursor-pointer" @click="activeFilter = 'increased'">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] text-emerald-700 font-bold">روند افزایشی</span>
                        <span class="w-7 h-7 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-xs">📈</span>
                    </div>
                    <div>
                        <div class="text-2xl font-black text-emerald-600"><?php echo toFarsiDigits($count_increased); ?></div>
                        <div class="text-[9px] text-emerald-600/70 mt-1 font-bold">رشد کمک‌ها در دوره‌های اخیر</div>
                    </div>
                </div>

                <!-- Decreased Trend -->
                <div class="bg-white p-5 rounded-[2rem] shadow-sm border border-amber-100 flex flex-col justify-between hover:shadow-md transition-all cursor-pointer" @click="activeFilter = 'decreased'">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] text-amber-700 font-bold">روند کاهشی</span>
                        <span class="w-7 h-7 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center text-xs">📉</span>
                    </div>
                    <div>
                        <div class="text-2xl font-black text-amber-600"><?php echo toFarsiDigits($count_decreased); ?></div>
                        <div class="text-[9px] text-amber-600/70 mt-1 font-bold">افت مبالغ یا تعداد واریزی</div>
                    </div>
                </div>

                <!-- Steady / Regular -->
                <div class="bg-white p-5 rounded-[2rem] shadow-sm border border-teal-100 flex flex-col justify-between hover:shadow-md transition-all cursor-pointer" @click="activeFilter = 'steady'">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] text-teal-700 font-bold">منظم و پایدار</span>
                        <span class="w-7 h-7 bg-teal-50 text-teal-600 rounded-xl flex items-center justify-center text-xs">💎</span>
                    </div>
                    <div>
                        <div class="text-2xl font-black text-teal-600"><?php echo toFarsiDigits($count_steady); ?></div>
                        <div class="text-[9px] text-teal-600/70 mt-1 font-bold">واریزی مستمر و بدون وقفه</div>
                    </div>
                </div>

                <!-- Lapsed / Need Follow-up -->
                <div class="bg-white p-5 rounded-[2rem] shadow-sm border border-rose-100 flex flex-col justify-between hover:shadow-md transition-all cursor-pointer" @click="activeFilter = 'lapsed'">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] text-rose-700 font-bold">نیازمند پیگیری</span>
                        <span class="w-7 h-7 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center text-xs">⚠️</span>
                    </div>
                    <div>
                        <div class="text-2xl font-black text-rose-600"><?php echo toFarsiDigits($count_lapsed); ?></div>
                        <div class="text-[9px] text-rose-600/70 mt-1 font-bold">عدم واریز > ۳ ماه</div>
                    </div>
                </div>
            </div>

            <!-- 2. Monthly Collection Timeline Chart -->
            <div class="bg-white rounded-[2.5rem] p-8 shadow-sm border border-gray-100 mb-8 no-print card">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-black text-gray-900">توزیع ماهانه جذب سرمایه در بازه انتخابی</h3>
                        <p class="text-xs text-gray-400 mt-1">تغییرات ماه به ماه مبالغ جذب شده از خیرین</p>
                    </div>
                    <div class="text-xs font-bold text-gray-500 bg-gray-50 px-3 py-1.5 rounded-xl">
                        میانگین جذب در ماه: <span class="text-teal-600 font-black" dir="ltr"><?php echo count($monthly_chart_raw) > 0 ? toFarsiDigits(number_format(round($total_collected / count($monthly_chart_raw)))) : 0; ?></span> ریال
                    </div>
                </div>

                <div class="flex items-end gap-2 h-44 pt-6 border-b border-gray-100 overflow-x-auto pb-2">
                    <?php if (empty($monthly_chart_raw)): ?>
                        <div class="w-full text-center text-gray-400 text-xs py-10 font-bold">هیچ رکوردی در این بازه ثبت نشده است.</div>
                    <?php else: ?>
                        <?php foreach ($monthly_chart_raw as $m): 
                            $amt = (float)$m['total_amount'];
                            $pct = $max_monthly > 0 ? round(($amt / $max_monthly) * 100) : 0;
                            $ym_farsi = toFarsiDigits($m['ym']);
                        ?>
                            <div class="flex-1 min-w-[36px] flex flex-col items-center gap-2 group relative">
                                <div class="absolute -top-10 bg-primary-900 text-white text-[9px] px-2 py-1 rounded-lg opacity-0 group-hover:opacity-100 transition-all pointer-events-none whitespace-nowrap shadow-xl z-30">
                                    <div class="font-bold"><?php echo $ym_farsi; ?></div>
                                    <div dir="ltr"><?php echo toFarsiDigits(number_format($amt)); ?> ریال</div>
                                    <div class="text-teal-300"><?php echo toFarsiDigits($m['tx_count']); ?> واریزی</div>
                                </div>

                                <div class="w-full bg-slate-100 rounded-t-xl overflow-hidden h-32 flex items-end">
                                    <div class="w-full bg-gradient-to-t from-teal-600 to-teal-400 rounded-t-xl transition-all group-hover:from-emerald-500 group-hover:to-teal-300" style="height: <?php echo max(6, $pct); ?>%;"></div>
                                </div>
                                <span class="text-[9px] text-gray-400 font-bold rotate-45 md:rotate-0 origin-right md:origin-center mt-1 block truncate w-full text-center"><?php echo $ym_farsi; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3. Filter Tabs & Live Search Bar -->
            <div class="bg-white rounded-[2.5rem] p-4 shadow-sm border border-gray-100 mb-8 flex flex-col lg:flex-row justify-between items-center gap-4 no-print">
                <!-- Filter Tabs -->
                <div class="flex flex-wrap items-center gap-1 w-full lg:w-auto">
                    <button @click="activeFilter = 'all'" :class="activeFilter === 'all' ? 'bg-primary-900 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100'" class="px-4 py-2.5 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer">
                        همه خیرین (<?php echo toFarsiDigits($total_donors_count); ?>)
                    </button>
                    <button @click="activeFilter = 'increased'" :class="activeFilter === 'increased' ? 'bg-emerald-600 text-white shadow-md' : 'text-emerald-700 hover:bg-emerald-50'" class="px-4 py-2.5 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer">
                        <span>📈</span> روند افزایشی (<?php echo toFarsiDigits($count_increased); ?>)
                    </button>
                    <button @click="activeFilter = 'decreased'" :class="activeFilter === 'decreased' ? 'bg-amber-600 text-white shadow-md' : 'text-amber-700 hover:bg-amber-50'" class="px-4 py-2.5 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer">
                        <span>📉</span> روند کاهشی (<?php echo toFarsiDigits($count_decreased); ?>)
                    </button>
                    <button @click="activeFilter = 'steady'" :class="activeFilter === 'steady' ? 'bg-teal-600 text-white shadow-md' : 'text-teal-700 hover:bg-teal-50'" class="px-4 py-2.5 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer">
                        <span>💎</span> منظم و پایدار (<?php echo toFarsiDigits($count_steady); ?>)
                    </button>
                    <button @click="activeFilter = 'lapsed'" :class="activeFilter === 'lapsed' ? 'bg-rose-600 text-white shadow-md' : 'text-rose-700 hover:bg-rose-50'" class="px-4 py-2.5 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer">
                        <span>⚠️</span> نیازمند پیگیری (<?php echo toFarsiDigits($count_lapsed); ?>)
                    </button>
                    <button @click="activeFilter = 'new'" :class="activeFilter === 'new' ? 'bg-blue-600 text-white shadow-md' : 'text-blue-700 hover:bg-blue-50'" class="px-4 py-2.5 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer">
                        <span>🆕</span> حامیان جدید (<?php echo toFarsiDigits($count_new); ?>)
                    </button>
                    <button @click="activeFilter = 'onetime'" :class="activeFilter === 'onetime' ? 'bg-slate-600 text-white shadow-md' : 'text-slate-700 hover:bg-slate-100'" class="px-4 py-2.5 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 cursor-pointer">
                        <span>🔹</span> تک‌پرداخت (<?php echo toFarsiDigits($count_onetime); ?>)
                    </button>
                </div>

                <!-- Live Search -->
                <div class="relative w-full lg:w-80">
                    <input type="text" x-model="searchQuery" placeholder="جستجوی نام خیر، کارت، شماره تلفن..." class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-12 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-teal-500 focus:bg-white transition-all">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm">🔍</span>
                    <button x-show="searchQuery !== ''" @click="searchQuery = ''" class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 text-xs font-bold">✕</button>
                </div>
            </div>

            <!-- 4. Master Behavioral Table -->
            <div class="bg-white rounded-[3rem] p-8 shadow-xl border border-gray-100 overflow-x-auto card">
                
                <!-- Table Action Utility Bar -->
                <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-100 no-print">
                    <div class="flex items-center gap-3">
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-bold text-gray-700">
                            <input type="checkbox" @change="toggleSelectAll()" :checked="isAllSelected" class="w-4 h-4 rounded text-teal-600 focus:ring-teal-500 cursor-pointer">
                            <span>انتخاب همه موارد این صفحه (<span x-text="filteredList.length"></span> خیر)</span>
                        </label>
                        <template x-if="selectedIds.length > 0">
                            <span class="px-2.5 py-1 bg-teal-50 text-teal-700 border border-teal-200 rounded-full text-[11px] font-black">
                                <span x-text="selectedIds.length"></span> مورد انتخاب شده
                            </span>
                        </template>
                    </div>

                    <div class="flex items-center gap-2 text-xs font-bold text-gray-500">
                        <span>نمایش: <span class="font-black text-gray-900" x-text="filteredList.length"></span> از <span class="font-black"><?php echo toFarsiDigits($total_donors_count); ?></span> خیر</span>
                    </div>
                </div>

                <table class="w-full text-right whitespace-nowrap">
                    <thead>
                        <tr class="text-[11px] text-gray-400 uppercase tracking-wider border-b border-gray-50">
                            <th class="pb-6 font-bold w-10 text-center no-print">
                                <input type="checkbox" @change="toggleSelectAll()" :checked="isAllSelected" class="w-4 h-4 rounded text-teal-600 focus:ring-teal-500 cursor-pointer" title="انتخاب / لغو انتخاب همه">
                            </th>
                            <th class="pb-6 font-bold w-12 text-center">ردیف</th>
                            <th class="pb-6 font-bold">نام و مشخصات نیکوکار</th>
                            <th class="pb-6 font-bold text-center">وضعیت رفتار مالی</th>
                            <th class="pb-6 font-bold">آخرین واریزی</th>
                            <th class="pb-6 font-bold text-center">تعداد واریز</th>
                            <th class="pb-6 font-bold">آخرین مقدار واریزی (ریال)</th>
                            <th class="pb-6 font-bold">روند تغییرات اخیر</th>
                            <th class="pb-6 font-bold">مجموع واریز تا کنون (ریال)</th>
                            <th class="pb-6 font-bold text-center no-print">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs text-gray-700">
                        <template x-if="filteredList.length === 0">
                            <tr>
                                <td colspan="10" class="py-12 text-center text-gray-400 font-bold">
                                    هیچ خیری با مشخصات یا فیلتر انتخابی یافت نشد.
                                </td>
                            </tr>
                        </template>

                        <template x-for="(d, index) in filteredList" :key="d.id">
                            <tr class="border-b border-gray-50 hover:bg-gray-50/70 transition-colors" :class="isSelected(d.id) ? 'bg-teal-50/40' : ''">
                                
                                <!-- Checkbox -->
                                <td class="py-4 text-center no-print">
                                    <input type="checkbox" :value="d.id" @change="toggleSelect(d.id)" :checked="isSelected(d.id)" class="w-4 h-4 rounded text-teal-600 focus:ring-teal-500 cursor-pointer">
                                </td>

                                <!-- Index -->
                                <td class="py-4 text-center text-gray-400 font-bold" x-text="index + 1"></td>

                                <!-- Donor Identity -->
                                <td class="py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl overflow-hidden bg-gray-100 flex items-center justify-center font-bold text-primary-900 border border-gray-200 no-print">
                                            <template x-if="d.photo_path">
                                                <img :src="d.photo_path" class="w-full h-full object-cover">
                                            </template>
                                            <template x-if="!d.photo_path">
                                                <span x-text="formatNameInitial(d.name)"></span>
                                            </template>
                                        </div>
                                        <div>
                                            <a :href="'../donor-detail.php?id=' + d.id" class="font-black text-gray-900 hover:text-teal-600 transition-colors block text-sm" x-text="d.full_name"></a>
                                            <div class="flex items-center gap-2 text-[10px] text-gray-400 mt-0.5 font-mono">
                                                <span x-show="d.phone" x-text="d.phone"></span>
                                                <span x-show="d.card_number" x-text="formatCard(d.card_number)"></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Behavioral Badge -->
                                <td class="py-4 text-center">
                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-black border" :class="d.status_color">
                                        <span x-text="d.status_icon"></span>
                                        <span x-text="d.status_label"></span>
                                    </span>
                                </td>

                                <!-- Last Donation -->
                                <td class="py-4">
                                    <span class="px-2.5 py-1 bg-gray-100 text-gray-700 rounded-lg text-[10px] font-bold font-mono" x-text="d.last_donation || '---'"></span>
                                </td>

                                <!-- Count -->
                                <td class="py-4 text-center">
                                    <span class="font-black text-gray-800 text-sm" x-text="d.donation_count"></span>
                                    <span class="text-[9px] text-gray-400">مرتبه</span>
                                </td>

                                <!-- Last Donation Amount -->
                                <td class="py-4 font-bold text-gray-800 text-xs" dir="ltr" x-text="Number(d.last_donation_amount).toLocaleString('fa-IR')"></td>

                                <!-- Trend Comparison -->
                                <td class="py-4">
                                    <template x-if="d.status_code === 'increased'">
                                        <div class="flex items-center gap-1 text-emerald-600 font-black text-[11px]">
                                            <span>↑</span>
                                            <span x-text="'+' + d.growth_pct + '%'"></span>
                                            <span class="text-[9px] text-gray-400 font-normal">رشد دوره</span>
                                        </div>
                                    </template>
                                    <template x-if="d.status_code === 'decreased'">
                                        <div class="flex items-center gap-1 text-amber-600 font-black text-[11px]">
                                            <span>↓</span>
                                            <span x-text="d.growth_pct + '%'"></span>
                                            <span class="text-[9px] text-gray-400 font-normal">افت دوره</span>
                                        </div>
                                    </template>
                                    <template x-if="d.status_code === 'steady'">
                                        <span class="text-[10px] text-teal-600 font-bold">مستمر و بدون نوسان</span>
                                    </template>
                                    <template x-if="d.status_code === 'new'">
                                        <span class="text-[10px] text-blue-600 font-bold">عضویت جدید</span>
                                    </template>
                                    <template x-if="d.status_code === 'lapsed'">
                                        <span class="text-[10px] text-rose-500 font-bold">عدم واریز > ۳ ماه</span>
                                    </template>
                                    <template x-if="d.status_code === 'onetime' || d.status_code === 'active'">
                                        <span class="text-[10px] text-gray-400">---</span>
                                    </template>
                                </td>

                                <!-- Period Total / Lifetime Total -->
                                <td class="py-4 text-sm font-black text-primary-900" dir="ltr">
                                    <span x-text="Number(d.period_total).toLocaleString('fa-IR')"></span>
                                </td>

                                <!-- Actions -->
                                <td class="py-4 text-center no-print">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a :href="'../donor-detail.php?id=' + d.id" class="px-3 py-1.5 bg-primary-900 hover:bg-teal-600 text-white rounded-xl text-[10px] font-black transition-all shadow-sm" title="مشاهده پرونده کامل">
                                            پرونده
                                        </a>
                                        <template x-if="d.phone">
                                            <a :href="'tel:' + d.phone" class="p-1.5 bg-gray-100 hover:bg-emerald-100 text-emerald-700 rounded-xl text-xs transition-all" title="تماس تلفنی">
                                                📞
                                            </a>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 5. Floating Action Bar for Selected Donors -->
        <div x-show="selectedIds.length > 0" 
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-y-20 opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="translate-y-0 opacity-100"
             x-transition:leave-end="translate-y-20 opacity-0"
             class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-primary-900/95 backdrop-blur-xl text-white px-6 py-4 rounded-3xl shadow-2xl border border-teal-500/30 flex flex-col md:flex-row items-center gap-4 no-print max-w-4xl w-[92%] justify-between">
            
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-2xl bg-teal-500 text-primary-900 font-black text-sm flex items-center justify-center shadow-md" x-text="selectedIds.length"></span>
                <div>
                    <div class="text-xs font-black text-teal-300">نیکوکار برای گزارش اختصاصی انتخاب شد</div>
                    <div class="text-[11px] text-gray-300 mt-0.5">
                        مجموع واریزی‌های انتخاب‌شده: 
                        <span class="font-bold text-white font-mono" dir="ltr" x-text="Number(selectedTotalAmount).toLocaleString('fa-IR')"></span> ریال
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 w-full md:w-auto justify-end">
                <button @click="downloadPdf(true)" :disabled="isGeneratingPdf" class="px-4 py-2.5 bg-gradient-to-r from-teal-500 to-emerald-500 hover:from-teal-400 hover:to-emerald-400 text-primary-900 rounded-xl text-xs font-black shadow-lg flex items-center gap-1.5 transition-all cursor-pointer disabled:opacity-50">
                    <span x-show="!isGeneratingPdf">📄</span>
                    <span x-show="isGeneratingPdf" class="animate-spin text-xs">⏳</span>
                    <span x-text="isGeneratingPdf ? 'تولید PDF...' : 'دانلود PDF منتخب‌ها'"></span>
                </button>
                <button @click="printReport(true)" class="px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-black border border-white/20 flex items-center gap-1.5 transition-all cursor-pointer">
                    <span>🖨️</span> چاپ
                </button>
                <button @click="exportCsv(true)" class="px-4 py-2.5 bg-emerald-700/80 hover:bg-emerald-600 text-white rounded-xl text-xs font-black flex items-center gap-1.5 transition-all cursor-pointer">
                    <span>📥</span> اکسل
                </button>
                <button @click="clearSelection()" class="p-2 text-gray-400 hover:text-white text-sm font-bold transition-all ml-1 cursor-pointer" title="لغو انتخاب">
                    ✕
                </button>
            </div>
        </div>

    </main>

<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js');
    });
  }
</script>

</body>
</html>
