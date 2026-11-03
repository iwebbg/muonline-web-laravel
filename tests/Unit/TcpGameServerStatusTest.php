<?php

namespace Tests\Unit;

use App\Game\Status\TcpGameServerStatus;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies that public server status follows the configured player TCP endpoint.
 */
final class TcpGameServerStatusTest extends TestCase
{
    /**
     * Confirms an accepting game server endpoint is reported online.
     */
    public function test_accepting_endpoint_is_online(): void
    {
        $errorCode = 0;
        $errorMessage = '';
        $server = stream_socket_server(
            'tcp://127.0.0.1:0',
            $errorCode,
            $errorMessage,
        );

        if (! is_resource($server)) {
            throw new RuntimeException('Unable to create the local TCP test server.');
        }

        try {
            $address = stream_socket_get_name($server, false);
            $port = is_string($address)
                ? parse_url('tcp://'.$address, PHP_URL_PORT)
                : null;

            $this->assertIsInt($port);
            $this->configureEndpoint($port);
            $this->assertTrue($this->statusReader()->isOnline());
        } finally {
            fclose($server);
        }
    }

    /**
     * Confirms a closed game server endpoint is reported offline.
     */
    public function test_closed_endpoint_is_offline(): void
    {
        $errorCode = 0;
        $errorMessage = '';
        $server = stream_socket_server(
            'tcp://127.0.0.1:0',
            $errorCode,
            $errorMessage,
        );

        if (! is_resource($server)) {
            throw new RuntimeException('Unable to reserve the local TCP test port.');
        }

        $address = stream_socket_get_name($server, false);
        $port = is_string($address)
            ? parse_url('tcp://'.$address, PHP_URL_PORT)
            : null;

        fclose($server);

        $this->assertIsInt($port);
        $this->configureEndpoint($port);
        $this->assertFalse($this->statusReader()->isOnline());
    }

    /**
     * Applies one isolated TCP endpoint to the status reader configuration.
     */
    private function configureEndpoint(int $port): void
    {
        config()->set('game.server_status.host', '127.0.0.1');
        config()->set('game.server_status.port', $port);
        config()->set('game.server_status.timeout_seconds', 0.2);
        config()->set('game.server_status.cache_seconds', 1);
    }

    /**
     * Creates a status reader with an isolated in-memory cache.
     */
    private function statusReader(): TcpGameServerStatus
    {
        return new TcpGameServerStatus(
            new Repository(new ArrayStore),
        );
    }
}
