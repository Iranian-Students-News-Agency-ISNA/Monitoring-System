<?php
// خواندن و نوشتن ساده فایل‌های .xlsx بدون هیچ کتابخانه خارجی (فقط ZipArchive + SimpleXML)

function xlsxReadRows(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('امکان باز کردن فایل اکسل نبود.');
    }

    // پیدا کردن مسیر شیت اول از روی workbook.xml + رابطه‌ها
    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wbXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wbXml !== false && $relsXml !== false) {
        $wb = @simplexml_load_string($wbXml);
        $rels = @simplexml_load_string($relsXml);
        if ($wb && $rels) {
            $firstSheet = $wb->sheets->sheet[0] ?? null;
            if ($firstSheet) {
                $rid = (string)$firstSheet->attributes('r', true)->id;
                foreach ($rels->Relationship as $rel) {
                    if ((string)$rel['Id'] === $rid) {
                        $target = (string)$rel['Target'];
                        $sheetPath = 'xl/' . ltrim($target, '/');
                        break;
                    }
                }
            }
        }
    }

    // رشته‌های اشتراکی
    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml !== false) {
        $sx = @simplexml_load_string($sharedXml);
        if ($sx) {
            foreach ($sx->si as $si) {
                if (isset($si->t)) {
                    $shared[] = (string)$si->t;
                } else {
                    $txt = '';
                    foreach ($si->r as $r) { $txt .= (string)$r->t; }
                    $shared[] = $txt;
                }
            }
        }
    }

    $sheetXml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($sheetXml === false) return [];

    $xml = @simplexml_load_string($sheetXml);
    if (!$xml) return [];

    $rows = [];
    foreach ($xml->sheetData->row as $rowNode) {
        $cells = [];
        $maxCol = -1;
        $tmp = [];
        foreach ($rowNode->c as $c) {
            $ref = (string)$c['r'];
            $col = xlsxColIndexFromRef($ref);
            $type = (string)$c['t'];
            $val = '';
            if ($type === 's') {
                $idx = (int)$c->v;
                $val = $shared[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $val = (string)($c->is->t ?? '');
            } elseif ($type === 'str' || $type === 'b') {
                $val = (string)$c->v;
            } else {
                $val = isset($c->v) ? (string)$c->v : '';
            }
            $tmp[$col] = $val;
            if ($col > $maxCol) $maxCol = $col;
        }
        for ($i = 0; $i <= $maxCol; $i++) { $cells[$i] = $tmp[$i] ?? ''; }
        $rows[] = $cells;
    }
    return $rows;
}

function xlsxColIndexFromRef(string $ref): int
{
    if (!preg_match('/^([A-Z]+)/', $ref, $m)) return 0;
    $letters = $m[1];
    $col = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $col = $col * 26 + (ord($letters[$i]) - 64);
    }
    return $col - 1;
}

function xlsxColRefFromIndex(int $idx): string
{
    $idx++;
    $ref = '';
    while ($idx > 0) {
        $rem = ($idx - 1) % 26;
        $ref = chr(65 + $rem) . $ref;
        $idx = intdiv($idx - 1, 26);
    }
    return $ref;
}

function xlsxEscape(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// نوشتن یک فایل اکسل ساده با یک شیت (هدر + ردیف‌های داده)
function xlsxWriteSimple(string $path, array $headers, array $rows, string $sheetName = 'Sheet1'): void
{
    if (file_exists($path)) @unlink($path);
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('امکان ساخت فایل اکسل نبود.');
    }

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
        '</Types>');

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>');

    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
        '</Relationships>');

    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ' .
        'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets><sheet name="' . xlsxEscape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>' .
        '</workbook>');

    $zip->addFromString('xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>' .
        '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>' .
        '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>' .
        '</styleSheet>');

    $allRows = array_merge([$headers], $rows);
    $sheetData = '';
    foreach ($allRows as $ri => $row) {
        $rowNum = $ri + 1;
        $sheetData .= '<row r="' . $rowNum . '">';
        foreach (array_values($row) as $ci => $val) {
            $ref = xlsxColRefFromIndex($ci) . $rowNum;
            $str = (string)$val;
            if ($str !== '' && preg_match('/^-?\d+$/', $str) && strlen($str) < 15) {
                $sheetData .= '<c r="' . $ref . '"><v>' . xlsxEscape($str) . '</v></c>';
            } else {
                $sheetData .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . xlsxEscape($str) . '</t></is></c>';
            }
        }
        $sheetData .= '</row>';
    }

    $zip->addFromString('xl/worksheets/sheet1.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<sheetData>' . $sheetData . '</sheetData>' .
        '</worksheet>');

    $zip->close();
}

