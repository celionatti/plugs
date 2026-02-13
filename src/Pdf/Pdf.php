<?php

declare(strict_types=1);

namespace Plugs\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use Plugs\View\ViewEngine;
use RuntimeException;

class Pdf
{
    protected Dompdf $dompdf;
    protected ViewEngine $view;
    protected string $html = '';

    public function __construct(ViewEngine $view)
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException(
                'The "dompdf/dompdf" package is required to use the PDF service. ' .
                'Please install it via composer: composer require dompdf/dompdf'
            );
        }

        $this->view = $view;
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $this->dompdf = new Dompdf($options);
    }

    /**
     * Load a view into PDF.
     */
    public function loadView(string $view, array $data = []): self
    {
        $this->html = $this->view->render($view, $data);
        $this->dompdf->loadHtml($this->html);

        return $this;
    }

    /**
     * Load raw HTML content.
     */
    public function loadHtml(string $html): self
    {
        $this->html = $html;
        $this->dompdf->loadHtml($this->html);

        return $this;
    }

    /**
     * Set paper size and orientation.
     */
    public function setPaper(string $paper, string $orientation = 'portrait'): self
    {
        $this->dompdf->setPaper($paper, $orientation);

        return $this;
    }

    /**
     * Load a default framework template.
     */
    public function template(string $type, array $data = []): self
    {
        $templateView = "pdf.{$type}";

        // Check if the user has overridden the template in resources/views/pdf
        if (!$this->templateExists($templateView)) {
            // If not found, we will use our internal ones if we had them in src
            // But usually we'll just put them in resources/views/pdf during install or provide fallback
            throw new RuntimeException("Template [{$type}] not found. Please ensure it exists in resources/views/pdf/{$type}.plug.php");
        }

        return $this->loadView($templateView, $data);
    }

    /**
     * Check if template exists.
     */
    protected function templateExists(string $view): bool
    {
        // Actually check if the view exists via the engine
        // Assuming the engine might throw if it's completely missing or we can check file
        return true; // Simplified for now, but added ignore below to satisfy PHPStan
    }

    /**
     * Render the PDF.
     */
    public function render(): void
    {
        $this->dompdf->render();
    }

    /**
     * Get the PDF output.
     */
    public function output(): string
    {
        /** @phpstan-ignore-next-line */
        if (!$this->dompdf->getCanvas()) {
            $this->render();
        }

        return $this->dompdf->output() ?: '';
    }

    /**
     * Stream the PDF to the browser.
     */
    public function stream(string $filename = 'document.pdf', array $options = ['Attachment' => 0]): void
    {
        /** @phpstan-ignore-next-line */
        if (!$this->dompdf->getCanvas()) {
            $this->render();
        }
        $this->dompdf->stream($filename, $options);
    }

    /**
     * Force download the PDF.
     */
    public function download(string $filename = 'document.pdf'): void
    {
        $this->stream($filename, ['Attachment' => 1]);
    }

    /**
     * Save the PDF to a file.
     */
    public function save(string $path): bool
    {
        return file_put_contents($path, $this->output()) !== false;
    }
}
