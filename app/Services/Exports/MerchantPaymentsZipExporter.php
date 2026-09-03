<?php

namespace App\Services\Exports;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

final class MerchantPaymentsZipExporter
{
    public function __construct(private MerchantPaymentsExcelExporter $excelExporter) {}

    /**
     * @param  array<int, array{name: string, payments: Collection<int, Payment>}>  $merchantFiles
     */
    public function generate(array $merchantFiles): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'gatewayhub-merchant-payments-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary merchant archive.');
        }

        try {
            $archive = new ZipArchive;
            $openResult = $archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($openResult !== true) {
                throw new RuntimeException('Unable to open the merchant archive.');
            }

            try {
                if ($merchantFiles === []) {
                    $archive->addFromString('README.txt', "No transactions matched the selected filters.\n");
                }

                foreach ($merchantFiles as $merchantId => $merchantFile) {
                    $path = 'merchants/'.$this->folderName($merchantFile['name'], $merchantId).'/transactions.xlsx';
                    $workbook = $this->excelExporter->generate($merchantFile['payments']);

                    if ($archive->addFromString($path, $workbook) === false) {
                        throw new RuntimeException('Unable to add a merchant workbook to the archive.');
                    }
                }
            } finally {
                $archive->close();
            }

            $zip = file_get_contents($temporaryPath);
            if ($zip === false) {
                throw new RuntimeException('Unable to read the generated merchant archive.');
            }

            return $zip;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function folderName(string $merchantName, int $merchantId): string
    {
        $slug = Str::slug($merchantName);

        return ($slug === '' ? 'merchant' : $slug).'-'.$merchantId;
    }
}
