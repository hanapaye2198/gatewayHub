<?php

namespace Tests\Unit\Services\Exports;

use App\Models\Payment;
use App\Services\Exports\MerchantPaymentsExcelExporter;
use Illuminate\Database\Eloquent\Collection;
use SimpleXMLElement;
use Tests\TestCase;
use ZipArchive;

class MerchantPaymentsExcelExporterTest extends TestCase
{
    public function test_generated_workbook_uses_microsoft_excel_compatible_zip_headers(): void
    {
        $workbook = $this->generateWorkbook();

        $this->assertStringStartsWith('PK', $workbook);

        $endOfCentralDirectory = strrpos($workbook, "PK\x05\x06");
        $this->assertNotFalse($endOfCentralDirectory);

        $centralDirectoryOffset = unpack('V', substr($workbook, $endOfCentralDirectory + 16, 4));
        $this->assertIsArray($centralDirectoryOffset);

        $this->assertSame("PK\x01\x02", substr($workbook, $centralDirectoryOffset[1], 4));
        $this->assertSame(20, ord($workbook[$centralDirectoryOffset[1] + 4]));
        $this->assertSame(0, ord($workbook[$centralDirectoryOffset[1] + 5]));
    }

    public function test_generated_workbook_includes_excel_required_parts_and_reserved_fills(): void
    {
        $entries = $this->zipEntries($this->generateWorkbook());

        $this->assertArrayHasKey('[Content_Types].xml', $entries);
        $this->assertArrayHasKey('_rels/.rels', $entries);
        $this->assertArrayHasKey('docProps/core.xml', $entries);
        $this->assertArrayHasKey('docProps/app.xml', $entries);
        $this->assertArrayHasKey('xl/workbook.xml', $entries);
        $this->assertArrayHasKey('xl/styles.xml', $entries);
        $this->assertArrayHasKey('xl/worksheets/sheet1.xml', $entries);

        $this->assertStringContainsString('patternType="none"', $entries['xl/styles.xml']);
        $this->assertStringContainsString('patternType="gray125"', $entries['xl/styles.xml']);
        $this->assertStringNotContainsString('scheme val="minor"', $entries['xl/styles.xml']);
        $this->assertStringNotContainsString('defaultThemeVersion', $entries['xl/workbook.xml']);
    }

    public function test_generated_workbook_xml_parts_are_well_formed(): void
    {
        foreach ($this->zipEntries($this->generateWorkbook()) as $name => $contents) {
            if (! str_ends_with($name, '.xml') && ! str_ends_with($name, '.rels')) {
                continue;
            }

            $xml = simplexml_load_string($contents);
            $this->assertInstanceOf(SimpleXMLElement::class, $xml, $name.' is not well-formed XML.');
        }
    }

    public function test_generated_workbook_escapes_xml_and_strips_illegal_control_characters(): void
    {
        $payment = new Payment([
            'reference_id' => 'A&B <C>',
            'provider_reference' => "ok\x01bad",
            'gateway_code' => 'gcash',
            'amount' => 10.5,
            'currency' => 'PHP',
            'status' => 'paid',
        ]);
        $payment->setRelation('gateway', null);
        $payment->setRelation('platformFee', null);

        $entries = $this->zipEntries(
            (new MerchantPaymentsExcelExporter)->generate(new Collection([$payment]))
        );
        $worksheet = $entries['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('A&amp;B &lt;C&gt;', $worksheet);
        $this->assertStringNotContainsString('A&B <C>', $worksheet);
        $this->assertStringContainsString('okbad', $worksheet);
        $this->assertStringNotContainsString("ok\x01bad", $worksheet);

        $xml = simplexml_load_string($worksheet);
        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
    }

    public function test_generated_workbook_includes_display_status_for_expired_provider_evidence(): void
    {
        $payment = new Payment([
            'reference_id' => 'DISP-EXPIRED-001',
            'provider_reference' => 'PROV-EXPIRED',
            'gateway_code' => 'coins',
            'amount' => 10,
            'currency' => 'PHP',
            'status' => 'failed',
            'raw_response' => ['status' => 'EXPIRED'],
        ]);
        $payment->setRelation('gateway', null);
        $payment->setRelation('platformFee', null);
        $payment->setRelation('webhookEvents', collect());

        $entries = $this->zipEntries(
            (new MerchantPaymentsExcelExporter)->generate(new Collection([$payment]))
        );
        $worksheet = $entries['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('Display Status', $worksheet);
        $this->assertStringContainsString('Expired', $worksheet);
        $this->assertStringContainsString('failed', $worksheet);
    }

    private function generateWorkbook(): string
    {
        return (new MerchantPaymentsExcelExporter)->generate(new Collection);
    }

    /**
     * @return array<string, string>
     */
    private function zipEntries(string $workbook): array
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'gatewayhub-excel-test-');
        $this->assertNotFalse($temporaryPath);

        try {
            $this->assertNotFalse(file_put_contents($temporaryPath, $workbook));

            $archive = new ZipArchive;
            $this->assertTrue($archive->open($temporaryPath) === true);

            $entries = [];
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);
                $this->assertIsString($name);
                $contents = $archive->getFromIndex($index);
                $this->assertIsString($contents);
                $entries[$name] = $contents;
            }

            $archive->close();

            return $entries;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
