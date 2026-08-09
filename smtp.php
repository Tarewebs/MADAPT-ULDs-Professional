<?php
declare(strict_types=1);

/**
 * Minimal authenticated SMTP client for Hostinger.
 * Credentials are read from config constants/environment only and are never stored in the repository.
 */
function madapt_smtp_send(string $to, string $subject, string $body): void {
    $host = defined('MADAPT_SMTP_HOST') ? (string)MADAPT_SMTP_HOST : (getenv('MADAPT_SMTP_HOST') ?: '');
    $port = defined('MADAPT_SMTP_PORT') ? (int)MADAPT_SMTP_PORT : (int)(getenv('MADAPT_SMTP_PORT') ?: 465);
    $user = defined('MADAPT_SMTP_USER') ? (string)MADAPT_SMTP_USER : (getenv('MADAPT_SMTP_USER') ?: '');
    $pass = defined('MADAPT_SMTP_PASS') ? (string)MADAPT_SMTP_PASS : (getenv('MADAPT_SMTP_PASS') ?: '');
    $from = defined('MADAPT_SMTP_FROM') ? (string)MADAPT_SMTP_FROM : (getenv('MADAPT_SMTP_FROM') ?: $user);
    $fromName = defined('MADAPT_SMTP_FROM_NAME') ? (string)MADAPT_SMTP_FROM_NAME : (getenv('MADAPT_SMTP_FROM_NAME') ?: 'MADAPT ULDs');

    if ($host === '' || $user === '' || $pass === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('SMTP is not configured.');
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Invalid recipient address.');
    }

    $timeout = 20;
    $remote = $port === 465 ? 'ssl://' . $host : $host;
    $fp = @stream_socket_client('tcp://' . $remote . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$fp) throw new RuntimeException('SMTP connection failed.');
    stream_set_timeout($fp, $timeout);

    try {
        smtp_expect($fp, [220]);
        smtp_cmd($fp, 'EHLO madapt.local', [250]);
        if ($port !== 465) {
            smtp_cmd($fp, 'STARTTLS', [220]);
            $crypto = defined('STREAM_CRYPTO_METHOD_TLS_CLIENT') ? STREAM_CRYPTO_METHOD_TLS_CLIENT : STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (!stream_socket_enable_crypto($fp, true, $crypto)) throw new RuntimeException('SMTP TLS negotiation failed.');
            smtp_cmd($fp, 'EHLO madapt.local', [250]);
        }
        smtp_cmd($fp, 'AUTH LOGIN', [334]);
        smtp_cmd($fp, base64_encode($user), [334]);
        smtp_cmd($fp, base64_encode($pass), [235]);
        smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_cmd($fp, 'DATA', [354]);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . smtp_encode_header($fromName) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . smtp_encode_header($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'Reply-To: ' . $from,
        ];
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n", "\r"], "\n", $body);
        $payload = preg_replace('/^\./m', '..', $payload) ?? $payload;
        fwrite($fp, str_replace("\n", "\r\n", $payload) . "\r\n.\r\n");
        smtp_expect($fp, [250]);
        smtp_cmd($fp, 'QUIT', [221, 250]);
    } finally {
        fclose($fp);
    }
}

function smtp_cmd($fp, string $command, array $codes): void {
    fwrite($fp, $command . "\r\n");
    smtp_expect($fp, $codes);
}

function smtp_expect($fp, array $codes): string {
    $response = '';
    while (($line = fgets($fp, 4096)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $codes, true)) throw new RuntimeException('SMTP server rejected the message.');
    return $response;
}

function smtp_encode_header(string $value): string {
    if (preg_match('/[^\x20-\x7E]/', $value)) return '=?UTF-8?B?' . base64_encode($value) . '?=';
    return $value;
}
