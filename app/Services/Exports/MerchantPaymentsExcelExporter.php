<?php

namespace App\Services\Exports;

use App\Models\Payment;
use App\Services\Payments\PaymentDisplayStatusResolver;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;
use ZipArchive;

final class MerchantPaymentsExcelExporter
{
    /**
     * @var list<string>
     */
    private const HEADERS = [
        'Created At',
        'Reference',
        'Provider Reference',
        'Gateway',
        'Gross Amount',
        'Currency',
        'GatewayHub Platform Fee (%)',
        'GatewayHub Platform Fee',
        'Net After GatewayHub Fee',
        'Status',
        'Display Status',
    ];

    /**
     * @param  Collection<int, Payment>  $payments
     */
    public function generate(Collection $payments): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'gatewayhub-payments-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary Excel workbook.');
        }

        $workbookPath = $temporaryPath.'.xlsx';

        try {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }

            $archive = new ZipArchive;
            $openResult = $archive->open($workbookPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($openResult !== true) {
                throw new RuntimeException('Unable to open the Excel workbook archive.');
            }

            try {
                foreach ($this->workbookFiles($payments) as $fileName => $contents) {
                    if ($archive->addFromString($fileName, $contents) === false) {
                        throw new RuntimeException('Unable to add a file to the Excel workbook.');
                    }

                    $archive->setCompressionName($fileName, ZipArchive::CM_DEFLATE);
                }
            } finally {
                $archive->close();
            }

            $workbook = file_get_contents($workbookPath);
            if ($workbook === false) {
                throw new RuntimeException('Unable to read the generated Excel workbook.');
            }

            return $this->makeZipCompatibleWithExcel($workbook);
        } finally {
            foreach ([$temporaryPath, $workbookPath] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array<string, string>
     */
    private function workbookFiles(Collection $payments): array
    {
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');

        return [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelationshipsXml(),
            'docProps/core.xml' => $this->corePropertiesXml($timestamp),
            'docProps/app.xml' => $this->appPropertiesXml(),
            'xl/workbook.xml' => $this->workbookXml(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationshipsXml(),
            'xl/styles.xml' => $this->stylesXml(),
            'xl/worksheets/sheet1.xml' => $this->worksheetXml($payments),
        ];
    }

    private function contentTypesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
    <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML;
    }

    private function rootRelationshipsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
XML;
    }

    private function corePropertiesXml(string $timestamp): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:creator>GatewayHub</dc:creator>
    <cp:lastModifiedBy>GatewayHub</cp:lastModifiedBy>
    <dcterms:created xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:created>
    <dcterms:modified xsi:type="dcterms:W3CDTF">{$timestamp}</dcterms:modified>
</cp:coreProperties>
XML;
    }

    private function appPropertiesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
    <Application>GatewayHub</Application>
</Properties>
XML;
    }

    private function workbookXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <workbookPr/>
    <bookViews>
        <workbookView xWindow="0" yWindow="0" windowWidth="20480" windowHeight="11905"/>
    </bookViews>
    <sheets>
        <sheet name="Transactions" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>
XML;
    }

    private function workbookRelationshipsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private function stylesXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="2">
        <font><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/></font>
        <font><b/><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/><family val="2"/></font>
    </fonts>
    <fills count="3">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFD9EAF7"/><bgColor indexed="64"/></patternFill></fill>
    </fills>
    <borders count="1">
        <border><left/><right/><top/><bottom/><diagonal/></border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="2">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center"/></xf>
    </cellXfs>
    <cellStyles count="1">
        <cellStyle name="Normal" xfId="0" builtinId="0"/>
    </cellStyles>
