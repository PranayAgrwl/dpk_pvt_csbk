<?php
/**
 * Report: Party-wise Ledger
 * ------------------------------------------------------------
 * THIS FILE IS THE PDF LAYOUT. Manipulate it freely — change fonts,
 * widths, spacing, add/remove sections. The controller (LedgerController)
 * only gathers data and `require`s this file.
 *
 * Sibling of /app/Reports/Ledger/regular.php and local.php. Same visual
 * spec (Courier, 7.5mm margins, no grey, black on white) but the layout
 * is a per-master transaction list instead of a positive/negative summary.
 *
 * Locals provided by the caller (LedgerController::printParty()):
 *   $partyName      string   master's name (already uppercase from save-time)
 *   $partyStation   string   master's station, '' when none
 *   $partyBalance   float    current balance (SUM(dr) - SUM(cr))
 *   $balanceStr     string   $partyBalance pre-formatted (e.g. "-13,999.00")
 *   $rows           array<int,{trx_id,trx_date,cr,dr,remark,running_balance}>
 *                            sorted ASC by trx_id (per bible: "ascending on vno")
 *   $sumDr          float    sum of all dr values
 *   $sumCr          float    sum of all cr values
 *   $closing        float    closing balance (= sumDr - sumCr = last row's running)
 *   $generated      string   today's date e.g. "27/05/2026"
 *
 * Layout spec (per bible /z_pranay/personal_notes/8_party_wise_ledger_printout.txt):
 *   - "similar simple layout like ledger printout"
 *   - "no fanacy colours" (sic)  →  black on white, Courier everywhere
 *   - "ascending on vno"          →  $rows already sorted ASC by trx_id server-side
 *   - 7.5mm margins L/R/T (matches the other ledger reports)
 *
 * Column layout (6 cols, sum = 195mm = page width minus 2x7.5mm margins):
 *   VNO       10mm  right-align   ("vno" = trx_id, max 5 digits)
 *   DATE      22mm  left-align    (DD/MM/YYYY)
 *   REMARK    65mm  left-align    (truncated with "~" suffix if too long)
 *   DR        28mm  right-align   (blank when row is a credit-only line)
 *   CR        28mm  right-align   (blank when row is a debit-only line)
 *   BALANCE   42mm  right-align   (running balance through this row)
 * ------------------------------------------------------------
 */

require_once APP_BASE . '/lib/fpdf/fpdf.php';

// ====================================================================
//   1) FPDF subclass — Header() (party banner + info line + col headers)
//      is auto-redrawn at the top of every page so multi-page printouts
//      stay legible. Footer() draws "Page X / N" along the bottom.
// ====================================================================
$pdf = new class('P', 'mm', 'A4') extends \FPDF {

    public string $partyName    = '';
    public string $partyStation = '';
    public string $balanceStr   = '';
    public int    $trxCount     = 0;
    public string $generated    = '';

    public function Header(): void
    {
        // ---- Top banner: "PARTY LEDGER - <NAME>" (L) | "GENERATE: <date>" (R)
        $this->SetFont('Courier', 'B', 12);
        $this->Cell(130, 6, 'PARTY LEDGER - ' . $this->partyName, 0, 1, 'L');
        // $this->Cell(130, 6, 'PARTY LEDGER - ' . $this->partyName, 0, 0, 'L');
        // $this->Cell( 65, 6, 'GENERATE: ' . $this->generated,      0, 1, 'R');

        // ---- Info line (Courier 9): station | balance | total trx count
        $this->SetFont('Courier', '', 9);
        $station = $this->partyStation !== '' ? $this->partyStation : '-';
        $info    = 'STATION: '          . $station
                 . '  |  CURRENT BALANCE: ' . $this->balanceStr
                 . '  |  TOTAL TRX: '       . $this->trxCount;
        // $this->Cell(0, 5, $info, 0, 1, 'L');
        $this->Ln(2);

        // ---- Column headers (Courier-B 9, bordered underline)
        $this->SetFont('Courier', 'B', 9);
        // $this->Cell(10, 5, 'VNO',     'B', 0, 'L');
        $this->Cell(22, 5, 'DATE',    'B', 0, 'L');
        $this->Cell(30, 5, 'CR',      'B', 0, 'R');
        $this->Cell(30, 5, 'DR',      'B', 0, 'R');
        $this->Cell(35, 5, 'BALANCE', 'B', 0, 'R');
        $this->Cell(8, 5, '', 'B', 0, 'R');
        $this->Cell(70, 5, 'REMARK',  'B', 1, 'L');
    }

    public function Footer(): void
    {
        // $this->SetY(-10);
        // $this->SetFont('Courier', 'B', 8);
        // $this->Cell(0, 6, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
    }
};

$pdf->partyName    = $partyName;
$pdf->partyStation = $partyStation;
$pdf->balanceStr   = $balanceStr;
$pdf->trxCount     = count($rows);
$pdf->generated    = $generated;
$pdf->AliasNbPages();
$pdf->SetMargins(7.5, 7.5, 7.5);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();

