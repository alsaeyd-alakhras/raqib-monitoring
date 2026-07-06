<?php

namespace App\Services;

use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Mpdf\Mpdf;

class BrandPdfService
{
    /**
     * Build a branded report: full-bleed cover page + normal body pages.
     *
     * @param  array   $cover  ['title','subtitle','eyebrow','author','date','version']
     * @param  string  $bodyView  blade view name for the body (@extends pdf.layout)
     * @param  array   $bodyData
     */
    public function report(array $cover, string $bodyView, array $bodyData = []): Mpdf
    {
        // 1) cover on its own zero-margin page
        $coverHtml = view('pdf.cover-runtime', $cover)->render();

        /** @var Mpdf $mpdf */
        $mpdf = PDF::loadHTML('')->getMpdf(); // start empty w/ config/pdf.php settings

        // cover page: no margins
        $mpdf->AddPageByArray([
            'margin-left' => 0, 'margin-right' => 0,
            'margin-top' => 0, 'margin-bottom' => 0, 'margin-footer' => 0,
        ]);
        $mpdf->WriteHTML($coverHtml);

        // 2) body pages: restore default margins + footer
        $bodyHtml = view($bodyView, $bodyData)->render();
        $mpdf->AddPageByArray([
            'margin-left' => 18, 'margin-right' => 18,
            'margin-top' => 22, 'margin-bottom' => 22, 'margin-footer' => 8,
        ]);
        $mpdf->WriteHTML($bodyHtml);

        return $mpdf;
    }

    /** Stream to browser */
    public function stream(array $cover, string $bodyView, array $bodyData = [], string $name = 'report.pdf')
    {
        return $this->report($cover, $bodyView, $bodyData)->Output($name, \Mpdf\Output\Destination::INLINE);
    }

    /** Save to storage, return path */
    public function save(array $cover, string $bodyView, array $bodyData, string $path): string
    {
        $this->report($cover, $bodyView, $bodyData)->Output(storage_path("app/{$path}"), \Mpdf\Output\Destination::FILE);
        return $path;
    }
}
