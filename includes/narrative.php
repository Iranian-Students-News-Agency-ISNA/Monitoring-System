<?php
require_once __DIR__ . '/helpers.php';

// دسته‌بندی ساده «نوع واقعی خبر» بر مبنای متن آزاد کاربر
function classifyRealNewsType(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') return '';
    $map = [
        'مصاحبه'   => 'مصاحبه',
        'گزارش'    => 'گزارش',
        'یادداشت'  => 'یادداشت',
        'تحلیل'    => 'تحلیل',
        'عکس'      => 'خبر تصویری',
        'فیلم'     => 'خبر ویدئویی',
    ];
    foreach ($map as $needle => $label) {
        if (mb_strpos($raw, $needle) !== false) return $label;
    }
    return $raw;
}

function mostCommonService(array $rows): string
{
    $counts = [];
    foreach ($rows as $r) {
        $s = trim((string)($r['service_main'] ?? ''));
        if ($s === '') continue;
        $counts[$s] = ($counts[$s] ?? 0) + 1;
    }
    if (empty($counts)) return '';
    arsort($counts);
    return array_key_first($counts);
}

// جمله آماری بالای گزارش: تفکیک تعداد اخبار بر اساس نوع خبر/نوع واقعی خبر
function buildTypeBreakdownSentence(array $rows): string
{
    $counts = [];
    foreach ($rows as $r) {
        $rt = classifyRealNewsType((string)($r['real_news_type'] ?? ''));
        $nt = trim((string)($r['news_type'] ?? ''));
        $label = $rt !== '' && $nt !== '' ? "{$rt}/{$nt}" : ($rt !== '' ? $rt : ($nt !== '' ? $nt : 'خبر'));
        $counts[$label] = ($counts[$label] ?? 0) + 1;
    }
    if (empty($counts)) return '-';
    $parts = [];
    foreach ($counts as $label => $c) {
        $parts[] = numberToPersianWords($c) . " {$label}";
    }
    if (count($parts) === 1) return $parts[0];
    $last = array_pop($parts);
    return implode('، ', $parts) . ' و ' . $last;
}

// عبارت «به عنوان چه چیزی منتشر شده» برای هر ردیف
function buildPublishTypeDesc(array $row): string
{
    $rt = trim((string)($row['real_news_type'] ?? ''));
    $nt = trim((string)($row['news_type'] ?? ''));
    if (mb_strpos($rt, 'فیلم') !== false || mb_strpos($rt, 'ویدیو') !== false) return 'فیلم';
    if ($rt !== '') return $rt;
    if (mb_strpos($nt, 'دریافتی') !== false) return 'خبر دریافتی';
    if (mb_strpos($nt, 'پوششی') !== false) return 'خبر پوششی';
    if ($nt !== '') return $nt;
    return 'خبر';
}

// توضیح محتوایی خبر برای جمله اصلی (بر اساس سوژه/مصاحبه‌شونده/عناصر خبری)
function buildContentDesc(array $row): string
{
    $interviewee = trim((string)($row['interviewee'] ?? ''));
    $subject = trim((string)($row['subject'] ?? ''));
    $elements = trim((string)($row['news_elements'] ?? ''));
    $desc = $interviewee !== '' ? "گفت‌وگویی با {$interviewee}" : $elements;
    if ($subject !== '') {
        $desc .= $desc !== '' ? " در سوژه «{$subject}»" : "در سوژه «{$subject}»";
    }
    return trim($desc);
}

function buildMainSentence(array $row, int $index): string
{
    $publisher = trim((string)($row['publisher'] ?? '')) ?: 'ناشر نامشخص';
    $title = trim((string)($row['title'] ?? ''));
    $link = trim((string)($row['news_link'] ?? ''));
    $typeDesc = buildPublishTypeDesc($row);
    $contentDesc = buildContentDesc($row);

    $sentence = ($index + 1) . "- {$publisher} خبر «{$title}»" . ($link !== '' ? " ({$link})" : '') .
        " را به عنوان {$typeDesc} منتشر کرده است";
    $sentence .= $contentDesc !== '' ? " که {$contentDesc}." : '.';
    return $sentence;
}

function buildDescriptionSentenceRow(array $row): string
{
    $desc = trim((string)($row['description'] ?? ''));
    return $desc !== '' ? "توضیح کلی: {$desc}" : 'توضیح کلی ثبت نشده است.';
}

function buildElementsSentenceRow(array $row): string
{
    $elements = trim((string)($row['news_elements'] ?? ''));
    return $elements !== '' ? "عناصر خبری: {$elements}" : 'عناصر خبری ثبت نشده است.';
}

function buildRelatedSentenceRow(array $row): string
{
    $count = (int)($row['related_links_count'] ?? 0);
    $note = trim((string)($row['related_links_note'] ?? ''));
    if ($count <= 0) return 'اخبار مرتبط ندارد.';
    $sentence = numberToPersianWords($count) . " ({$count}) اخبار مرتبط دارد.";
    return $note !== '' ? "{$sentence} {$note}" : $sentence;
}

