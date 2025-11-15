<?php 

namespace Orryv\DockerManager;

use Orryv\DockerManager\Ports\FindNextPort;
use Orryv\Cmd;
use Orryv\DockerManagerOld;
use Orryv\XString;
use Orryv\XStringType;

class HelperOld
{
    public static function startContainer($name, $workdir, $compose_path, int|FindNextPort $port, $build_containers, $save_logs, $vars = []): int
    {
        Cmd::beginLive(1);
        $tmp_port = $port instanceof FindNextPort
            ? $port->getAvailablePort()
            : $port;
        $dm = new DockerManagerOld($workdir, $compose_path)
            ->setName($name)
            ->injectVariable('HOST_PORT', $tmp_port)
            ->onProgress(function($builds){
                $line = '  ';
                $in_progress = false;
                foreach($builds['containers'] ?? [] as $name => $status) {
                    $line .= "{$name}: {$status} | ";

                    if($status !== 'Started' && $status !== 'Running'){
                        $in_progress = true;
                    }
                }

                if(!empty($builds['containers']) && !$in_progress){
                    Cmd::updateLive(0, "  Starting up container...");
                    return;
                }

                if(!empty($line)){
                    $line .= '=> ';
                }
                
                // Create status
                $b = XString::new($builds['build_status'] ?? '');
                // if it matches ^#[int] \[int/int\]
                if($b->contains(XStringType::regex('/^#[0-9]+[ ]\[[0-9]+\/[0-9]+\]/'))){
                    // extract
                    $progress = $b->match(XStringType::regex('/^#[0-9]+[ ]\[[0-9]+\/[0-9]+\]/'));
                    $line .= $progress;
                } else {
                    // limit length to 25 chars and add ellipsis if longer
                    $line .= $b->trim()->limit(50, '...');
                }

                Cmd::updateLive(0, $line);
            });

        foreach($vars as $key => $value){
            $dm->injectVariable($key, $value);
        }
        $success = $dm->run($build_containers, $save_logs);

        Cmd::finishLive();

        if(!$success){
            if($dm->hasPortInUseError() && $port instanceof FindNextPort){
                Cmd::beginLive(1);
                do{
                    $tmp_port = $port->getAvailablePort($tmp_port);
                    Cmd::updateLive(0, "Failed previous port, trying " . $tmp_port . "...");
                    $dm->injectVariable('HOST_PORT', $tmp_port)
                        ->onProgress(null);
                    $success = $dm->run();
                    if(!$success && !$dm->hasPortInUseError()){
                        print_r($dm->getErrors());
                        throw new \Exception("Failed to start Docker containers for unknown reason (see above).");
                    }
                } while(!$success);
                Cmd::finishLive();
            } else {
                echo "Failed to start Docker containers." . PHP_EOL;
                print_r($dm->getErrors());
                throw new \Exception("Failed to start Docker containers for unknown reason (see above).");
            }
        }

        return $tmp_port;
    }
}