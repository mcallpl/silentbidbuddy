<?php
// ============================================================
// PDF GENERATOR — Create professional item documents with QR codes
// ============================================================

require_once __DIR__ . '/rebrandly-utils.php';

class ItemPDFGenerator {
    private $item;
    private $qrCodeUrl;
    private $shortUrl;

    public function __construct($item) {
        $this->item = $item;
    }

    public function generate($shortUrl, $qrCodeUrl = null) {
        $this->shortUrl = $shortUrl;
        // Generate QR code image URL using qr-server API (reliable image endpoint)
        $this->qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($shortUrl);

        // Create HTML content
        $html = $this->buildHTML();

        // Save as file
        $filename = $this->getFilename();
        file_put_contents($filename, $html);

        return $filename;
    }

    private function buildHTML() {
        $item = $this->item;
        $startBid = '$' . number_format($item['starting_bid'], 2);
        $fmv = $item['fair_market_value'] ? '$' . number_format($item['fair_market_value'], 2) : 'N/A';
        $duration = $this->formatDuration($item['auction_duration_seconds']);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: white; color: #333; }
        .container { max-width: 8.5in; margin: 0 auto; padding: 0.3in; height: 11in; display: flex; flex-direction: column; }
        .header { text-align: center; margin-bottom: 0.15in; flex-shrink: 0; }
        .title { font-size: 18px; font-weight: 700; color: #1a1a1a; margin-bottom: 3px; line-height: 1.2; }
        .subtitle { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .content { display: grid; grid-template-columns: 2in 3.5in; gap: 0.15in; flex: 1; min-height: 0; }
        .image-section { display: flex; flex-direction: column; }
        .image-wrapper { background: #f5f5f5; border-radius: 6px; overflow: hidden; flex: 1; display: flex; align-items: center; justify-content: center; min-height: 2in; }
        .image-wrapper img { max-width: 100%; max-height: 100%; object-fit: contain; padding: 3px; }
        .no-image { text-align: center; color: #999; font-size: 11px; padding: 10px; }
        .qr-section { text-align: center; padding-top: 3px; }
        .qr-code { display: inline-block; }
        .qr-code img { width: 1.2in; height: 1.2in; display: block; }
        .qr-text { font-size: 8px; color: #999; margin-top: 2px; }
        .qr-url { font-size: 8px; color: #667eea; word-break: break-all; margin-top: 2px; font-weight: 500; }
        .details-section { overflow-y: auto; }
        .section { margin-bottom: 0.1in; }
        .section-title { font-size: 9px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }
        .description { font-size: 10px; color: #555; line-height: 1.3; }
        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
        .detail-item { background: #f9f9f9; padding: 5px 8px; border-radius: 4px; border-left: 2px solid #667eea; }
        .detail-label { font-size: 8px; color: #999; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 2px; }
        .detail-value { font-size: 12px; font-weight: 600; color: #1a1a1a; }
        .cta { margin-top: 0.1in; font-size: 9px; color: #666; background: #f0f4ff; padding: 5px; border-radius: 4px; border-left: 2px solid #667eea; line-height: 1.3; }
        .footer { text-align: center; padding-top: 3px; border-top: 1px solid #eee; font-size: 8px; color: #999; flex-shrink: 0; margin-top: 0.1in; }
        .print-button { display: inline-block; margin-bottom: 15px; padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .print-button:hover { background: #5568d3; }
        @media print {
            body {
                background: white;
                margin: 0;
                padding: 0;
            }
            .print-button {
                display: none !important;
            }
            .container {
                height: auto;
                padding: 0.3in;
                page-break-after: avoid;
                page-break-inside: avoid;
            }
            .content {
                page-break-inside: avoid;
            }
            .image-wrapper {
                page-break-inside: avoid;
            }
            .details-section {
                page-break-inside: avoid;
            }
            a {
                color: #667eea;
                text-decoration: none;
            }
            @page {
                margin: 0.3in;
                size: letter;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Print Button -->
        <button class="print-button" onclick="window.print()">🖨️ Print This Item</button>

        <!-- Header -->
        <div class="header">
            <div class="subtitle">Silent Bid Buddy Auction</div>
            <h1 class="title">{$item['title']}</h1>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Image Section -->
            <div class="image-section">
                <div class="image-wrapper">
                    {$this->getImageHTML()}
                </div>
            </div>

            <!-- Details Section -->
            <div class="details-section">
                <!-- Description -->
                {$this->getDescriptionHTML()}

                <!-- Bid Details -->
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Starting Bid</div>
                        <div class="detail-value">{$startBid}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Minimum Increment</div>
                        <div class="detail-value">\${$item['min_increment']}</div>
                    </div>
                    {$this->getFMVHTML($fmv)}
                    {$this->getBuyNowHTML()}
                    <div class="detail-item">
                        <div class="detail-label">Duration</div>
                        <div class="detail-value">{$duration}</div>
                    </div>
                </div>

                <!-- QR Code -->
                <div class="qr-section">
                    <div class="qr-code">
                        <img src="{$this->qrCodeUrl}" alt="Bid QR Code" />
                    </div>
                    <div class="qr-text">Scan to start bidding instantly</div>
                    <div class="qr-url">{$this->shortUrl}</div>
                </div>

                <!-- CTA -->
                <div class="cta">
                    <div class="cta-text">
                        📱 Not registered? Scan the QR code and you'll be guided to register before bidding
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Silent Bid Buddy • Professional Auction Platform • {$this->getFormattedDate()}</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getImageHTML() {
        if (empty($this->item['image_url'])) {
            return '<div class="no-image">No image available</div>';
        }
        return '<img src="' . htmlspecialchars($this->item['image_url']) . '" alt="' . htmlspecialchars($this->item['title']) . '" />';
    }

    private function getDescriptionHTML() {
        if (empty($this->item['description'])) {
            return '';
        }
        return '
            <div class="section">
                <div class="section-title">Description</div>
                <div class="description">' . nl2br(htmlspecialchars($this->item['description'])) . '</div>
            </div>
        ';
    }

    private function getFMVHTML($fmv) {
        if (empty($this->item['fair_market_value'])) {
            return '';
        }
        return '
            <div class="detail-item">
                <div class="detail-label">Fair Market Value</div>
                <div class="detail-value">' . $fmv . '</div>
            </div>
        ';
    }

    private function getBuyNowHTML() {
        if (empty($this->item['buy_now_price'])) {
            return '';
        }
        return '
            <div class="detail-item">
                <div class="detail-label">Buy Now Price</div>
                <div class="detail-value">$' . number_format($this->item['buy_now_price'], 2) . '</div>
            </div>
        ';
    }

    private function formatDuration($seconds) {
        if (!$seconds) return 'N/A';
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        return $minutes . ' minutes';
    }

    private function getFormattedDate() {
        return date('F d, Y');
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