function buildAddonSentenceRow(array $row): string
{
    $count = (int)($row['addon_count'] ?? 0);
    $note = trim((string)($row['addon_note'] ?? ''));
    if ($count <= 0) return 'افزونه در خبر ندارد.';
    return $note !== '' ? "افزونه در خبر {$note} دارد." : 'افزونه در خبر دارد.';
}

function buildTagSentenceRow(array $row): string
{
    $count = (int)($row['tag_count'] ?? 0);
    $tags = trim((string)($row['tag'] ?? ''));
    $note = trim((string)($row['tag_note'] ?? ''));
    if ($count <= 0) return 'برچسبی برای این خبر ثبت نشده است.';
    $sentence = numberToPersianWords($count) . ' برچسب دارد' . ($tags !== '' ? " ({$tags})" : '') . '.';
    return $note !== '' ? "{$sentence} {$note}" : $sentence;
}

function buildDailyNarrative(array $rows, string $reporter, string $from, string $to = ''): array
{
    if ($to === '') { $to = $from; }
    $isRange = $from !== $to;
    $department = mostCommonService($rows) ?: '-';

    $paragraphs = [];
    $paragraphs[] = ['text' => "نام خبرنگار: {$reporter}      اداره: {$department}", 'bold' => true];
    $paragraphs[] = ['text' => $isRange ? (jalaliDateLabel($from) . ' تا ' . jalaliDateLabel($to)) : jalaliDateLabel($from), 'bold' => true];

    $count = count($rows);
    $breakdown = buildTypeBreakdownSentence($rows);
    $paragraphs[] = ['text' => "در مجموع " . numberToPersianWords($count) . " ({$count}) خبر به نام خبرنگار منتشر شده است که از نظر نوع عبارتند از: {$breakdown}", 'bold' => true];
    $paragraphs[] = '';

    foreach ($rows as $i => $row) {
        $paragraphs[] = ['text' => buildMainSentence($row, $i), 'bold' => true];
        $paragraphs[] = ['text' => buildDescriptionSentenceRow($row), 'bold' => true];
        $paragraphs[] = ['text' => buildElementsSentenceRow($row), 'bold' => true];
        $paragraphs[] = ['text' => buildRelatedSentenceRow($row), 'bold' => true];
        $paragraphs[] = ['text' => buildAddonSentenceRow($row), 'bold' => true];
        $paragraphs[] = ['text' => buildTagSentenceRow($row), 'bold' => true];
        $paragraphs[] = '';
    }

    $tableHeaders = ['ردیف'];
    if ($isRange) { $tableHeaders[] = 'تاریخ'; }
    $tableHeaders = array_merge($tableHeaders, [
        'ناشر', 'آدرس', 'تیتر', 'سوژه', 'نوع خبر / نوع واقعی خبر', 'عناصر خبری', 'توضیح کلی',
        'اخبار مرتبط', 'برچسب', 'افزونه',
    ]);

    $tableRows = [];
    foreach ($rows as $i => $row) {
        $line = [$i + 1];
        if ($isRange) { $line[] = $row['entry_date'] ?? ''; }

        $link = trim((string)($row['news_link'] ?? ''));
        $title = trim((string)($row['title'] ?? ''));
        $titleCell = $link !== '' ? ['text' => $title, 'link' => $link] : $title;

        $rt = classifyRealNewsType((string)($row['real_news_type'] ?? ''));
        $nt = trim((string)($row['news_type'] ?? ''));
        $typeCombined = trim($nt . ($rt !== '' ? " / {$rt}" : '')) ?: '-';

        $interviewee = trim((string)($row['interviewee'] ?? ''));
        $elements = trim((string)($row['news_elements'] ?? ''));
        $elementsCombined = trim(($interviewee !== '' ? "مصاحبه‌شونده: {$interviewee}" : '') .
            ($interviewee !== '' && $elements !== '' ? ' - ' : '') . $elements) ?: '-';

        $relCount = (int)($row['related_links_count'] ?? 0);
        $relNote = trim((string)($row['related_links_note'] ?? ''));
        $relatedCombined = $relCount > 0 ? ($relCount . ($relNote !== '' ? " - {$relNote}" : '')) : 'ندارد';

        $tagCount = (int)($row['tag_count'] ?? 0);
        $tags = trim((string)($row['tag'] ?? ''));
        $tagNote = trim((string)($row['tag_note'] ?? ''));
        $tagCombined = $tagCount > 0 ? (($tags !== '' ? $tags : (string)$tagCount) . ($tagNote !== '' ? " | {$tagNote}" : '')) : 'ندارد';

        $addonCount = (int)($row['addon_count'] ?? 0);
        $addonNote = trim((string)($row['addon_note'] ?? ''));
        $addonCombined = $addonCount > 0 ? ($addonNote !== '' ? $addonNote : 'دارد') : 'ندارد';

        $descCombined = trim((string)($row['description'] ?? '')) ?: '-';

        $tableRows[] = array_merge($line, [
            $row['publisher'] ?? '',
            $link,
            $titleCell,
            $row['subject'] ?? '',
            $typeCombined,
            $elementsCombined,
            $descCombined,
            $relatedCombined,
            $tagCombined,
            $addonCombined,
        ]);
    }

    return ['paragraphs' => $paragraphs, 'table_headers' => $tableHeaders, 'table_rows' => $tableRows];
}
