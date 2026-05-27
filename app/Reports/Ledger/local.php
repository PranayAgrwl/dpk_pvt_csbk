<?php
/**
 * Report: Local Parties - Ledger Summary
 * ------------------------------------------------------------
 * THIS FILE IS THE PDF LAYOUT. Manipulate it freely — change fonts,
 * widths, spacing, add/remove sections. The controller (LedgerController)
 * only gathers data and `require`s this file; no rendering logic lives
 * over there.
 *
 * This is a twin of /app/Reports/Ledger/regular.php — same layout, same
 * spec, only the title in Header() differs. Keep them in sync if you want
 * the two reports to stay visually identical, or edit each independently
 * to give them different looks.
 *
 * Locals provided by the caller (LedgerController::printLocal()):
 *   $positives   array<int, {name:string, balance:float}>   alphabetical
 *   $negatives   array<int, {name:string, balance:float}>   alphabetical
 *   $posSum      float                                       sum of positives
 *   $negSum      float                                       sum of negatives
 *   $grand       float                                       posSum + negSum
 *   $generated   string                                      e.g. "26/05/2026"
 *
 * Layout spec (per bible /z_pranay/personal_notes/6_ledger_printout_1.txt):
 *   - Margins: 7.5mm L / R / T
 *   - Font: Courier everywhere
 *   - No grey allowed — strictly black on white
 *   - Top-left:  "LOCAL PARTIES - LEDGER SUMMARY"     (Courier-B 12)
 *   - Top-right: "GENERATE: <today's date>"           (Courier-B 12)
 *   - Section heading: "POSITIVE BALANCE <count>"     (Courier-B 10)
 *   - Column header + body rows:                      (Courier 9)
 *       # column     7 mm  left-align
 *       Name       160 mm  left-align
 *       Amount      28 mm  right-align
 *   - Same treatment for the negative section, subtotals, grand total.
 *
 * Page width math: A4 portrait (210mm) − 7.5mm margins × 2 = 195mm usable.
 *                  Columns sum: 7 + 160 + 28 = 195mm. Exact fit.
 * ------------------------------------------------------------
 */

require_once APP_BASE . '/lib/fpdf/fpdf.php';

