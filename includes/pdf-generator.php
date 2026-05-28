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
        .container { max-width: 850px; margin: 0 auto; padding: 40px 30px; }
        .header { text-align: center; margin-bottom: 30px; }
        .title { font-size: 32px; font-weight: 700; color: #1a1a1a; margin-bottom: 10px; }
        .subtitle { font-size: 14px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
        .content { display: flex; gap: 40px; align-items: flex-start; }
        .image-section { flex: 1; }
        .image-wrapper { background: #f5f5f5; border-radius: 12px; overflow: hidden; margin-bottom: 20px; aspect-ratio: 1; display: flex; align-items: center; justify-content: center; }
        .image-wrapper img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .no-image { text-align: center; color: #999; font-size: 14px; padding: 40px; }
        .details-section { flex: 1; }
        .section { margin-bottom: 30px; }
        .section-title { font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .section-content { font-size: 16px; color: #333; line-height: 1.6; }
        .description { font-size: 15px; color: #555; line-height: 1.8; }
        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .detail-item { background: #f9f9f9; padding: 15px; border-radius: 8px; border-left: 3px solid #667eea; }
        .detail-label { font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .detail-value { font-size: 18px; font-weight: 600; color: #1a1a1a; }
        .qr-section { text-align: center; margin-top: 30px; padding-top: 30px; border-top: 1px solid #eee; }
        .qr-code { display: inline-block; padding: 20px; background: white; border-radius: 12px; }
        .qr-code img { max-width: 180px; height: auto; display: block; }
        .qr-text { font-size: 12px; color: #999; margin-top: 12px; }
        .qr-url { font-size: 13px; color: #667eea; word-break: break-all; margin-top: 8px; font-weight: 500; }
        .cta { margin-top: 30px; text-align: center; font-size: 13px; color: #666; }
        .cta-text { background: #f0f4ff; padding: 15px; border-radius: 8px; border-left: 3px solid #667eea; }
        .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; font-size: 11px; color: #999; }
        @media print {
            body { background: white; }
            .container { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
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
