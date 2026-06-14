<?php

declare(strict_types=1);

namespace AnimalId\PartnerSdk\Tests\Integration;

use AnimalId\PartnerSdk\Exception\TransportException;
use AnimalId\PartnerSdk\Http\CurlHttpClient;
use AnimalId\PartnerSdk\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real cURL transport against a local PHP built-in server.
 */
final class CurlHttpClientTest extends TestCase
{
    /** @var resource|null */
    private static $serverProcess;

    /** @var string */
    private static $baseUrl;

    public static function setUpBeforeClass(): void
    {
        $port = self::findFreePort();
        self::$baseUrl = 'http://127.0.0.1:' . $port;

        $command = sprintf(
            '%s -S 127.0.0.1:%d %s',
            escapeshellarg(PHP_BINARY),
            $port,
            escapeshellarg(__DIR__ . '/../Fixtures/server.php')
        );

        self::$serverProcess = proc_open(
            $command,
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );

        if (!is_resource(self::$serverProcess) || !self::waitForServer($port)) {
            self::markTestSkipped('Unable to start the PHP built-in server.');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
    }

    public function testGetReceivesStatusHeadersAndBody(): void
    {
        $client = new CurlHttpClient(5, 2);

        $response = $client->send(new Request(
            'GET',
            self::$baseUrl . '/v1/partner/dictionaries?lang=uk',
            ['X-Eternity-App-Id' => 'aid_app_test']
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($response->isSuccessful());
        self::assertSame('1', $response->getHeader('X-Echo-Server'));

        $echo = $response->json();
        self::assertSame('GET', $echo['method']);
        self::assertSame('/v1/partner/dictionaries?lang=uk', $echo['uri']);
        self::assertSame('aid_app_test', $echo['headers']['x-eternity-app-id']);
    }

    public function testPostSendsExactBodyBytes(): void
    {
        $client = new CurlHttpClient(5, 2);
        $body = '{"nickname":"Барсік"}';

        $response = $client->send(new Request(
            'POST',
            self::$baseUrl . '/v1/partner/animals',
            ['Content-Type' => 'application/json'],
            $body
        ));

        $echo = $response->json();
        self::assertSame('POST', $echo['method']);
        self::assertSame($body, $echo['body']);
    }

    public function testPatchAndDeleteUseTheConfiguredVerb(): void
    {
        $client = new CurlHttpClient(5, 2);

        $patch = $client->send(new Request('PATCH', self::$baseUrl . '/x', [], '{"a":1}'));
        self::assertSame('PATCH', $patch->json()['method']);

        $delete = $client->send(new Request('DELETE', self::$baseUrl . '/x'));
        self::assertSame('DELETE', $delete->json()['method']);
    }

    public function testMultipartUploadsFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sdk_upload_');
        file_put_contents($tmpFile, str_repeat('x', 1024));

        try {
            $client = new CurlHttpClient(5, 2);
            $response = $client->send(new Request(
                'POST',
                self::$baseUrl . '/v1/partner/animals/a1/photos',
                [],
                null,
                ['file' => new \CURLFile($tmpFile, 'image/jpeg', 'photo.jpg'), 'kind' => 'avatar']
            ));

            $echo = $response->json();
            self::assertSame('avatar', $echo['post']['kind']);
            self::assertSame('photo.jpg', $echo['files']['file']['name']);
            self::assertSame(1024, $echo['files']['file']['size']);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testErrorStatusIsReturnedNotThrown(): void
    {
        $client = new CurlHttpClient(5, 2);

        $response = $client->send(new Request('GET', self::$baseUrl . '/status/404'));

        self::assertSame(404, $response->getStatusCode());
        self::assertFalse($response->isSuccessful());
    }

    public function testConnectionFailureThrowsTransportException(): void
    {
        $client = new CurlHttpClient(2, 1);

        $this->expectException(TransportException::class);

        // A free port nobody listens on.
        $client->send(new Request('GET', 'http://127.0.0.1:' . self::findFreePort() . '/'));
    }

    public function testTimeoutThrowsTransportException(): void
    {
        $client = new CurlHttpClient(1, 1);

        $this->expectException(TransportException::class);

        $client->send(new Request('GET', self::$baseUrl . '/slow'));
    }

    private static function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private static function waitForServer(int $port): bool
    {
        for ($i = 0; $i < 50; $i++) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if (is_resource($connection)) {
                fclose($connection);

                return true;
            }
            usleep(100000);
        }

        return false;
    }
}
