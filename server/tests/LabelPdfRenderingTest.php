<?php

use Fleetbase\FleetOps\Support\LabelDocument;
use Fleetbase\FleetOps\Support\LabelPdf;
use Illuminate\Container\Container;
use Illuminate\Http\Response;

class FleetOpsLabelPdfDompdfFake
{
    public array $options = [];

    public function set_option(string $key, mixed $value): void
    {
        $this->options[$key] = $value;
    }
}

class FleetOpsLabelPdfWrapperFake
{
    public array $loadedHtml = [];
    public array $options    = [];

    public FleetOpsLabelPdfDompdfFake $dompdf;

    public function __construct(public string $bytes = "%PDF-1.7\n% dompdf fake\n%%EOF")
    {
        $this->dompdf = new FleetOpsLabelPdfDompdfFake();
    }

    public function loadHTML(string $html, ?string $encoding = null): self
    {
        $this->loadedHtml[] = [$html, $encoding];

        return $this;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getDomPDF(): FleetOpsLabelPdfDompdfFake
    {
        return $this->dompdf;
    }

    public function output(): string
    {
        return $this->bytes;
    }
}

function fleetOpsLabelPdfUseDompdfFake(?FleetOpsLabelPdfWrapperFake $wrapper = null): FleetOpsLabelPdfWrapperFake
{
    $wrapper ??= new FleetOpsLabelPdfWrapperFake();

    Container::getInstance()->instance('dompdf.wrapper', $wrapper);
    Barryvdh\DomPDF\Facade\Pdf::clearResolvedInstance('dompdf.wrapper');

    return $wrapper;
}

/**
 * Chromium binary used by the Browsershot test, or null when this environment cannot run it.
 */
function fleetOpsLabelPdfChromium(): ?string
{
    $settings = LabelPdf::browsershotSettings();
    $chrome   = $settings['chrome_path'] ?? null;

    foreach (array_filter([$chrome, '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome']) as $candidate) {
        if (is_file($candidate) && is_executable($candidate)) {
            $chrome = $candidate;
            break;
        }
        $chrome = null;
    }

    $hasNode      = trim((string) @shell_exec('command -v node 2>/dev/null')) !== '';
    $nodeModules  = $settings['node_modules_path'] ?? null;
    $hasPuppeteer = $nodeModules ? is_dir(rtrim($nodeModules, '/') . '/puppeteer') : trim((string) @shell_exec('node -e "require.resolve(\'puppeteer\')" 2>/dev/null && echo ok')) === 'ok';

    if (!$chrome || !$hasNode || !$hasPuppeteer || !class_exists(Spatie\Browsershot\Browsershot::class)) {
        return null;
    }

    return $chrome;
}

afterEach(function () {
    config(['fleetops.labels.pdf_renderer' => null]);
});

test('labels render through the shared label pdf renderer with thai fonts first', function () {
    $labelPdf = file_get_contents(dirname(__DIR__) . '/src/Support/LabelPdf.php');
    $order    = file_get_contents(dirname(__DIR__) . '/src/Models/Order.php');
    $waypoint = file_get_contents(dirname(__DIR__) . '/src/Models/Waypoint.php');
    $entity   = file_get_contents(dirname(__DIR__) . '/src/Models/Entity.php');
    $default  = file_get_contents(dirname(__DIR__) . '/resources/views/labels/default.php');
    $stop     = file_get_contents(dirname(__DIR__) . '/resources/views/labels/waypoint-label.php');
    $utils    = file_get_contents(dirname(__DIR__) . '/src/Support/Utils.php');

    expect($labelPdf)
        ->toContain('new PdfBuilder()')
        ->toContain("Pdf::loadHTML(\$html, 'UTF-8')")
        ->toContain("'defaultFont'             => 'DejaVu Sans'")
        ->and($order)->toContain('LabelPdf::fromHtml($this->label())')
        ->and($waypoint)->toContain('LabelPdf::fromHtml($this->label())')
        ->and($entity)->toContain('LabelPdf::fromHtml($this->label())')
        ->and($default)->toContain('font-family: "Sarabun", "Noto Sans Thai Looped", "DejaVu Sans", "Noto Sans", "Noto Sans Arabic"')
        ->toContain('line-height: 1.5;')
        ->not->toContain('strtoupper($company->name)')
        ->and($stop)->toContain('font-family: "Sarabun", "Noto Sans Thai Looped", "DejaVu Sans"')
        ->toContain('Noto Sans CJK JP')
        ->toContain('line-height: 1.5;')
        ->not->toContain('strtoupper($company->name)')
        ->and($utils)->not->toContain('fixArabicInHTML');
});

test('label pdf returns a lazy label document', function () {
    $document = LabelPdf::fromHtml('<strong>สวัสดี</strong>');

    expect($document)->toBeInstanceOf(LabelDocument::class)
        ->and($document->html())->toBe('<strong>สวัสดี</strong>')
        ->and($document->renderedWith())->toBeNull();
});

test('label pdf renderer defaults to chromium and can be switched to dompdf', function () {
    config(['fleetops.labels.pdf_renderer' => null]);
    expect(LabelPdf::renderer())->toBe('chromium');

    config(['fleetops.labels.pdf_renderer' => ' DomPDF ']);
    expect(LabelPdf::renderer())->toBe('dompdf');

    config(['fleetops.labels.pdf_renderer' => 'something-else']);
    expect(LabelPdf::renderer())->toBe('chromium');
});

test('dompdf renderer loads html with utf8 and applies renderer options', function () {
    $wrapper = fleetOpsLabelPdfUseDompdfFake();

    $bytes = LabelPdf::renderWithDompdf('<strong>مرحبا</strong>');

    expect($bytes)->toBe($wrapper->bytes)
        ->and($wrapper->loadedHtml)->toBe([['<strong>مرحبا</strong>', 'UTF-8']])
        ->and($wrapper->options)->toBe(LabelPdf::options())
        ->and($wrapper->dompdf->options)->toBe(LabelPdf::dompdfOptions())
        ->and(LabelPdf::options())->toBe(LabelPdf::dompdfOptions());
});

test('label document output returns pdf bytes and stream returns an inline application/pdf response', function () {
    $calls    = 0;
    $document = new LabelDocument('<p>ทดสอบ</p>', function (string $html) use (&$calls) {
        $calls++;

        return "%PDF-1.7\n" . $html;
    });

    $output   = $document->output();
    $response = $document->stream('label.pdf');

    expect($output)->toStartWith('%PDF')
        ->and($document->renderedWith())->toBe('chromium')
        ->and($calls)->toBe(1)
        ->and($response)->toBeInstanceOf(Response::class)
        ->and($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toBe('inline; filename=label.pdf')
        ->and($response->headers->get('Content-Length'))->toBe((string) strlen($output))
        ->and($response->getContent())->toBe($output)
        ->and($document->download()->headers->get('Content-Disposition'))->toBe('attachment; filename=document.pdf');
});

test('label document falls back to dompdf when chromium throws', function () {
    $document = new LabelDocument(
        '<p>fallback</p>',
        fn () => throw new RuntimeException('chromium is not installed'),
        fn (string $html) => "%PDF-1.4\ndompdf " . $html,
    );

    expect($document->output())->toBe("%PDF-1.4\ndompdf <p>fallback</p>")
        ->and($document->renderedWith())->toBe('dompdf')
        ->and($document->stream()->headers->get('Content-Type'))->toBe('application/pdf');
});

test('label document falls back to dompdf when chromium returns something that is not a pdf', function () {
    $document = new LabelDocument('<p>x</p>', fn () => '', fn () => '%PDF-1.4 dompdf');

    expect($document->output())->toBe('%PDF-1.4 dompdf')
        ->and($document->renderedWith())->toBe('dompdf');
});

test('label document skips chromium when the renderer is dompdf', function () {
    config(['fleetops.labels.pdf_renderer' => 'dompdf']);

    $document = new LabelDocument('<p>x</p>', fn () => throw new LogicException('chromium must not run'), fn () => '%PDF-1.4 dompdf');

    expect($document->output())->toBe('%PDF-1.4 dompdf')
        ->and($document->renderedWith())->toBe('dompdf');
});

test('label document uses the dompdf wrapper as the default fallback renderer', function () {
    $wrapper  = fleetOpsLabelPdfUseDompdfFake();
    $document = new LabelDocument('<p>x</p>', fn () => throw new RuntimeException('no chromium'));

    expect($document->output())->toBe($wrapper->bytes)
        ->and($wrapper->loadedHtml)->toBe([['<p>x</p>', 'UTF-8']]);
});

test('browsershot settings come from laravel-pdf config and fall back to LARAVEL_PDF env vars', function () {
    $previous = [
        'LARAVEL_PDF_CHROME_PATH'       => getenv('LARAVEL_PDF_CHROME_PATH'),
        'LARAVEL_PDF_NODE_MODULES_PATH' => getenv('LARAVEL_PDF_NODE_MODULES_PATH'),
        'LARAVEL_PDF_NO_SANDBOX'        => getenv('LARAVEL_PDF_NO_SANDBOX'),
    ];

    try {
        config(['laravel-pdf.browsershot' => []]);
        putenv('LARAVEL_PDF_CHROME_PATH=/env/chromium');
        putenv('LARAVEL_PDF_NODE_MODULES_PATH=/env/node_modules');
        putenv('LARAVEL_PDF_NO_SANDBOX=true');
        unset($_SERVER['LARAVEL_PDF_CHROME_PATH'], $_SERVER['LARAVEL_PDF_NODE_MODULES_PATH'], $_SERVER['LARAVEL_PDF_NO_SANDBOX']);
        unset($_ENV['LARAVEL_PDF_CHROME_PATH'], $_ENV['LARAVEL_PDF_NODE_MODULES_PATH'], $_ENV['LARAVEL_PDF_NO_SANDBOX']);

        expect(LabelPdf::browsershotSettings())->toBe([
            'chrome_path'       => '/env/chromium',
            'node_modules_path' => '/env/node_modules',
            'no_sandbox'        => true,
        ]);

        config(['laravel-pdf.browsershot' => [
            'chrome_path'       => '/config/chromium',
            'node_modules_path' => '/config/node_modules',
            'no_sandbox'        => true,
        ]]);

        expect(LabelPdf::browsershotSettings())->toBe([
            'chrome_path'       => '/config/chromium',
            'node_modules_path' => '/config/node_modules',
            'no_sandbox'        => true,
        ]);

        config(['laravel-pdf.browsershot' => []]);
        putenv('LARAVEL_PDF_CHROME_PATH');
        putenv('LARAVEL_PDF_NODE_MODULES_PATH');
        putenv('LARAVEL_PDF_NO_SANDBOX');

        expect(LabelPdf::browsershotSettings())->toBe([
            'chrome_path'       => null,
            'node_modules_path' => null,
            'no_sandbox'        => false,
        ]);
    } finally {
        foreach ($previous as $key => $value) {
            putenv($value === false ? $key : "{$key}={$value}");
        }
        config(['laravel-pdf.browsershot' => null]);
    }
});

test('browsershot is configured with backgrounds, css page size and the resolved binaries', function () {
    config(['laravel-pdf.browsershot' => [
        'chrome_path'       => '/opt/chromium',
        'node_modules_path' => '/opt/node_modules',
        'no_sandbox'        => true,
    ]]);

    try {
        $browsershot = LabelPdf::configureBrowsershot(Spatie\Browsershot\Browsershot::html('<p>x</p>'));
        $command     = $browsershot->createPdfCommand();

        expect($command['options']['printBackground'] ?? null)->toBeTrue()
            ->and($command['options']['preferCSSPageSize'] ?? null)->toBeTrue()
            ->and($command['options']['executablePath'] ?? null)->toBe('/opt/chromium')
            ->and($command['options']['args'] ?? [])->toContain('--no-sandbox');
    } finally {
        config(['laravel-pdf.browsershot' => null]);
    }
});

test('chromium renders a thai label to pdf through browsershot', function () {
    if (!fleetOpsLabelPdfChromium()) {
        $this->markTestSkipped('Chromium, node or puppeteer is not available for Browsershot.');
    }

    $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:"Sarabun",sans-serif;line-height:1.5}</style></head>'
        . '<body><p>ที่อยู่รับสินค้า: ๑๒๓ ถนนสุขุมวิท แขวงคลองเตยเหนือ เขตวัฒนา กรุงเทพมหานคร</p></body></html>';

    $document = new LabelDocument($html, null, fn () => throw new LogicException('DomPDF fallback must not be used: Chromium failed.'));
    $output   = $document->output();
    $response = $document->stream();

    expect($output)->toStartWith('%PDF')
        ->and($document->renderedWith())->toBe('chromium')
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->getContent())->toBe($output);
});