// اکسل ماتریس (هیت‌مپ) راست‌به‌چپ با همان رنگ‌بندی سبز صفحهٔ وب
// $cols: عنوان ستون‌ها، $rows: [[نام موضوع, [مقادیر به ترتیب ستون‌ها]], ...]
function xlsxWriteMatrix(string $path, string $sheetName, array $cols, array $rows): void
{
    if (file_exists($path)) @unlink($path);
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('امکان ساخت فایل اکسل نبود.');
    }
    $max = 0;
    foreach ($rows as $r) foreach ($r[1] as $v) $max = max($max, (int)$v);

    // hsl(142,60%,L) -> hex
    $hsl = function (int $l): string {
        $h = 142 / 360; $s = 0.60; $L = $l / 100;
        $q = $L < 0.5 ? $L * (1 + $s) : $L + $s - $L * $s; $p = 2 * $L - $q;
        $f = function ($t) use ($p, $q) {
            if ($t < 0) $t += 1; if ($t > 1) $t -= 1;
            if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
            if ($t < 1 / 2) return $q;
            if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
            return $p;
        };
        return strtoupper(sprintf('%02x%02x%02x', round($f($h + 1 / 3) * 255), round($f($h) * 255), round($f($h - 1 / 3) * 255)));
    };

    // سبک‌ها: 0 پیش‌فرض، 1 سربرگ ستون، 2 سربرگ موضوع، 3 خانهٔ خالی، 4+ هر سطح روشنایی
    $fills = ['<fill><patternFill patternType="none"/></fill>', '<fill><patternFill patternType="gray125"/></fill>'];
    $xfs = [
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>',
        '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1" readingOrder="2"/></xf>',
        '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center" readingOrder="2"/></xf>',
        '<xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>',
    ];
    $topicXf = 4;
    $xfs[] = '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center" readingOrder="2"/></xf>';
    $lvl = [];
    $styleFor = function (int $l) use (&$lvl, &$fills, &$xfs, $hsl): int {
        if (isset($lvl[$l])) return $lvl[$l];
        $fills[] = '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $hsl($l) . '"/><bgColor indexed="64"/></patternFill></fill>';
        $xfs[] = '<xf numFmtId="3" fontId="' . ($l < 55 ? 2 : 3) . '" fillId="' . (count($fills) - 1) . '" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>';
        return $lvl[$l] = count($xfs) - 1;
    };

    $sd = '<row r="1" ht="24" customHeight="1"><c r="A1" s="2" t="inlineStr"><is><t>موضوع</t></is></c>';
    foreach ($cols as $ci => $c) {
        $sd .= '<c r="' . xlsxColRefFromIndex($ci + 1) . '1" s="1" t="inlineStr"><is><t xml:space="preserve">' . xlsxEscape((string)$c) . '</t></is></c>';
    }
    $sd .= '</row>';
    foreach ($rows as $ri => $r) {
        $n = $ri + 2;
        $sd .= '<row r="' . $n . '" ht="21" customHeight="1"><c r="A' . $n . '" s="' . $topicXf . '" t="inlineStr"><is><t xml:space="preserve">' . xlsxEscape((string)$r[0]) . '</t></is></c>';
        foreach ($r[1] as $ci => $v) {
            $ref = xlsxColRefFromIndex($ci + 1) . $n;
            $v = (int)$v;
            if ($v <= 0 || $max <= 0) {
                $sd .= '<c r="' . $ref . '" s="3" t="inlineStr"><is><t>–</t></is></c>';
            } else {
                $l = 94 - (int)round(($v / $max) * 62);
                $sd .= '<c r="' . $ref . '" s="' . $styleFor($l) . '"><v>' . $v . '</v></c>';
            }
        }
        $sd .= '</row>';
    }

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets><sheet name="' . xlsxEscape($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="5">' .
        '<font><sz val="11"/><name val="Tahoma"/></font>' .
        '<font><b/><sz val="11"/><color rgb="FF212529"/><name val="Tahoma"/></font>' .
        '<font><sz val="11"/><color rgb="FFFFFFFF"/><name val="Tahoma"/></font>' .
        '<font><sz val="11"/><color rgb="FF12351F"/><name val="Tahoma"/></font>' .
        '<font><sz val="11"/><color rgb="FF6C757D"/><name val="Tahoma"/></font></fonts>' .
        '<fills count="' . count($fills) . '">' . implode('', $fills) . '</fills>' .
        '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' .
        '<border><left style="thin"><color rgb="FFDEE2E6"/></left><right style="thin"><color rgb="FFDEE2E6"/></right><top style="thin"><color rgb="FFDEE2E6"/></top><bottom style="thin"><color rgb="FFDEE2E6"/></bottom><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="' . count($xfs) . '">' . implode('', $xfs) . '</cellXfs></styleSheet>');
    $colsXml = '<col min="1" max="1" width="34" customWidth="1"/><col min="2" max="' . (count($cols) + 1) . '" width="16" customWidth="1"/>';
    $zip->addFromString('xl/worksheets/sheet1.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<sheetViews><sheetView rightToLeft="1" showGridLines="0" workbookViewId="0"><pane xSplit="1" ySplit="1" topLeftCell="B2" activePane="bottomRight" state="frozen"/></sheetView></sheetViews>' .
        '<sheetFormatPr defaultRowHeight="18"/><cols>' . $colsXml . '</cols><sheetData>' . $sd . '</sheetData></worksheet>');
    $zip->close();
}
