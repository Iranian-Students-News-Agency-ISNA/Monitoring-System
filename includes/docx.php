<?php
// نوشتن ساده فایل Word (.docx) بدون هیچ کتابخانه خارجی (فقط ZipArchive)

function docxEscape(string $s): string
{
    // داده‌های قدیمی (به‌خصوص متن‌های آزاد طولانی مثل «عناصر خبری» و «توضیح کلی») گاهی
    // بایت‌های نامعتبر UTF-8 یا کاراکترهای کنترلی غیرمجاز در XML دارند. htmlspecialchars()
    // با ورودی UTF-8 نامعتبر رشته خالی برمی‌گرداند و همین باعث می‌شد آن سلول در Word کاملاً خالی شود.
    // اینجا ابتدا بایت‌های نامعتبر پاک/اصلاح می‌شوند و کاراکترهای کنترلی غیرمجاز حذف می‌شوند.
    $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? $s;
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function docxParagraphXml(string $text, bool $bold = false, string $align = 'right', int $size = 22): string
{
    $rpr = '<w:rPr><w:rFonts w:cs="B Nazanin"/><w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/>' .
           ($bold ? '<w:b/><w:bCs/>' : '') . '<w:rtl/></w:rPr>';
    return '<w:p><w:pPr><w:bidi/><w:jc w:val="' . $align . '"/><w:rPr>' . '</w:rPr></w:pPr>' .
           '<w:r>' . $rpr . '<w:t xml:space="preserve">' . docxEscape($text) . '</w:t></w:r></w:p>';
}

// $cell می‌تواند رشته باشد یا آرایه ['text'=>..,'link'=>URL] برای سلول‌های لینک‌دار
function docxTableCellXml($cell, bool $header = false, ?array &$relCounter = null, ?array &$rels = null): string
{
    $text = is_array($cell) ? (string)($cell['text'] ?? '') : (string)$cell;
    $link = is_array($cell) ? trim((string)($cell['link'] ?? '')) : '';

    $shd = $header ? '<w:shd w:val="clear" w:color="auto" w:fill="D9E2F3"/>' : '';
    $rpr = '<w:rPr><w:rFonts w:cs="B Nazanin"/><w:sz w:val="20"/>' . ($header ? '<w:b/>' : '') .
           ($link !== '' ? '<w:color w:val="1F5AA8"/><w:u w:val="single"/>' : '') . '<w:rtl/></w:rPr>';

    if ($link !== '' && $relCounter !== null && $rels !== null) {
        if (!preg_match('#^https?://#i', $link)) { $link = 'https://' . $link; }
        $relCounter['n']++;
        $rId = 'rId' . (100 + $relCounter['n']); // فضای شماره جدا از rId1 اصلی
        $rels[] = ['id' => $rId, 'target' => $link];
        $run = '<w:hyperlink r:id="' . $rId . '"><w:r>' . $rpr . '<w:t xml:space="preserve">' . docxEscape($text) . '</w:t></w:r></w:hyperlink>';
    } else {
        $run = '<w:r>' . $rpr . '<w:t xml:space="preserve">' . docxEscape($text) . '</w:t></w:r>';
    }

    return '<w:tc><w:tcPr><w:tcBorders>' .
           '<w:top w:val="single" w:sz="4" w:color="999999"/>' .
           '<w:left w:val="single" w:sz="4" w:color="999999"/>' .
           '<w:bottom w:val="single" w:sz="4" w:color="999999"/>' .
           '<w:right w:val="single" w:sz="4" w:color="999999"/>' .
           '</w:tcBorders>' . $shd . '</w:tcPr>' .
           '<w:p><w:pPr><w:bidi/><w:jc w:val="center"/></w:pPr>' . $run . '</w:p></w:tc>';
}

function docxTableXml(array $headers, array $rows, ?array &$relCounter = null, ?array &$rels = null): string
{
    $colCount = count($headers);
    $gridCols = '';
    for ($i = 0; $i < $colCount; $i++) { $gridCols .= '<w:gridCol w:w="' . intdiv(9000, $colCount) . '"/>'; }

    $headerRow = '<w:tr>';
    foreach ($headers as $h) { $headerRow .= docxTableCellXml((string)$h, true); }
    $headerRow .= '</w:tr>';

    $bodyRows = '';
    foreach ($rows as $row) {
        $bodyRows .= '<w:tr>';
        foreach ($row as $cell) { $bodyRows .= docxTableCellXml($cell, false, $relCounter, $rels); }
        $bodyRows .= '</w:tr>';
    }

    return '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:bidiVisual/>' .
           '<w:tblW w:w="9000" w:type="dxa"/></w:tblPr>' .
           '<w:tblGrid>' . $gridCols . '</w:tblGrid>' .
           $headerRow . $bodyRows . '</w:tbl>';
}

function docxWriteSimple(string $path, array $paragraphs, array $tableHeaders, array $tableRows): void
{
    if (file_exists($path)) @unlink($path);
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('امکان ساخت فایل Word نبود.');
    }

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>' .
        '</Types>');

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>' .
        '</Relationships>');

    $relCounter = ['n' => 0];
    $rels = [];

    $body = '';
    foreach ($paragraphs as $p) {
        if (is_array($p)) {
            $body .= docxParagraphXml($p['text'] ?? '', (bool)($p['bold'] ?? false), $p['align'] ?? 'right', $p['size'] ?? 22);
        } else {
            $body .= docxParagraphXml((string)$p);
        }
    }
    if (!empty($tableHeaders)) {
        $body .= docxTableXml($tableHeaders, $tableRows, $relCounter, $rels);
        $body .= docxParagraphXml('');
    }

    $sectPr = '<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>' .
              '<w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="708" w:footer="708" w:gutter="0"/>' .
              '<w:bidi/></w:sectPr>';

    $zip->addFromString('word/document.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" ' .
        'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<w:body>' . $body . $sectPr . '</w:body></w:document>');

    if (!empty($rels)) {
        $relXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                  '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($rels as $r) {
            $relXml .= '<Relationship Id="' . $r['id'] . '" ' .
                       'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" ' .
                       'Target="' . docxEscape($r['target']) . '" TargetMode="External"/>';
        }
        $relXml .= '</Relationships>';
        $zip->addFromString('word/_rels/document.xml.rels', $relXml);
    }

    $zip->close();
}
