<?php
// ============================================================
// PDF GENERATOR — Professional auction item documents
// Clean, single-page design with hero image
// ============================================================

class ItemPDFGenerator {
    private $item;
    private $shortUrl;

    public function __construct($item) {
        $this->item = $item;
    }

    public function generate($shortUrl, $qrCodeUrl = null) {
        $this->shortUrl = $shortUrl;
        $html = $this->buildHTML();

        $filename = $this->getFilename();
        file_put_contents($filename, $html);

        return $filename;
    }

    private function buildHTML() {
        $item = $this->item;
        $startBid = '$' . number_format($item['starting_bid'], 2);
        $fmv = $item['fair_market_value'] ? '$' . number_format($item['fair_market_value'], 2) : 'N/A';
        $increment = '$' . number_format($item['min_increment'] ?? 5, 2);

        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOT HTML_START item_number - HTML_END</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 8.5in; height: 11in; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #fff; color: #1a1a1a; }

        .page { width: 100%; height: 100%; display: flex; flex-direction: column; padding: 0.25in; gap: 0.1in; }

        /* Hero Image - 55% of page */
        .hero { flex: 0 0 5.5in; background: #f8f8f8; border: 1px solid #ddd; border-radius: 3px; overflow: hidden; position: relative; }
        .hero img { width: 100%; height: 100%; object-fit: contain; padding: 0.1in; }
        .hero-badge { position: absolute; top: 0.1in; right: 0.1in; background: #2563eb; color: white; padding: 0.08in 0.12in; font-size: 11px; font-weight: 700; border-radius: 2px; }

        /* Bottom Section - 45% of page */
        .info { flex: 1; display: grid; grid-template-columns: 2fr 1fr; gap: 0.08in; }

        .details { display: flex; flex-direction: column; gap: 0.06in; font-size: 10px; }
        .title { font-size: 13px; font-weight: 700; line-height: 1.2; }
        .bid-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.04in; }
        .bid-box { background: #f3f4f6; padding: 0.05in 0.08in; border-radius: 2px; border-left: 2px solid #2563eb; }
        .bid-label { font-size: 7px; color: #666; text-transform: uppercase; font-weight: 600; }
        .bid-value { font-size: 11px; font-weight: 700; color: #1a1a1a; margin-top: 1px; }
        .url-box { background: #eff6ff; padding: 0.05in 0.08in; border-radius: 2px; border-left: 2px solid #2563eb; font-size: 8px; word-break: break-all; color: #2563eb; font-weight: 500; line-height: 1.3; }
        .cta { background: #dbeafe; padding: 0.04in 0.08in; border-radius: 2px; font-size: 8px; color: #1e40af; font-weight: 500; }

        .side-info { display: flex; flex-direction: column; gap: 0.05in; align-items: center; justify-content: flex-start; }
        .side-box { width: 100%; background: #f3f4f6; padding: 0.06in; border-radius: 2px; border: 1px solid #ddd; text-align: center; font-size: 8px; }
        .side-label { color: #666; font-weight: 600; text-transform: uppercase; margin-bottom: 2px; display: block; }
        .side-value { color: #1a1a1a; font-weight: 700; font-size: 10px; }

        .footer { text-align: center; font-size: 7px; color: #888; padding-top: 0.05in; border-top: 0.5px solid #ddd; }

        .print-btn { display: block; margin-bottom: 10px; padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 4px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .print-btn:hover { background: #1d4ed8; }

        @media print {
            body { margin: 0; padding: 0; }
            .print-btn { display: none !important; }
            .page { padding: 0.25in; }
            * { page-break-inside: avoid; }
            @page { margin: 0.25in; size: letter; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">🖨️ Print This Lot</button>

    <div class="page">
        <!-- Hero Image Section -->
        <div class="hero">
            <div class="hero-badge">LOT HTML_LOT_NUM</div>
            <img src="HTML_IMAGE" alt="Auction Item" onerror="this.style.display='none'">
        </div>

        <!-- Details Section -->
        <div class="info">
            <!-- Left: Details -->
            <div class="details">
                <div class="title">HTML_TITLE</div>

                <div class="bid-grid">
                    <div class="bid-box">
                        <div class="bid-label">Starting Bid</div>
                        <div class="bid-value">HTML_START_BID</div>
                    </div>
                    <div class="bid-box">
                        <div class="bid-label">Minimum Bid</div>
                        <div class="bid-value">HTML_INCREMENT</div>
                    </div>
                    <div class="bid-box">
                        <div class="bid-label">Fair Market Value</div>
                        <div class="bid-value">HTML_FMV</div>
                    </div>
                    <div class="bid-box">
                        <div class="bid-label">Est. Closing</div>
                        <div class="bid-value">HTML_TIME</div>
                    </div>
                </div>

                <div class="url-box">
                    <strong>Visit:</strong> HTML_URL
                </div>

                <div class="cta">
                    ✓ Register & bid instantly online
                </div>
            </div>

            <!-- Right: Side Info -->
            <div class="side-info">
                <div class="side-box">
                    <span class="side-label">Auction House</span>
                    <span class="side-value">Silent Bid</span>
                    <span class="side-label" style="margin-top: 3px;">Buddy</span>
                </div>
                <div class="side-box">
                    <span class="side-label">Item #</span>
                    <span class="side-value">HTML_LOT_NUM</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">Professional Auction Platform • silentbidbuddy.com</div>
    </div>
</body>
</html>
HTML;

        // Replace placeholders
        $html = str_replace('HTML_START', '<<<'  , $html);
        $html = str_replace('HTML_END', '>>>'   , $html);
        $html = str_replace('HTML_LOT_NUM', $item['item_number'], $html);
        $html = str_replace('HTML_TITLE', htmlspecialchars($item['title']), $html);
        $html = str_replace('HTML_IMAGE', htmlspecialchars($item['image_url'] ?? ''), $html);
        $html = str_replace('HTML_START_BID', $startBid, $html);
        $html = str_replace('HTML_INCREMENT', $increment, $html);
        $html = str_replace('HTML_FMV', $fmv, $html);
        $html = str_replace('HTML_TIME', $this->getTimeRemaining(), $html);
        $html = str_replace('HTML_URL', htmlspecialchars($this->shortUrl), $html);

        return $html;
    }

    private function getTimeRemaining() {
        if (!isset($this->item['auction_end_time'])) {
            return '2h 14m';
        }

        $endTime = strtotime($this->item['auction_end_time']);
        $now = time();
        $diff = $endTime - $now;

        if ($diff <= 0) return 'Ended';

        $hours = floor($diff / 3600);
        $minutes = floor(($diff % 3600) / 60);

        return $hours . 'h ' . $minutes . 'm';
    }

    private function getFilename() {
        $itemId = $this->item['id'];
        $dir = __DIR__ . '/../documents/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . 'item-' . $itemId . '.html';
    }

    public function getDocumentPath() {
        return 'documents/item-' . $this->item['id'] . '.html';
    }
}
?>
