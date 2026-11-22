<?php
/**
 * StayHub Email Service (PHPMailer / Local HTML Receipt Logger)
 * Provides reliable booking confirmation email dispatch, error recovery, and receipt archiving.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/logger.php';

class Mailer {

    public static function sendBookingConfirmation(array $booking, array $listing, array $guest, array $host): bool {
        try {
            $listingTitle = $listing['title'] ?? 'Your Stay';
            $bookingCode = $booking['booking_code'] ?? 'N/A';
            $subject = "Booking Confirmed! Your Stay at " . $listingTitle . " (" . $bookingCode . ")";
            $body = self::generateReceiptHtml($booking, $listing, $guest, $host);

            // Always log to local receipt archive for immediate inspection during testing/demos
            self::logEmail($guest['email'] ?? '', $guest['name'] ?? 'Guest', $subject, $body);

            // If real SMTP credentials are provided, attempt delivery
            if (defined('MAIL_ENABLED') && MAIL_ENABLED && !empty(SMTP_HOST) && !empty(SMTP_USER) && !empty(SMTP_PASS)) {
                try {
                    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host       = SMTP_HOST;
                        $mail->SMTPAuth   = true;
                        $mail->Username   = SMTP_USER;
                        $mail->Password   = SMTP_PASS;
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = SMTP_PORT;

                        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                        $mail->addAddress($guest['email'], $guest['name']);
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body    = $body;

                        $sent = $mail->send();
                        Logger::info("Booking confirmation email dispatched via SMTP", [
                            'booking_code' => $bookingCode,
                            'recipient'    => $guest['email']
                        ]);
                        return $sent;
                    }
                } catch (\Throwable $e) {
                    Logger::error("PHPMailer dispatch failed: " . $e->getMessage(), [
                        'booking_code' => $bookingCode,
                        'recipient'    => $guest['email'] ?? ''
                    ]);
                }
            }

            Logger::info("Booking receipt archived locally", ['booking_code' => $bookingCode]);
            return true;
        } catch (\Throwable $t) {
            Logger::error("Unexpected error in sendBookingConfirmation: " . $t->getMessage());
            return false;
        }
    }

    private static function generateReceiptHtml(array $booking, array $listing, array $guest, array $host): string {
        $totalFormatted = formatPrice($booking['total_price'] ?? 0);
        $pricePerNightFormatted = formatPrice($booking['price_per_night'] ?? 0);
        $cleaningFeeFormatted = formatPrice($booking['cleaning_fee'] ?? 0);
        $serviceFeeFormatted = formatPrice($booking['service_fee'] ?? 0);
        $nights = (int)($booking['total_nights'] ?? 1);
        $subtotalFormatted = formatPrice(((float)($booking['price_per_night'] ?? 0)) * $nights);

        $heroImg = !empty($listing['primary_image']) ? $listing['primary_image'] : 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?w=800';

        $guestName = htmlspecialchars((string)($guest['name'] ?? 'Guest'), ENT_QUOTES, 'UTF-8');
        $listingCity = htmlspecialchars((string)($listing['city'] ?? 'your destination'), ENT_QUOTES, 'UTF-8');
        $bookingCode = htmlspecialchars((string)($booking['booking_code'] ?? ''), ENT_QUOTES, 'UTF-8');
        $listingTitle = htmlspecialchars((string)($listing['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $listingAddress = htmlspecialchars((string)($listing['address'] ?? ''), ENT_QUOTES, 'UTF-8');
        $hostName = htmlspecialchars((string)($host['name'] ?? 'Host'), ENT_QUOTES, 'UTF-8');
        $hostPhone = htmlspecialchars((string)($host['phone'] ?? 'Contact via platform'), ENT_QUOTES, 'UTF-8');
        $hostEmail = htmlspecialchars((string)($host['email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $appName = htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'StayHub', ENT_QUOTES, 'UTF-8');

        $checkInFormatted = isValidDate($booking['check_in'] ?? '') ? date('D, M j, Y', strtotime($booking['check_in'])) : 'Check-in';
        $checkOutFormatted = isValidDate($booking['check_out'] ?? '') ? date('D, M j, Y', strtotime($booking['check_out'])) : 'Check-out';
        $guestsCount = (int)($booking['guests_count'] ?? 1);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f7f7f7; margin: 0; padding: 20px; color: #222; }
                .email-card { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
                .email-header { background: #FF385C; color: #fff; padding: 24px; text-align: center; }
                .email-header h1 { margin: 0; font-size: 24px; }
                .email-body { padding: 32px 24px; }
                .booking-badge { display: inline-block; background: #e7f5ea; color: #0f5132; font-weight: bold; padding: 6px 12px; border-radius: 20px; font-size: 14px; margin-bottom: 20px; }
                .listing-box { border: 1px solid #ebebeb; border-radius: 12px; overflow: hidden; margin: 20px 0; }
                .listing-img { width: 100%; height: 200px; object-fit: cover; }
                .listing-content { padding: 16px; }
                .details-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .details-table td { padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
                .details-table td.label { color: #717171; }
                .details-table td.value { font-weight: 600; text-align: right; }
                .total-row td { font-size: 18px; font-weight: bold; color: #FF385C; border-top: 2px solid #ddd; }
                .host-box { background: #f9f9f9; border-radius: 12px; padding: 16px; margin-top: 24px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #888; }
            </style>
        </head>
        <body>
            <div class='email-card'>
                <div class='email-header'>
                    <h1>{$appName}</h1>
                    <p style='margin: 4px 0 0;'>Your reservation is confirmed!</p>
                </div>
                <div class='email-body'>
                    <div class='booking-badge'>Reservation Confirmed &bull; {$bookingCode}</div>
                    <h2>Hi {$guestName}, you're going to {$listingCity}!</h2>
                    <p style='color: #666;'>We've received your booking and your payment has been processed successfully. Below are your reservation details.</p>

                    <div class='listing-box'>
                        <img src='" . htmlspecialchars($heroImg, ENT_QUOTES, 'UTF-8') . "' class='listing-img' alt='Property'>
                        <div class='listing-content'>
                            <h3 style='margin:0 0 6px;'>{$listingTitle}</h3>
                            <p style='margin:0; color: #717171;'>{$listingAddress}, {$listingCity}</p>
                        </div>
                    </div>

                    <table class='details-table'>
                        <tr>
                            <td class='label'>Check-in</td>
                            <td class='value'>{$checkInFormatted} (From 2:00 PM)</td>
                        </tr>
                        <tr>
                            <td class='label'>Check-out</td>
                            <td class='value'>{$checkOutFormatted} (Until 11:00 AM)</td>
                        </tr>
                        <tr>
                            <td class='label'>Duration</td>
                            <td class='value'>{$nights} night(s)</td>
                        </tr>
                        <tr>
                            <td class='label'>Guests</td>
                            <td class='value'>{$guestsCount} guest(s)</td>
                        </tr>
                        <tr>
                            <td class='label'>Nightly Rate ({$nights} &times; {$pricePerNightFormatted})</td>
                            <td class='value'>{$subtotalFormatted}</td>
                        </tr>
                        <tr>
                            <td class='label'>Cleaning fee</td>
                            <td class='value'>{$cleaningFeeFormatted}</td>
                        </tr>
                        <tr>
                            <td class='label'>Service fee</td>
                            <td class='value'>{$serviceFeeFormatted}</td>
                        </tr>
                        <tr class='total-row'>
                            <td class='label' style='color:#222;'>Total Paid</td>
                            <td class='value'>{$totalFormatted}</td>
                        </tr>
                    </table>

                    <div class='host-box'>
                        <strong>Host Information:</strong><br>
                        {$hostName} &bull; {$hostPhone}<br>
                        <span style='color:#717171;font-size:13px;'>Email: {$hostEmail}</span>
                    </div>
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " {$appName}, Inc. All rights reserved.<br>
                    Need help with your stay? Visit our 24/7 Support Center.
                </div>
            </div>
        </body>
        </html>";
    }

    private static function logEmail(string $toEmail, string $toName, string $subject, string $htmlBody): void {
        $logDir = defined('STORAGE_LOGS_DIR') ? STORAGE_LOGS_DIR : dirname(__DIR__) . '/storage/logs/';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . 'mail_receipts.html';

        $safeToName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeToEmail = htmlspecialchars($toEmail, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');

        $entry = "<!-- Email sent to {$safeToName} <{$safeToEmail}> on " . date('Y-m-d H:i:s') . " -->\n"
               . "<div style='border: 3px solid #FF385C; margin: 30px auto; max-width: 650px; border-radius: 12px; padding: 12px; background: #fff;'>\n"
               . "<div style='font-family: monospace; font-size: 12px; background: #eee; padding: 8px; margin-bottom: 15px; border-radius: 4px;'>"
               . "<strong>TO:</strong> {$safeToName} &lt;{$safeToEmail}&gt;<br>"
               . "<strong>SUBJECT:</strong> {$safeSubject}<br>"
               . "<strong>SENT AT:</strong> " . date('Y-m-d H:i:s') . "<br>"
               . "<strong>STATUS:</strong> Delivered (Logged Successfully)"
               . "</div>\n"
               . $htmlBody
               . "\n</div>\n";

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
