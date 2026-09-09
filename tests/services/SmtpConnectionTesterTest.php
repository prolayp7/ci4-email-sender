<?php

namespace Tests\Services;

use App\Services\SmtpConnectionTester;
use CodeIgniter\Test\CIUnitTestCase;

final class SmtpConnectionTesterTest extends CIUnitTestCase
{
    public function testReportsFailureWhenServerIsUnreachable(): void
    {
        $result = (new SmtpConnectionTester())->test('127.0.0.1', 1, 'tls', 'user@example.com', 'secret');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Could not connect', $result['message']);
    }

    public function testReportsFailureWhenAuthenticationIsRejected(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available.');
        }

        $port = $this->withFakeSmtpServer(function ($conn): void {
            fwrite($conn, "220 fake.smtp ESMTP\r\n");
            fgets($conn); // EHLO
            fwrite($conn, "250 fake.smtp\r\n");
            fgets($conn); // AUTH LOGIN
            fwrite($conn, "334 VXNlcm5hbWU6\r\n");
            fgets($conn); // base64 username
            fwrite($conn, "334 UGFzc3dvcmQ6\r\n");
            fgets($conn); // base64 password
            fwrite($conn, "535 Authentication failed\r\n");
        });

        $result = (new SmtpConnectionTester())->test('127.0.0.1', $port, 'none', 'user@example.com', 'wrong-password');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Authentication failed', $result['message']);
    }

    public function testSucceedsAgainstAServerThatAcceptsAuth(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available.');
        }

        $port = $this->withFakeSmtpServer(function ($conn): void {
            fwrite($conn, "220 fake.smtp ESMTP\r\n");
            fgets($conn); // EHLO
            fwrite($conn, "250 fake.smtp\r\n");
            fgets($conn); // AUTH LOGIN
            fwrite($conn, "334 VXNlcm5hbWU6\r\n");
            fgets($conn); // base64 username
            fwrite($conn, "334 UGFzc3dvcmQ6\r\n");
            fgets($conn); // base64 password
            fwrite($conn, "235 Authentication successful\r\n");
            fgets($conn); // QUIT
            fwrite($conn, "221 Bye\r\n");
        });

        $result = (new SmtpConnectionTester())->test('127.0.0.1', $port, 'none', 'user@example.com', 'correct-password');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('No email was sent', $result['message']);
    }

    /**
     * Binds a listening socket on a free port, forks a child process that
     * accepts exactly one connection and runs $conversation against it, then
     * returns the port for the (parent-process) test to connect to as the
     * client. The child exits on its own once the conversation callback
     * returns; the parent doesn't wait for it (it's done talking to a single
     * client and has nothing left to do).
     *
     * The child is killed with SIGKILL rather than exit()/die(): fork()
     * duplicates every open file descriptor, including the parent PHPUnit
     * process's live MySQL connection socket. A normal exit() runs PHP's
     * shutdown sequence -- destructors included -- and the mysqli connection
     * object's destructor sends a MySQL QUIT packet over that *shared*
     * underlying socket, which the server honors by closing the connection
     * for both processes. The parent then fails its next query with "MySQL
     * server has gone away". SIGKILL bypasses shutdown functions and
     * destructors entirely, so the child can never touch it.
     */
    private function withFakeSmtpServer(callable $conversation): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $port   = (int) explode(':', stream_socket_get_name($server, false))[1];

        $pid = pcntl_fork();
        if ($pid === 0) {
            $conn = stream_socket_accept($server, 5);
            if ($conn) {
                $conversation($conn);
                fclose($conn);
            }
            fclose($server);
            if (function_exists('posix_kill')) {
                posix_kill(posix_getpid(), SIGKILL);
            }
            exit(0);
        }

        fclose($server);

        return $port;
    }
}
