<?php

namespace App\Services;

class SmtpConnectionTester
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * Opens a raw SMTP connection, upgrades to TLS if requested, and attempts
     * AUTH LOGIN -- then disconnects without ever issuing MAIL FROM/RCPT
     * TO/DATA, so no email is actually sent. This is deliberately separate
     * from SmtpController::test(), which goes through CI4's Email class and
     * always delivers a real message; "Test Connection" is meant to be a
     * lighter, faster check that doesn't need a recipient address.
     *
     * @return array{success: bool, message: string}
     */
    public function test(string $host, int $port, string $encryption, string $username, string $password): array
    {
        $errno  = 0;
        $errstr = '';
        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

        $socket = @stream_socket_client($remote, $errno, $errstr, self::TIMEOUT_SECONDS);
        if (! $socket) {
            return ['success' => false, 'message' => "Could not connect to {$host}:{$port}" . ($errstr !== '' ? " ({$errstr})" : '.')];
        }
        stream_set_timeout($socket, self::TIMEOUT_SECONDS);

        try {
            $banner = $this->readResponse($socket);
            if (! $this->isCode($banner, 220)) {
                return ['success' => false, 'message' => 'Unexpected server greeting: ' . trim($banner)];
            }

            $ehlo = $this->command($socket, 'EHLO ' . (gethostname() ?: 'localhost'));
            if (! $this->isCode($ehlo, 250)) {
                return ['success' => false, 'message' => 'Server rejected EHLO: ' . trim($ehlo)];
            }

            if ($encryption === 'tls') {
                $startTls = $this->command($socket, 'STARTTLS');
                if (! $this->isCode($startTls, 220)) {
                    return ['success' => false, 'message' => 'Server rejected STARTTLS: ' . trim($startTls)];
                }
                if (! @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return ['success' => false, 'message' => 'TLS handshake failed.'];
                }
                $ehlo = $this->command($socket, 'EHLO ' . (gethostname() ?: 'localhost'));
                if (! $this->isCode($ehlo, 250)) {
                    return ['success' => false, 'message' => 'Server rejected EHLO after STARTTLS: ' . trim($ehlo)];
                }
            }

            $auth = $this->command($socket, 'AUTH LOGIN');
            if (! $this->isCode($auth, 334)) {
                return ['success' => false, 'message' => 'Server does not support AUTH LOGIN: ' . trim($auth)];
            }

            $userReply = $this->command($socket, base64_encode($username));
            if (! $this->isCode($userReply, 334)) {
                return ['success' => false, 'message' => 'Username rejected: ' . trim($userReply)];
            }

            $passReply = $this->command($socket, base64_encode($password));
            if (! $this->isCode($passReply, 235)) {
                return ['success' => false, 'message' => 'Authentication failed: ' . trim($passReply)];
            }

            $this->command($socket, 'QUIT');

            return ['success' => true, 'message' => 'Connected and authenticated successfully. No email was sent.'];
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function command($socket, string $line): string
    {
        fwrite($socket, $line . "\r\n");

        return $this->readResponse($socket);
    }

    /**
     * SMTP multi-line replies use "250-text" for continuation lines and
     * "250 text" (space) for the final line -- keep reading until one
     * without the dash, or the connection times out.
     *
     * @param resource $socket
     */
    private function readResponse($socket): string
    {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        return $data;
    }

    private function isCode(string $response, int $code): bool
    {
        return str_starts_with($response, (string) $code);
    }
}