// ====================================================================
//   1) FPDF subclass — only here for Header() (auto-redrawn each page).
// ====================================================================
// Defined as an anonymous class so this file can be safely `require`d
// more than once in the same request without a "class already declared"
// fatal. Constructor args are forwarded to FPDF::__construct().
$pdf = new class('P', 'mm', 'A4') extends \FPDF {

    public string $generated = '';

    /**
     * Auto-drawn at the top of every page (incl. the first AddPage()).
     * Two cells on the same line: title (left) and "GENERATE: <date>" (right).
     */
    public function Header(): void
    {
        $this->SetFont('Courier', 'B', 12);
        // Page usable width = 195mm. Split: 130 (left) + 65 (right).
        $this->Cell(130, 6, 'LOCAL PARTIES - LEDGER SUMMARY', 0, 0, 'L');
        $this->Cell( 65, 6, 'GENERATE: ' . $this->generated,  0, 1, 'R');
        $this->Ln(4);   // small breathing room before the body
    }

    /**
     * Footer: page X / N in plain black Courier. The bible doesn't mention
     * one, but a tiny page counter is useful when the list spans multiple
     * pages. Comment out the body if you want a footer-less PDF.
     */
    public function Footer(): void
    {
        $this->SetY(-10);
        $this->SetFont('Courier', '', 8);
        $this->Cell(0, 6, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
    }
};

$pdf->generated = $generated;
$pdf->AliasNbPages();
$pdf->SetMargins(7.5, 7.5, 7.5);
$pdf->SetAutoPageBreak(true, 12);   // leave 12mm at the bottom for the footer
$pdf->AddPage();

// ====================================================================
//   2) Column widths (must sum to <= 195mm).
// ====================================================================
$colNum   = 7;       // "#" column
$colName  = 160;     // "Name" column
$colBal   = 28;      // "Amount" column
// Convenience: width of (# + Name) for right-aligning the "Subtotal" /
// "GRAND TOTAL" label so the number lines up under the Amount column.
$colLabel = $colNum + $colName;   // 167mm

// ====================================================================
//   3) Tiny helpers.
// ====================================================================

// Money formatting: 1,234.56 (no currency symbol).
$fmt = static fn(float $n): string => number_format($n, 2, '.', ',');

// FPDF core fonts only support cp1252. Master names in this app are upper-
// case English, so the conversion is normally a no-op. Kept as a guard so
// any unexpected unicode never crashes the page.
$safe = static function (string $s): string {
    if (function_exists('iconv')) {
        $r = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
        if ($r !== false) return $r;
    }
    return (string) preg_replace('/[^\x20-\x7E]/', '?', $s);
};

// ====================================================================
//   4) Render one balance section (title + headers + body + subtotal).
//      Used identically for positives and negatives — "same tone for
//      everything" per the bible.
// ====================================================================
$renderSection = static function (
    \FPDF $pdf, string $title, array $rows, float $sum
) use ($fmt, $safe, $colNum, $colName, $colBal, $colLabel): void {

    // ---- 4a) Section heading: "POSITIVE BALANCE (<count>)" (Courier-B 10)
    $pdf->SetFont('Courier', 'B', 10);
    $pdf->Cell(0, 6, $title . ' (' . count($rows) . ')', 0, 1, 'L');

    // ---- 4b) Column header row (Courier-B 9, bordered underline)
    $pdf->SetFont('Courier', 'B', 9);
    $pdf->Cell($colNum,  5, '#',      'B', 0, 'L');
    $pdf->Cell($colName, 5, 'Name',   'B', 0, 'L');
    $pdf->Cell($colBal,  5, 'Amount', 'B', 1, 'R');

    // ---- 4c) Body
    if (empty($rows)) {
        $pdf->SetFont('Courier', 'B', 9);
        $pdf->Cell(0, 5, '(none)', 0, 1, 'L');
    } else {
        $pdf->SetFont('Courier', 'B', 9);
        $i = 0;
        foreach ($rows as $r) {
            $i++;
            $pdf->Cell($colNum,  5, (string) $i,                      0, 0, 'L');
            $pdf->Cell($colName, 5, $safe((string) $r['name']),       0, 0, 'L');
            $pdf->Cell($colBal,  5, $fmt((float)  $r['balance']),     0, 1, 'R');
        }
    }

    // ---- 4d) Subtotal: top-border above the totals row (classic ledger look)
    $pdf->SetFont('Courier', 'B', 9);
    $pdf->Cell($colLabel, 5, 'Subtotal', 'T', 0, 'R');
    $pdf->Cell($colBal,   5, $fmt($sum), 'T', 1, 'R');

    // ---- 4e) Vertical breathing room before the next section
    $pdf->Ln(3);
};

// ====================================================================
//   5) Render the two sections.
// ====================================================================
$renderSection($pdf, 'POSITIVE BALANCE', $positives, $posSum);
$renderSection($pdf, 'NEGATIVE BALANCE', $negatives, $negSum);

// ====================================================================
//   6) Grand total — black horizontal rule then the totals row.
// ====================================================================
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(0.3);
$y = $pdf->GetY();
$pdf->Line(7.5, $y, 202.5, $y);    // full usable-width black line
$pdf->Ln(1);

$pdf->SetFont('Courier', 'B', 10);
$pdf->Cell($colLabel, 6, 'GRAND TOTAL', 0, 0, 'R');
$pdf->Cell($colBal,   6, $fmt($grand), 0, 1, 'R');

// ====================================================================
//   7) Send the PDF inline (Content-Disposition: inline). The /ledger
//      button uses target="_blank" so this lands in a new tab and the
//      browser's PDF viewer renders it.
// ====================================================================
$pdf->Output('I', 'ledger-local-' . date('Ymd') . '.pdf');
