<?php
/**
 * StayHub Email Service (PHPMailer / Local HTML Receipt Logger)
 * Provides reliable booking confirmation email dispatch, error recovery, and receipt archiving.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/logger.php';

class Mailer {

    public static function sendRegistrationConfirmation(array $user): bool {
        $email = trim((string)($user['email'] ?? ''));
        if ($email === '') {
            return false;
        }

        $name = trim((string)($user['name'] ?? 'Guest'));
        $role = trim((string)($user['role'] ?? 'guest'));
        $subject = 'Welcome to ' . (defined('APP_NAME') ? APP_NAME : 'StayHub');
        $body = "
        <!DOCTYPE html>
        <html><head><meta charset='utf-8'></head>
        <body style='font-family:Arial,sans-serif;background:#f7f7f7;padding:24px'>
          <div style='max-width:600px;margin:auto;background:white;border-radius:12px;padding:32px'>
            <h1 style='color:#FF385C;margin-top:0'>Welcome to " . htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'StayHub', ENT_QUOTES, 'UTF-8') . "</h1>
            <p>Hi " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ",</p>
            <p>Your " . htmlspecialchars($role, ENT_QUOTES, 'UTF-8') . " account has been created successfully.</p>
            <p>You can now " . ($role === 'host' ? 'create listings and manage reservations.' : 'search stays and make reservations.') . "</p>
          </div>
        </body></html>";

        self::logEmail($email, $name, $subject, $body);

        $emailJsTemplate = defined('EMAILJS_REGISTRATION_TEMPLATE_ID') ? EMAILJS_REGISTRATION_TEMPLATE_ID : '';
        $emailJsSent = self::sendViaEmailJs(
            $email,
            $name,
            $subject,
            $body,
            $emailJsTemplate,
            [
                'name' => $name,
                'role' => $role,
                'login_url' => BASE_URL . '/login.php'
            ]
        );
        if ($emailJsSent) {
            return true;
        }

        return self::sendViaSmtp($email, $name, $subject, $body);
    }

    public static function sendBookingConfirmation(array $booking, array $listing, array $guest, array $host): bool {
        try {
            $listingTitle = $listing['title'] ?? 'Your Stay';
            $bookingCode = $booking['booking_code'] ?? 'N/A';
            $subject = "Booking Confirmed! Your Stay at " . $listingTitle . " (" . $bookingCode . ")";
            $body = self::generateReceiptHtml($booking, $listing, $guest, $host);

            // Always log to local receipt archive for immediate inspection during testing/demos
            self::logEmail($guest['email'] ?? '', $guest['name'] ?? 'Guest', $subject, $body);

            $guestTemplate = defined('EMAILJS_BOOKING_TEMPLATE_ID') ? EMAILJS_BOOKING_TEMPLATE_ID : '';
            $hostTemplate = defined('EMAILJS_HOST_BOOKING_TEMPLATE_ID') ? EMAILJS_HOST_BOOKING_TEMPLATE_ID : '';
            $bookingParams = [
                'booking_code' => $bookingCode,
                'listing_title' => $listingTitle,
                'check_in' => $booking['check_in'] ?? '',
                'check_out' => $booking['check_out'] ?? '',
                'total_price' => formatPrice($booking['total_price'] ?? 0),
                'guest_name' => $guest['name'] ?? 'Guest',
                'host_name' => $host['name'] ?? 'Host'
            ];

            $guestSent = self::sendViaEmailJs(
                $guest['email'] ?? '',
                $guest['name'] ?? 'Guest',
                $subject,
                $body,
                $guestTemplate,
                $bookingParams
            );
            if (!$guestSent) {
                $guestSent = self::sendViaSmtp($guest['email'] ?? '', $guest['name'] ?? 'Guest', $subject, $body);
            }

            $hostSent = false;
            if (!empty($host['email'])) {
                $hostSubject = "New booking received: " . $listingTitle . " (" . $bookingCode . ")";
                $hostSent = self::sendViaEmailJs(
                    $host['email'],
                    $host['name'] ?? 'Host',
                    $hostSubject,
                    $body,
                    $hostTemplate,
                    array_merge($bookingParams, [
                        'subject' => $hostSubject,
                        'guest_email' => $guest['email'] ?? ''
                    ])
                );
                if (!$hostSent) {
                    $hostSent = self::sendViaSmtp($host['email'], $host['name'] ?? 'Host', $hostSubject, $body);
                }
            }

            Logger::info("Booking confirmation delivery completed", [
                'booking_code' => $bookingCode,
                'guest_sent' => $guestSent,
                'host_sent' => $hostSent
            ]);
            return $guestSent && $hostSent;
        } catch (\Throwable $t) {
            Logger::error("Unexpected error in sendBookingConfirmation: " . $t->getMessage());
            return false;
        }
    }

    private static function sendViaEmailJs(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $templateId,
        array $extraParams = []
    ): bool {
        if (
            $toEmail === '' ||
            !defined('EMAILJS_SERVICE_ID') ||
            EMAILJS_SERVICE_ID === '' ||
            !defined('EMAILJS_PUBLIC_KEY') ||
            EMAILJS_PUBLIC_KEY === '' ||
            $templateId === ''
        ) {
            return false;
        }

        $payload = array_merge([
            'service_id' => EMAILJS_SERVICE_ID,
            'template_id' => $templateId,
            'user_id' => EMAILJS_PUBLIC_KEY,
            'template_params' => array_merge($extraParams, [
                'to_email' => $toEmail,
                'to_name' => $toName,
                'subject' => $subject,
                'message' => strip_tags($htmlBody),
                'html_message' => $htmlBody,
                'app_name' => defined('APP_NAME') ? APP_NAME : 'StayHub'
            ])
        ]);

        if (defined('EMAILJS_PRIVATE_KEY') && EMAILJS_PRIVATE_KEY !== '') {
            $payload['accessToken'] = EMAILJS_PRIVATE_KEY;
        }

        if (!function_exists('curl_init')) {
            Logger::warning('EmailJS delivery skipped because the PHP cURL extension is unavailable.', [
                'recipient' => $toEmail
            ]);
            return false;
        }

        $ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        $response = curl_exec($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpStatus < 200 || $httpStatus >= 300) {
            Logger::error('EmailJS delivery failed', [
                'recipient' => $toEmail,
                'template_id' => $templateId,
                'http_status' => $httpStatus,
                'error' => $curlError !== '' ? $curlError : (string)$response
            ]);
            return false;
        }

        Logger::info('EmailJS message sent', [
            'recipient' => $toEmail,
            'template_id' => $templateId
        ]);
        return true;
    }

    private static function sendViaSmtp(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
        if (
            $toEmail === '' ||
            !defined('MAIL_ENABLED') ||
            !MAIL_ENABLED ||
            !defined('SMTP_HOST') ||
            SMTP_HOST === '' ||
            !defined('SMTP_USER') ||
            SMTP_USER === '' ||
            !defined('SMTP_PASS') ||
            SMTP_PASS === ''
        ) {
            return false;
        }

        try {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                Logger::warning('SMTP delivery skipped because PHPMailer is not installed.', [
                    'recipient' => $toEmail
                ]);
                return false;
            }

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            $sent = $mail->send();
            Logger::info('SMTP message sent', [
                'recipient' => $toEmail,
                'subject' => $subject
            ]);
            return $sent;
        } catch (\Throwable $e) {
            Logger::error('SMTP delivery failed: ' . $e->getMessage(), [
                'recipient' => $toEmail
            ]);
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
