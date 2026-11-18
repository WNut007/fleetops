<?php

namespace Fleetbase\FleetOps\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Enums\Unit;
use Spatie\LaravelPdf\PdfBuilder;

class LabelPdf
{
    /**
     * Page geometry the label templates were designed for. They declare no @page rule, so DomPDF
     * printed them on A4 portrait with its default 1.2cm page margin; Chromium reproduces that.
     * A template that declares its own @page size wins (preferCSSPageSize).
     */
    public const MARGIN_MM = 12;

    /**
     * Create a PDF document for FleetOps labels (order, waypoint and entity).
     *
     * The document renders through headless Chromium so complex scripts (Thai, Arabic, CJK) are
     * shaped with the fonts installed in the image, and falls back to DomPDF if Chromium fails.
     * It exposes output() (raw PDF bytes) and stream() (inline application/pdf response), the same
     * contract the DomPDF wrapper offered.
     */
    public static function fromHtml(string $html): LabelDocument
    {
        return new LabelDocument($html);
    }

    /**
     * The configured primary renderer: "chromium" (default) or "dompdf".
     */
    public static function renderer(): string
    {
        $renderer = strtolower(trim((string) config('fleetops.labels.pdf_renderer', 'chromium')));

        return $renderer === 'dompdf' ? 'dompdf' : 'chromium';
    }

    /**
     * Render HTML to PDF bytes with spatie/laravel-pdf (Browsershot + Chromium).
     */
    public static function renderWithChromium(string $html): string
    {
        $builder = (new PdfBuilder())
            ->html($html)
            ->format(Format::A4)
            ->margins(static::MARGIN_MM, static::MARGIN_MM, static::MARGIN_MM, static::MARGIN_MM, Unit::Millimeter)
            ->withBrowsershot(fn (Browsershot $browsershot) => static::configureBrowsershot($browsershot));

        return $builder->getBrowsershot()->pdf();
    }

    /**
     * Render HTML to PDF bytes with DomPDF, the renderer labels used before Chromium.
     */
    public static function renderWithDompdf(string $html): string
    {
        $pdf = Pdf::loadHTML($html, 'UTF-8');

        if (method_exists($pdf, 'setOptions')) {
            $pdf->setOptions(static::options());
        }

        if (method_exists($pdf, 'getDomPDF')) {
            $dompdf = $pdf->getDomPDF();
            foreach (static::dompdfOptions() as $key => $value) {
                if (method_exists($dompdf, 'set_option')) {
                    $dompdf->set_option($key, $value);
                }
            }
        }

        return (string) $pdf->output();
    }

    /**
     * Label-specific Browsershot settings. spatie/laravel-pdf already applies
     * config('laravel-pdf.browsershot.*'); the LARAVEL_PDF_* environment variables are applied here
     * as well for apps where that config is not loaded.
     */
    public static function configureBrowsershot(Browsershot $browsershot): Browsershot
    {
        $browsershot->showBackground();
        $browsershot->setOption('preferCSSPageSize', true);

        $settings = static::browsershotSettings();

        if ($settings['chrome_path']) {
            $browsershot->setChromePath($settings['chrome_path']);
        }

        if ($settings['node_modules_path']) {
            $browsershot->setNodeModulePath($settings['node_modules_path']);
        }

        if ($settings['no_sandbox']) {
            $browsershot->noSandbox();
        }

        return $browsershot;
    }

    /**
     * Chrome path, node_modules path and no-sandbox flag: laravel-pdf config first, then LARAVEL_PDF_* env.
     *
     * @return array{chrome_path: ?string, node_modules_path: ?string, no_sandbox: bool}
     */
    public static function browsershotSettings(): array
    {
        $chromePath      = config('laravel-pdf.browsershot.chrome_path') ?: static::env('LARAVEL_PDF_CHROME_PATH');
        $nodeModulesPath = config('laravel-pdf.browsershot.node_modules_path') ?: static::env('LARAVEL_PDF_NODE_MODULES_PATH');
        $noSandbox       = config('laravel-pdf.browsershot.no_sandbox') ?: static::env('LARAVEL_PDF_NO_SANDBOX');

        return [
            'chrome_path'       => $chromePath ?: null,
            'node_modules_path' => $nodeModulesPath ?: null,
            'no_sandbox'        => filter_var($noSandbox, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    public static function options(): array
    {
        return [
            'defaultFont'             => 'DejaVu Sans',
            'isHtml5ParserEnabled'    => true,
            'isFontSubsettingEnabled' => true,
            'isRemoteEnabled'         => false,
        ];
    }

    public static function dompdfOptions(): array
    {
        return [
            'defaultFont'             => 'DejaVu Sans',
            'isHtml5ParserEnabled'    => true,
            'isFontSubsettingEnabled' => true,
            'isRemoteEnabled'         => false,
        ];
    }

    protected static function env(string $key): ?string
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
