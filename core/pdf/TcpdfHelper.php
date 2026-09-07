<?php

require_once __DIR__ . '/../../repo/SystemSettings.model.php';

require_once __DIR__ . '/../../vendor/TCPDF-6.7.4/tcpdf.php';

function spms_pdf_init(string $title, string $orientation = 'P', string $paper = 'A4'): TCPDF
{
    $settings = SystemSettings::getSettings();
    $orgName = $settings['organization_name'] ?? 'Cotabato State University';

    $pdf = new TCPDF($orientation, 'mm', $paper, true, 'UTF-8', false);
    $pdf->SetCreator($orgName . ' SMS');
    $pdf->SetAuthor($orgName);
    $pdf->SetTitle($title);
    $pdf->SetSubject($title);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(12, 16, 12);
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->SetFont('dejavusans', '', 9);

    return $pdf;
}

function spms_pdf_render(TCPDF $pdf, string $html): void
{
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');
}

function spms_pdf_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function spms_pdf_format_date($value, string $format = 'F d, Y', string $fallback = ''): string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return $fallback;
    }

    $timestamp = strtotime($raw);
    if ($timestamp === false) {
        return $fallback;
    }

    return date($format, $timestamp);
}

function spms_pdf_filename(string $prefix, string $reference): string
{
    $safeReference = preg_replace('/[^A-Za-z0-9\\-]/', '', (string)$reference);
    if ($safeReference === null || $safeReference === '') {
        $safeReference = 'document';
    }

    return $prefix . '-' . $safeReference . '.pdf';
}

function spms_pdf_output(TCPDF $pdf, string $filename): void
{
    while (ob_get_level() > 0) {
        $status = ob_get_status();
        $flags = (int)($status['flags'] ?? 0);

        $isRemovable = ($flags & PHP_OUTPUT_HANDLER_REMOVABLE) === PHP_OUTPUT_HANDLER_REMOVABLE;
        if ($isRemovable) {
            ob_end_clean();
            continue;
        }

        $isCleanable = ($flags & PHP_OUTPUT_HANDLER_CLEANABLE) === PHP_OUTPUT_HANDLER_CLEANABLE;
        if ($isCleanable) {
            ob_clean();
        }
        break;
    }

    $pdf->Output($filename, 'I');
    exit;
}
