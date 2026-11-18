<?php

namespace Fleetbase\FleetOps\Support;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * A rendered FleetOps label. Keeps the subset of the DomPDF wrapper contract that label callers use:
 * output() returns the raw PDF bytes and stream() returns an inline application/pdf response.
 *
 * Rendering is lazy and happens once. Chromium is tried first (unless config
 * fleetops.labels.pdf_renderer is "dompdf"); if it throws or returns something that is not a PDF,
 * the reason is logged and DomPDF renders the label instead, so labels never hard-fail on Chromium.
 */
class LabelDocument
{
    protected ?string $bytes = null;

    protected ?string $renderedWith = null;

    /** @var callable(string): string */
    protected $chromiumRenderer;

    /** @var callable(string): string */
    protected $dompdfRenderer;

    /**
     * @param callable(string): string|null $chromiumRenderer defaults to LabelPdf::renderWithChromium()
     * @param callable(string): string|null $dompdfRenderer   defaults to LabelPdf::renderWithDompdf()
     */
    public function __construct(
        protected string $html,
        ?callable $chromiumRenderer = null,
        ?callable $dompdfRenderer = null,
    ) {
        $this->chromiumRenderer = $chromiumRenderer ?? [LabelPdf::class, 'renderWithChromium'];
        $this->dompdfRenderer   = $dompdfRenderer ?? [LabelPdf::class, 'renderWithDompdf'];
    }

    /**
     * Raw PDF bytes.
     */
    public function output(): string
    {
        if ($this->bytes === null) {
            $this->bytes = $this->render();
        }

        return $this->bytes;
    }

    /**
     * Inline PDF response, as DomPDF's stream() returned.
     */
    public function stream(string $filename = 'document.pdf'): Response
    {
        return $this->makeResponse('inline', $filename);
    }

    /**
     * Attachment PDF response, as DomPDF's download() returned.
     */
    public function download(string $filename = 'document.pdf'): Response
    {
        return $this->makeResponse('attachment', $filename);
    }

    /**
     * Write the PDF to a local path.
     */
    public function save(string $path): self
    {
        file_put_contents($path, $this->output());

        return $this;
    }

    public function html(): string
    {
        return $this->html;
    }

    /**
     * "chromium" or "dompdf" once rendered, null before.
     */
    public function renderedWith(): ?string
    {
        return $this->renderedWith;
    }

    protected function render(): string
    {
        if (LabelPdf::renderer() === 'chromium') {
            try {
                $bytes = (string) call_user_func($this->chromiumRenderer, $this->html);

                if (!str_starts_with($bytes, '%PDF')) {
                    throw new \RuntimeException('Chromium did not return PDF data (' . strlen($bytes) . ' bytes).');
                }

                $this->renderedWith = 'chromium';

                return $bytes;
            } catch (\Throwable $e) {
                Log::warning('FleetOps label: Chromium rendering failed, falling back to DomPDF. ' . $e->getMessage(), [
                    'exception' => get_class($e),
                ]);
            }
        }

        $this->renderedWith = 'dompdf';

        return (string) call_user_func($this->dompdfRenderer, $this->html);
    }

    protected function makeResponse(string $disposition, string $filename): Response
    {
        $output   = $this->output();
        $fallback = preg_replace('/[^\x20-\x7e]|[%\/\\\\]/', '', $filename) ?: 'document.pdf';

        return new Response($output, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $filename, $fallback),
            'Content-Length'      => strlen($output),
        ]);
    }
}
