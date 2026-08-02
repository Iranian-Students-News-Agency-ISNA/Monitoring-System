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
