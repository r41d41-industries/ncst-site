<?php

declare(strict_types=1);

/**
 * Send HTML+text email via SMTP2GO /email/send.
 *
 * @return array{ok:bool,error:?string,email_id:?string}
 */
function cs_mail_send(string $to, string $subject, string $html, string $text): array
{
    $apiKey = (string) (getenv('SMTP2GO_API_KEY') ?: '');
    $sender = (string) (getenv('SMTP2GO_SENDER') ?: '');
    $url = (string) (getenv('SMTP2GO_API_URL') ?: 'https://api.smtp2go.com/v3/email/send');

    if ($apiKey === '' || $sender === '') {
        return ['ok' => false, 'error' => 'Mail is not configured.', 'email_id' => null];
    }

    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid recipient.', 'email_id' => null];
    }

    $payload = [
        'sender' => $sender,
        'to' => [$to],
        'subject' => $subject,
        'html_body' => $html,
        'text_body' => $text,
    ];

    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        return ['ok' => false, 'error' => 'Failed to encode mail payload.', 'email_id' => null];
    }

    $transport = cs_mail_http_post($url, $body, $apiKey);
    if (!$transport['ok']) {
        return ['ok' => false, 'error' => $transport['error'], 'email_id' => null];
    }

    $raw = $transport['body'];
    $status = $transport['status'];

    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded)) {
        return ['ok' => false, 'error' => 'Unexpected mail response (HTTP ' . $status . ').', 'email_id' => null];
    }

    $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    $failed = (int) ($data['failed'] ?? 0);
    $succeeded = (int) ($data['succeeded'] ?? 0);
    $emailId = isset($data['email_id']) && is_string($data['email_id']) ? $data['email_id'] : null;

    if ($status >= 200 && $status < 300 && $failed === 0 && ($succeeded > 0 || $emailId !== null)) {
        return ['ok' => true, 'error' => null, 'email_id' => $emailId];
    }

    $apiError = '';
    if (isset($data['error']) && is_string($data['error'])) {
        $apiError = $data['error'];
    } elseif (isset($data['failures']) && is_array($data['failures']) && $data['failures'] !== []) {
        $apiError = implode('; ', array_map('strval', $data['failures']));
    }

    return [
        'ok' => false,
        'error' => $apiError !== '' ? $apiError : 'Mail send failed (HTTP ' . $status . ').',
        'email_id' => $emailId,
    ];
}

/**
 * HTTP POST JSON helper with curl extension, curl.exe, or stream fallbacks.
 *
 * @return array{ok:bool,error:?string,status:int,body:?string}
 */
function cs_mail_http_post(string $url, string $body, string $apiKey): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'Failed to initialize mail request.', 'status' => 0, 'body' => null];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Smtp2go-Api-Key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'error' => 'Mail transport error: ' . $error, 'status' => $status, 'body' => null];
        }

        return ['ok' => true, 'error' => null, 'status' => $status, 'body' => is_string($raw) ? $raw : null];
    }

    $curlBin = cs_mail_find_curl_binary();
    if ($curlBin !== null) {
        $tmp = tempnam(sys_get_temp_dir(), 'ncstmail');
        if ($tmp === false) {
            return ['ok' => false, 'error' => 'Failed to create temp mail payload.', 'status' => 0, 'body' => null];
        }
        file_put_contents($tmp, $body);

        $cmd = [
            $curlBin,
            '-sS',
            '-w',
            "\n%{http_code}",
            '-X',
            'POST',
            $url,
            '-H',
            'Content-Type: application/json',
            '-H',
            'Accept: application/json',
            '-H',
            'X-Smtp2go-Api-Key: ' . $apiKey,
            '--data-binary',
            '@' . $tmp,
            '--max-time',
            '20',
        ];

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptor, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            @unlink($tmp);
            return ['ok' => false, 'error' => 'Failed to start curl mail transport.', 'status' => 0, 'body' => null];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        @unlink($tmp);

        if ($code !== 0 || !is_string($stdout)) {
            $detail = is_string($stderr) && $stderr !== '' ? trim($stderr) : 'curl exited with code ' . $code;
            return ['ok' => false, 'error' => 'Mail transport error: ' . $detail, 'status' => 0, 'body' => null];
        }

        $stdout = str_replace("\r\n", "\n", $stdout);
        $pos = strrpos($stdout, "\n");
        if ($pos === false) {
            return ['ok' => false, 'error' => 'Unexpected curl mail response.', 'status' => 0, 'body' => null];
        }
        $rawBody = substr($stdout, 0, $pos);
        $status = (int) trim(substr($stdout, $pos + 1));

        return ['ok' => true, 'error' => null, 'status' => $status, 'body' => $rawBody];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Smtp2go-Api-Key: ' . $apiKey,
                'Content-Length: ' . strlen($body),
            ]),
            'content' => $body,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        $last = error_get_last();
        $detail = is_array($last) && isset($last['message']) ? (string) $last['message'] : 'HTTP request failed.';
        return ['ok' => false, 'error' => 'Mail transport error: ' . $detail, 'status' => 0, 'body' => null];
    }

    $status = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $headerLine, $m)) {
                $status = (int) $m[1];
                break;
            }
        }
    }

    return ['ok' => true, 'error' => null, 'status' => $status, 'body' => $raw];
}

function cs_mail_find_curl_binary(): ?string
{
    if (stripos(PHP_OS, 'WIN') === 0) {
        $candidates = [
            'C:\\Windows\\System32\\curl.exe',
            'curl.exe',
        ];
    } else {
        $candidates = ['/usr/bin/curl', '/bin/curl', 'curl'];
    }

    foreach ($candidates as $bin) {
        if ($bin === 'curl' || $bin === 'curl.exe') {
            return $bin;
        }
        if (is_file($bin)) {
            return $bin;
        }
    }

    return null;
}

/**
 * Build and send a password-reset email.
 *
 * @return array{ok:bool,error:?string}
 */
function cs_mail_send_password_reset(string $to, string $rawToken, ?string $displayName = null): array
{
    $resetUrl = site_url('admin/reset-password.php?token=' . urlencode($rawToken));
    $name = trim((string) $displayName);
    $greeting = $name !== '' ? 'Hi ' . $name . ',' : 'Hi,';

    $subject = 'Reset your NCST admin password';
    $text = $greeting . "\n\n"
        . "We received a request to reset your Newnan Coweta Scanner Traffic admin password.\n\n"
        . "Open this link to choose a new password (expires in 1 hour):\n"
        . $resetUrl . "\n\n"
        . "If you did not request this, you can ignore this email.\n";

    $safeGreeting = htmlspecialchars($greeting, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $html = '<!DOCTYPE html><html lang="en"><body style="font-family:Inter,Segoe UI,sans-serif;line-height:1.5;color:#09090b;">'
        . '<p>' . $safeGreeting . '</p>'
        . '<p>We received a request to reset your <strong>Newnan Coweta Scanner Traffic</strong> admin password.</p>'
        . '<p><a href="' . $safeUrl . '" style="display:inline-block;padding:10px 16px;background:#a85c08;color:#fff;text-decoration:none;border-radius:8px;">Reset password</a></p>'
        . '<p style="color:#52525b;font-size:14px;">Or copy this link:<br><a href="' . $safeUrl . '">' . $safeUrl . '</a></p>'
        . '<p style="color:#71717a;font-size:14px;">This link expires in 1 hour. If you did not request a reset, you can ignore this email.</p>'
        . '</body></html>';

    $result = cs_mail_send($to, $subject, $html, $text);
    return ['ok' => $result['ok'], 'error' => $result['error']];
}
