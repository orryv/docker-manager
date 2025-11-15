<?php

namespace Orryv\DockerManager\Process;

interface ProcessRunnerInterface
{
    public function run(ProcessContext $context, callable $tick): ProcessResult;
}