</styleSheet>
XML;
    }

    /**
     * @param  Collection<int, Payment>  $payments
     */
    private function worksheetXml(Collection $payments): string
    {
        $rows = [$this->headerRow()];
        foreach ($payments as $payment) {
            $rows[] = $this->paymentRow($payment);
        }

        $lastRow = count($rows);
        $xmlRows = [];
        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $cells = [];
            foreach ($row as $columnIndex => $cell) {
                $cellReference = $this->columnName($columnIndex + 1).$excelRow;
                $style = $excelRow === 1 ? ' s="1"' : '';

                if ($cell['type'] === 'n') {
                    $cells[] = '<c r="'.$cellReference.'" t="n"'.$style.'><v>'.$cell['value'].'</v></c>';

                    continue;
                }

                $cells[] = $this->inlineStringCell($cellReference, $style, $cell['value']);
            }

            $xmlRows[] = '<row r="'.$excelRow.'">'.implode('', $cells).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<dimension ref="A1:K'.$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols>'
            .'<col min="1" max="1" width="21" customWidth="1"/>'
            .'<col min="2" max="3" width="28" customWidth="1"/>'
            .'<col min="4" max="4" width="18" customWidth="1"/>'
            .'<col min="5" max="5" width="15" customWidth="1"/>'
            .'<col min="6" max="6" width="10" customWidth="1"/>'
            .'<col min="7" max="9" width="23" customWidth="1"/>'
            .'<col min="10" max="10" width="16" customWidth="1"/>'
            .'<col min="11" max="11" width="22" customWidth="1"/>'
            .'</cols>'
            .'<sheetData>'.implode('', $xmlRows).'</sheetData>'
            .'<autoFilter ref="A1:K'.$lastRow.'"/>'
            .'<pageMargins left="0.25" right="0.25" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            .'</worksheet>';
    }

    /**
     * @return list<array{value: string, type: 'inlineStr'|'n'}>
     */
    private function headerRow(): array
    {
        return array_map(fn (string $header): array => $this->textCell($header), self::HEADERS);
    }

    /**
     * @return list<array{value: string, type: 'inlineStr'|'n'}>
     */
    private function paymentRow(Payment $payment): array
    {
        $feeData = $payment->gatewayHubFeeData();

        return [
            $this->textCell($this->formatDate($payment->created_at)),
            $this->textCell($payment->reference_id),
            $this->textCell($payment->provider_reference),
            $this->textCell($payment->gateway?->name ?? $payment->gateway_code),
            $this->numberCell((float) $payment->amount),
            $this->textCell($payment->currency),
            $this->numberCell($feeData['gatewayhub_platform_fee_percent']),
            $this->nullableNumberCell($feeData['gatewayhub_platform_fee']),
            $this->nullableNumberCell($feeData['gatewayhub_net_amount']),
            $this->textCell($payment->status),
            $this->textCell((new PaymentDisplayStatusResolver)->resolve($payment)->label()),
        ];
    }

    /**
     * @return array{value: string, type: 'inlineStr'|'n'}
     */
    private function textCell(mixed $value): array
    {
        return [
            'value' => $value === null ? '' : (string) $value,
            'type' => 'inlineStr',
        ];
    }

    /**
     * @return array{value: string, type: 'inlineStr'|'n'}
     */
    private function numberCell(float $value): array
    {
        return [
            'value' => number_format($value, 2, '.', ''),
            'type' => 'n',
        ];
    }

    /**
     * @return array{value: string, type: 'inlineStr'|'n'}
     */
    private function nullableNumberCell(?float $value): array
    {
        return $value === null ? $this->textCell(null) : $this->numberCell($value);
    }

    private function inlineStringCell(string $cellReference, string $style, string $value): string
    {
        $space = $value !== trim($value) ? ' xml:space="preserve"' : '';

        return '<c r="'.$cellReference.'" t="inlineStr"'.$style.'><is><t'.$space.'>'.$this->xmlText($value).'</t></is></c>';
    }

    private function xmlText(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
        $converted = iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if ($converted !== false) {
            $value = $converted;
        }

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function formatDate(?DateTimeInterface $date): string
    {
        return $date?->format('Y-m-d H:i:s') ?? '';
    }

    private function columnName(int $columnNumber): string
    {
        $columnName = '';
        while ($columnNumber > 0) {
            $remainder = ($columnNumber - 1) % 26;
            $columnName = chr(65 + $remainder).$columnName;
            $columnNumber = intdiv($columnNumber - 1, 26);
        }

        return $columnName;
    }

    /**
     * PHP 8.2+ ZipArchive stamps central-directory entries as UNIX 6.3.
     * Microsoft Excel treats those archives as corrupt and refuses to open them.
     */
    private function makeZipCompatibleWithExcel(string $zip): string
    {
        $endOfCentralDirectory = strrpos($zip, "PK\x05\x06");
        if ($endOfCentralDirectory === false) {
            return $zip;
        }

        $centralDirectoryOffset = unpack('V', substr($zip, $endOfCentralDirectory + 16, 4));
        $entryCount = unpack('v', substr($zip, $endOfCentralDirectory + 10, 2));
        if ($centralDirectoryOffset === false || $entryCount === false) {
            return $zip;
        }

        $offset = $centralDirectoryOffset[1];
        $count = $entryCount[1];

        for ($index = 0; $index < $count; $index++) {
            if (substr($zip, $offset, 4) !== "PK\x01\x02") {
                break;
            }

            $zip[$offset + 4] = "\x14";
            $zip[$offset + 5] = "\x00";
            $zip = substr_replace($zip, pack('V', 0x20), $offset + 38, 4);

            $nameLength = unpack('v', substr($zip, $offset + 28, 2));
            $extraLength = unpack('v', substr($zip, $offset + 30, 2));
            $commentLength = unpack('v', substr($zip, $offset + 32, 2));
            if ($nameLength === false || $extraLength === false || $commentLength === false) {
                break;
            }

            $offset += 46 + $nameLength[1] + $extraLength[1] + $commentLength[1];
        }

        return $zip;
    }
}
