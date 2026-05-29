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
        .container { width: 8.5in; height: 11in; margin: 0 auto; padding: 0.25in; display: flex; flex-direction: column; }

        /* Hero Image Section - 60% of page */
        .hero-section {
            flex: 0 0 5.8in;
            background: linear-gradient(135deg, #f5f5f5 0%, #fafafa 100%);
            border-radius: 4px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.15in;
            position: relative;
        }

        .image-wrapper {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.2in;
        }

        .image-wrapper img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .no-image {
            text-align: center;
            color: #999;
            font-size: 12px;
        }

        .lot-badge {
            position: absolute;
            top: 0.15in;
            right: 0.15in;
            background: #667eea;
            color: white;
            padding: 0.1in 0.15in;
            border-radius: 3px;
            font-weight: 700;
            font-size: 11px;
        }

        /* Details Section - Bottom 40% */
        .details-section {
            flex: 0 0 4.7in;
            display: grid;
            grid-template-columns: 1fr 0.9in;
            gap: 0.1in;
            overflow: hidden;
        }

        .info-column {
            display: flex;
            flex-direction: column;
            gap: 0.08in;
        }

        .title {
            font-size: 14px;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.15;
            flex-shrink: 0;
        }

        .description {
            font-size: 9px;
            color: #555;
            line-height: 1.25;
            flex-shrink: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .bid-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.05in;
            flex-shrink: 0;
        }

        .bid-item {
            background: #f9f9f9;
            padding: 0.05in 0.08in;
            border-left: 1.5px solid #667eea;
            border-radius: 2px;
        }

        .bid-label {
            font-size: 7px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 1px;
        }

        .bid-value {
            font-size: 11px;
            font-weight: 700;
            color: #1a1a1a;
        }

        .cta-small {
            font-size: 8px;
            color: #666;
            background: #f0f4ff;
            padding: 0.04in 0.06in;
            border-radius: 2px;
            border-left: 1.5px solid #667eea;
            line-height: 1.2;
            flex-shrink: 0;
        }

        /* QR Code Column */
        .qr-column {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.04in;
            justify-content: flex-start;
        }

        .qr-code {
            width: 0.85in;
            height: 0.85in;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 1px solid #eee;
            border-radius: 3px;
        }

        .qr-code img {
            width: 100%;
            height: 100%;
        }

        .qr-label {
            font-size: 7px;
            color: #999;
            text-align: center;
            line-height: 1.2;
        }

        .footer {
            text-align: center;
            font-size: 7px;
            color: #999;
            padding-top: 0.05in;
            border-top: 0.5pt solid #eee;
            flex-shrink: 0;
        }

        .print-button {
            display: inline-block;
            margin-bottom: 15px;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

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
                page-break-after: avoid;
                page-break-inside: avoid;
            }
            .hero-section {
                page-break-inside: avoid;
            }
            .details-section {
                page-break-inside: avoid;
            }
            @page {
                margin: 0.25in;
                size: letter;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Print Button (hidden in print) -->
        <button class="print-button" onclick="window.print()">🖨️ Print This Item</button>

        <!-- Hero Image Section - DOMINANT FEATURE -->
        <div class="hero-section">
            <div class="lot-badge">LOT #{$item['item_number']}</div>
            <div class="image-wrapper">
                {$this->getImageHTML()}
            </div>
        </div>

        <!-- Details Section -->
        <div class="details-section">
            <!-- Left Column: Info -->
            <div class="info-column">
                <div class="title">{$item['title']}</div>
                {$this->getDescriptionSmallHTML()}
                <div class="bid-info">
                    <div class="bid-item">
                        <div class="bid-label">Starting Bid</div>
                        <div class="bid-value">{$startBid}</div>
                    </div>
                    <div class="bid-item">
                        <div class="bid-label">Increment</div>
                        <div class="bid-value">\${$item['min_increment']}</div>
                    </div>
                    {$this->getFMVSmallHTML($fmv)}
                    {$this->getBuyNowSmallHTML()}
                </div>
                <div class="cta-small">
                    📱 Scan QR code to bid instantly
                </div>
            </div>

            <!-- Right Column: QR Code -->
            <div class="qr-column">
                <div class="qr-code">
                    <img src="{$this->qrCodeUrl}" alt="Bid QR Code" />
                </div>
                <div class="qr-label">Scan to Bid</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            Silent Bid Buddy • {$this->getFormattedDate()}
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

    private function getDescriptionSmallHTML() {
        if (empty($this->item['description'])) {
            return '';
        }
        $desc = substr($this->item['description'], 0, 150);
        return '<div class="description">' . htmlspecialchars($desc) . '...</div>';
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

    private function getFMVSmallHTML($fmv) {
        if (empty($this->item['fair_market_value'])) {
            return '';
        }
        return '
            <div class="bid-item">
                <div class="bid-label">Fair Market Value</div>
                <div class="bid-value">' . $fmv . '</div>
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

    private function getBuyNowSmallHTML() {
        if (empty($this->item['buy_now_price'])) {
            return '';
        }
        return '
            <div class="bid-item">
                <div class="bid-label">Buy Now</div>
                <div class="bid-value">$' . number_format($this->item['buy_now_price'], 2) . '</div>
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
