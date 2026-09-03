<?php

namespace App\Services\Exports;

use App\Models\Payment;
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

        try {
            $archive = new ZipArchive;
            $openResult = $archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($openResult !== true) {
                throw new RuntimeException('Unable to open the Excel workbook archive.');
            }

            try {
                foreach ($this->workbookFiles($payments) as $fileName => $contents) {
                    if ($archive->addFromString($fileName, $contents) === false) {
                        throw new RuntimeException('Unable to add a file to the Excel workbook.');
                    }
                }
            } finally {
                $archive->close();
            }

            $workbook = file_get_contents($temporaryPath);
            if ($workbook === false) {
                throw new RuntimeException('Unable to read the generated Excel workbook.');
            }

            return $workbook;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array<string, string>
     */
    private function workbookFiles(Collection $payments): array
    {
        return [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelationshipsXml(),
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
</Types>
XML;
    }

    private function rootRelationshipsXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function workbookXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <fileVersion appName="xl" lastEdited="1" lowestEdited="1" rupBuild="1"/>
    <workbookPr defaultThemeVersion="124226"/>
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
    <numFmts count="0"/>
    <fonts count="2">
        <font><sz val="11"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>
        <font><b/><sz val="11"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>
    </fonts>
    <fills count="2">
        <fill><patternFill patternType="none"/></fill>
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
        <xf numFmtId="0" fontId="1" fillId="1" borderId="0" applyAlignment="1" xfId="0"><alignment horizontal="center"/></xf>
    </cellXfs>
    <cellStyles count="1">
        <cellStyle name="Normal" xfId="0" builtinId="0"/>
    </cellStyles>
    <dxfs count="0"/>
    <tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleMedium9"/>
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

                $value = htmlspecialchars($cell['value'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells[] = '<c r="'.$cellReference.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$value.'</t></is></c>';
            }

            $xmlRows[] = '<row r="'.$excelRow.'">'.implode('', $cells).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:J'.$lastRow.'"/>'
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
            .'</cols>'
            .'<sheetData>'.implode('', $xmlRows).'</sheetData>'
            .'<autoFilter ref="A1:J'.$lastRow.'"/>'
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
}