// ====================================================================
//   2) Column widths (must match the values in Header() above).
// ====================================================================
$colVno  = 10;

$colDate = 22;
$colCr   = 30;
$colDr   = 30;
$colBal  = 35;
$colBlank  = 8;
$colRmk  = 70;

// ====================================================================
//   3) Tiny helpers.
// ====================================================================

// Money formatting: 1,234.56 (no currency symbol).
$fmt = static fn(float $n): string => number_format($n, 2, '.', ',');

// "" for null/zero, otherwise formatted money. Used so dr/cr columns show
// blank when the row only has the other side filled.
$fmtMoneyOrBlank = static function ($n) use ($fmt): string {
    if ($n === null) return '';
    $f = (float) $n;
    return $f == 0.0 ? '' : $fmt($f);
};

// FPDF core fonts only support cp1252 — defensive convert.
$safe = static function (string $s): string {
    if (function_exists('iconv')) {
        $r = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
        if ($r !== false) return $r;
    }
    return (string) preg_replace('/[^\x20-\x7E]/', '?', $s);
};

// YYYY-MM-DD -> DD/MM/YYYY. Falls through unchanged if the input is anything else.
$fmtDate = static function (string $iso): string {
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }
    return $iso;
};

// Truncate a remark so it stays inside the 65mm column.
// Courier 9pt ≈ 1.9mm per character → 65mm fits ~34 chars; leave 1 for the
// cell's internal 1mm padding and 1 for the truncation marker.
$truncate = static function (string $s, int $max = 33): string {
    if (mb_strlen($s) <= $max) return $s;
    return mb_substr($s, 0, max(0, $max - 1)) . '~';
};

// ====================================================================
//   4) Body — one row per transaction (already sorted ASC by trx_id).
// ====================================================================
if (empty($rows)) {
    $pdf->SetFont('Courier', 'B', 9);
    $pdf->Cell(0, 5, '(no transactions for this party)', 0, 1, 'L');
} else {
    $pdf->SetFont('Courier', 'B', 11);
    foreach ($rows as $r) {
        // $pdf->Cell($colVno,  5, (string) (int) $r['trx_id'],          0, 0, 'L');
        $pdf->Cell($colDate, 5, $fmtDate((string) $r['trx_date']),    0, 0, 'L');
        $pdf->Cell($colCr,   5, $fmtMoneyOrBlank($r['cr']),           0, 0, 'R');
        $pdf->Cell($colDr,   5, $fmtMoneyOrBlank($r['dr']),           0, 0, 'R');
        $pdf->Cell($colBal,  5, $fmt((float) $r['running_balance']),  0, 0, 'R');
        $pdf->Cell($colBlank,  5, '',  0, 0, 'R');
        $pdf->Cell($colRmk,  5, $safe($truncate((string) ($r['remark'] ?? ''))), 0, 1, 'L');
    }
}

// ====================================================================
//   5) Totals row + closing balance — only rendered when there's data.
// ====================================================================
if (!empty($rows)) {
    // ---- 5a) TOTAL row: sum of Dr + sum of Cr, with a top-border sum line
    //          drawn just under the Dr/Cr/Balance cells (classic ledger look).
    $pdf->Ln(2);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.3);
    $y = $pdf->GetY();
    $pdf->Line(7.5, $y, 202.5, $y);
    $pdf->Ln(1);

    $pdf->SetFont('Courier', 'B', 11);
    $pdf->Cell($colDate, 5, 'TOTAL',     0,   0, 'R');
    $pdf->Cell($colDr,                       5, $fmt($sumDr), 0, 0, 'R');
    $pdf->Cell($colCr,                       5, $fmt($sumCr), 0, 0, 'R');
    $pdf->Cell($colBal,                      5, $fmt($closing), 0, 1, 'R');

    // ---- 5b) CLOSING BALANCE — full-width black rule then the bold totals row.
    $pdf->Ln(2);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.3);
    $y = $pdf->GetY();
    $pdf->Line(7.5, $y, 202.5, $y);
    $pdf->Ln(1);

    // $pdf->SetFont('Courier', 'B', 10);
    // $pdf->Cell($colVno + $colDate + $colRmk + $colDr + $colCr, 6, 'CLOSING BALANCE', 0, 0, 'R');
    // $pdf->Cell($colBal,                                        6, $fmt($closing),    0, 1, 'R');
}

// ====================================================================
//   6) Output inline so the browser opens it in the new tab (the button
//      on /ledger/{id} uses target="_blank"). Filename includes a slug
//      of the party name and today's date.
// ====================================================================
$slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $partyName));
$slug = trim($slug, '-');
$pdf->Output('I', 'ledger-' . ($slug !== '' ? $slug : 'party') . '-' . date('Ymd') . '.pdf');
