<?php declare(strict_types=1);

namespace Sprain\Tests\SwissQrBill\PaymentPart\Output\FpdfOutput;

use Fpdf\Traits\MemoryImageSupport\MemImageTrait;
use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Fpdi;
use Sprain\SwissQrBill\Exception\InvalidFpdfImageFormat;
use Sprain\SwissQrBill\PaymentPart\Output\FpdfOutput\FpdfOutput;
use Sprain\SwissQrBill\PaymentPart\Output\PrintOptions;
use Sprain\SwissQrBill\PaymentPart\Output\VerticalSeparatorSymbolPosition;
use Sprain\SwissQrBill\QrBill;
use Sprain\SwissQrBill\QrCode\QrCode;
use Sprain\Tests\SwissQrBill\TestQrBillCreatorTrait;
use Sprain\SwissQrBill\PaymentPart\Output\FpdfOutput\FpdfTrait;
use Sprain\SwissQrBill\PaymentPart\Output\FpdfOutput\MissingTraitException;

final class FpdiOutputTest extends TestCase
{
    use TestQrBillCreatorTrait;

    /**
     * @dataProvider validQrBillsProvider
     */
    public function testValidQrBills(string $name, QrBill $qrBill): void
    {
        $variations = [
            [
                'layout' => (new PrintOptions())->setPrintable(false),
                'format' => QrCode::FILE_FORMAT_PNG,
                'file' => dirname(dirname(dirname(__DIR__))) . '/TestData/FpdfOutput/' . $name . '.pdf'
            ],
            [
                'layout' => (new PrintOptions())->setPrintable(true),
                'format' => QrCode::FILE_FORMAT_PNG,
                'file' => dirname(dirname(dirname(__DIR__))) . '/TestData/FpdfOutput/' . $name . '.print.pdf'
            ],
            [
                'layout' => (new PrintOptions())->setPrintable(false)->setSeparatorSymbol(true),
                'format' => QrCode::FILE_FORMAT_PNG,
                'file' => dirname(dirname(dirname(__DIR__))) . '/TestData/FpdfOutput/' . $name . '.scissors.pdf'
            ],
            [
                'layout' => (new PrintOptions())->setPrintable(false)->setSeparatorSymbol(true)->setVerticalSeparatorSymbolPosition(VerticalSeparatorSymbolPosition::BOTTOM),
                'format' => QrCode::FILE_FORMAT_PNG,
                'file' => __DIR__ . '/../../../TestData/FpdfOutput/' . $name . '.svg.scissorsdown.pdf'
            ],
            [
                'layout' => (new PrintOptions())->setPrintable(false)->setText(true)->setTextDownArrows(true),
                'format' => QrCode::FILE_FORMAT_PNG,
                'file' => __DIR__ . '/../../../TestData/FpdfOutput/' . $name . '.svg.textarrows.pdf'
            ]
        ];

        foreach ($variations as $variation) {
            $file = $variation['file'];

            $fpdf = $this->instantiateFpdi();
            $fpdf->AddPage();

            $output = new FpdfOutput($qrBill, 'en', $fpdf);
            $output
                ->setPrintOptions($variation['layout'])
                ->setQrCodeImageFormat($variation['format'])
                ->getPaymentPart();

            if ($this->regenerateReferenceFiles) {
                $fpdf->Output($file, 'F');
            }

            $contents = $this->getActualPdfContents($fpdf->Output($file, 'S'));

            $this->assertNotNull($contents);
            $this->assertSame($this->getActualPdfContents(file_get_contents($file)), $contents);
        }
    }

    public function testItThrowsMissingTraitException(): void
    {
        $this->expectException(MissingTraitException::class);

        $qrBill = $this->createQrBill([
            'header',
            'creditorInformationQrIban',
            'creditor',
            'paymentAmountInformation',
            'paymentReferenceQr'
        ]);

        $fpdf = $this->instantiateFpdi(null, false);
        $fpdf->AddPage();

        $output = new FpdfOutput($qrBill, 'en', $fpdf);
        $output
            ->setPrintOptions((new PrintOptions())->setPrintable(false)->setSeparatorSymbol(true))
            ->setQrCodeImageFormat(QrCode::FILE_FORMAT_PNG)
            ->getPaymentPart();
    }

    public function testItThrowsSvgNotSupportedException(): void
    {
        $this->expectException(InvalidFpdfImageFormat::class);

        $qrBill = $this->createQrBill([
            'header',
            'creditorInformationQrIban',
            'creditor',
            'paymentAmountInformation',
            'paymentReferenceQr'
        ]);

        $fpdf = $this->instantiateFpdi();
        $fpdf->AddPage();

        $output = new FpdfOutput($qrBill, 'en', $fpdf);
        $output
            ->setQrCodeImageFormat(QrCode::FILE_FORMAT_SVG)
            ->getPaymentPart();
    }

    private function getActualPdfContents(string $fileContents): ?string
    {
        // Extract actual pdf content and ignore all meta data which may differ in different versions of Fpdf
        $pattern = '/stream(.*?)endstream/s';
        preg_match($pattern, $fileContents, $matches);

        if (isset($matches[1])) {
            return $matches[1];
        }
        return null;
    }

    private function instantiateFpdi($withMemImageSupport = null, $withBundledTrait = null): Fpdi
    {
        if ($withMemImageSupport === null) {
            $withMemImageSupport = !ini_get('allow_url_fopen');
        }

        if ($withBundledTrait === null) {
            $withBundledTrait = true;
        }

        if ($withMemImageSupport) {
            if ($withBundledTrait) {
                return new class('P', 'mm', 'A4') extends Fpdi {
                    use MemImageTrait;
                    use FpdfTrait;
                };
            }
            return new class('P', 'mm', 'A4') extends Fpdi {
                use MemImageTrait;
            };
        }

        if ($withBundledTrait) {
            return new class('P', 'mm', 'A4') extends Fpdi {
                use FpdfTrait;
            };
        }

        return new Fpdi('P', 'mm', 'A4');
    }
}
