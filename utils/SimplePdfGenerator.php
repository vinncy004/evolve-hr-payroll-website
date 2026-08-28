<?php
/**
 * Lightweight, dependency-free PDF generator for payslip exports.
 * Generates a minimal valid PDF document suitable for download.
 */
class SimplePdfGenerator {
    public static function fromLines(array $lines, string $title = 'Document'): string {
        $lines = array_values($lines);
        $contentLines = [];
        $y = 760;

        foreach ($lines as $index => $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $contentLines[] = sprintf('BT /F1 11 Tf 50 %.0f Td (%s) Tj ET', $y, $escaped);
            $y -= 16;
            if ($y < 40) {
                $y = 760;
            }
        }

        $content = implode("\n", $contentLines);
        $content = str_replace("\r", '', $content);

        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $index + 1, $object);
        }

        $xrefStart = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n";
        $pdf .= "<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n";
        $pdf .= $xrefStart . "\n";
        $pdf .= "%%EOF";

        return $pdf;
    }
}
?>
