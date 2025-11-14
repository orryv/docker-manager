<?php 

namespace Orryv\DockerManager;

use Orryv\DockerManager\PortFinder;

class FindNextPort
{
    private array $preferred_ports = [];
    private int $fallback_start_port = 8000;
    private int $fallback_end_port = 9000;

    public function __construct(array $preferred_ports = [], int $fallback_start_port = 8000, int $fallback_end_port = 9000)
    {
        $this->preferred_ports = $preferred_ports;
        $this->fallback_start_port = $fallback_start_port;
        $this->fallback_end_port = $fallback_end_port;
    }

    public function getAvailablePort(?int $last_failed = null): ?int
    {
        if ($last_failed !== null) {
            if(in_array($last_failed, $this->preferred_ports, true)){
                // remove from preferred ports
                $index = array_search($last_failed, $this->preferred_ports, true);
                unset($this->preferred_ports[$index]);
                $last_failed = null;
            }
        }

        // Check preferred ports first
        foreach ($this->preferred_ports as $key => $port) {
            // PortFinder can fail to detect an unavailable port sometimes
            if(!PortFinder::isPortFree($port)){
                unset($this->preferred_ports[$key]);
                continue;
            }

            return $port;
        }

        // Fallback to range
        for ($port = $this->fallback_start_port; $port <= $this->fallback_end_port; $port++) {
            if($last_failed !== null && $port <= $last_failed){
                continue;
            }

            if (PortFinder::isPortFree($port)) {
                return $port;
            }
        }

        return null; // No available port found
    }
}