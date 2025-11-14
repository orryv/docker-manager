<?php 

namespace Orryv\DockerManager;

use RuntimeException;

/**
 * PortFinder
 *
 * Finds an available (not-in-use) TCP port on the host.
 * - Tries a list of candidate ports first.
 * - If none are free, scans from $start up to $end.
 *
 * Works on Windows, macOS, and Linux by attempting to bind a socket.
 */
final class PortFinder
{
    /**
     * Check if a TCP port is available by attempting to bind to it.
     *
     * @param int    $port  Port number (1..65535)
     * @param string $host  Interface to bind to (default all IPv4)
     * @return bool
     */
    public static function isPortFree(int $port, string $host = '0.0.0.0'): bool
    {
        if ($port < 1 || $port > 65535) {
            return false;
        }

        // Bind to IPv4 “all interfaces”. If another process is listening, this fails.
        $address = sprintf('tcp://%s:%d', $host, $port);

        $ctx = stream_context_create([
            'socket' => [
                // Helps avoid TIME_WAIT quirks without allowing binding over an active listener.
                'so_reuseaddr' => true,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $server = @stream_socket_server(
            $address,
            $errno,
            $errstr,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $ctx
        );

        if ($server === false) {
            return false; // Port is in use (or not bindable on this interface)
        }

        // Successfully bound -> port considered free. Close immediately.
        @fclose($server);

        // Optional: also try IPv6 if your environment needs it.
        // Commented out by default to avoid false negatives on systems without IPv6.
        // $server6 = @stream_socket_server("tcp://[::]:$port", $e6, $s6, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $ctx);
        // if ($server6 !== false) { @fclose($server6); }

        return true;
    }

    /**
     * Find an available TCP port.
     *
     * @param int[]  $preferredPorts  Ports to try first, in order.
     * @param int    $start           If none of $preferredPorts are free, scan from here upward.
     * @param int    $end             Inclusive upper bound for the scan.
     * @param string $host            Interface to test/bind (default all IPv4).
     * @return int                    The first free port found.
     * @throws RuntimeException       If no free port is found in the range.
     */
    public static function findOpenPort(
        array $preferredPorts,
        int $start,
        int $end = 65535,
        string $host = '0.0.0.0'
    ): int {
        // Try preferred ports first (dedup + keep order of first occurrence)
        $seen = [];
        foreach ($preferredPorts as $p) {
            if (!is_int($p) || $p < 1 || $p > 65535) {
                continue;
            }
            if (isset($seen[$p])) {
                continue;
            }
            $seen[$p] = true;

            if (self::isPortFree($p, $host)) {
                return $p;
            }
        }

        // Then scan from $start to $end
        if ($start < 1)   { $start = 1; }
        if ($end > 65535) { $end   = 65535; }
        if ($start > $end) {
            throw new RuntimeException('Invalid port scan range: start > end.');
        }

        for ($port = $start; $port <= $end; $port++) {
            if (self::isPortFree($port, $host)) {
                return $port;
            }
        }

        throw new RuntimeException("No free port found between {$start} and {$end}.");
    }

    /**
     * Reserve an available port by keeping the listening socket open.
     * Useful to avoid race conditions between discovery and docker run.
     *
     * @param int[]  $preferredPorts
     * @param int    $start
     * @param int    $end
     * @param string $host
     * @return array{port:int, socket:resource}
     * @throws RuntimeException
     */
    public static function reserveOpenPort(
        array $preferredPorts,
        int $start,
        int $end = 65535,
        string $host = '0.0.0.0'
    ): array {
        $ctx = stream_context_create(['socket' => ['so_reuseaddr' => true]]);

        // Try preferred first
        $tryReserve = function (int $port) use ($host, $ctx) {
            $addr = sprintf('tcp://%s:%d', $host, $port);
            $errno = 0; $errstr = '';
            $sock = @stream_socket_server($addr, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $ctx);
            return $sock !== false ? $sock : null;
        };

        $seen = [];
        foreach ($preferredPorts as $p) {
            if (!is_int($p) || $p < 1 || $p > 65535 || isset($seen[$p])) {
                continue;
            }
            $seen[$p] = true;
            $sock = $tryReserve($p);
            if ($sock) {
                return ['port' => $p, 'socket' => $sock];
            }
        }

        // Then scan the range
        if ($start < 1)   { $start = 1; }
        if ($end > 65535) { $end   = 65535; }
        if ($start > $end) {
            throw new RuntimeException('Invalid port scan range: start > end.');
        }

        for ($port = $start; $port <= $end; $port++) {
            $sock = $tryReserve($port);
            if ($sock) {
                return ['port' => $port, 'socket' => $sock];
            }
        }

        throw new RuntimeException("No free port found between {$start} and {$end}.");
    }

    /**
     * Release a previously reserved port socket (from reserveOpenPort()).
     *
     * @param resource|null $socket
     * @return void
     */
    public static function release($socket): void
    {
        if (is_resource($socket)) {
            @fclose($socket);
        }
    }
}
