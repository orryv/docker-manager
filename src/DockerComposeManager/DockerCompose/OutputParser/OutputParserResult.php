<?php

namespace Orryv\DockerComposeManager\DockerCompose\OutputParser;

/**
 * Immutable value object representing parsed docker-compose output for a single execution.
 */
class OutputParserResult implements OutputParserResultInterface
{
    private string $id;

    /** @var array<string, string> */
    private array $containerStates;

    /** @var array<string, bool> */
    private array $containerSuccess;

    /** @var array<string, string> */
    private array $networkStates;

    /** @var array<string, bool> */
    private array $networkSuccess;

    private ?string $buildLastLine;

    /** @var string[] */
    private array $errors;

    private bool $containersRunning;

    /**
     * @param array<string, string> $containerStates
     * @param array<string, bool> $containerSuccess
     * @param array<string, string> $networkStates
     * @param array<string, bool> $networkSuccess
     * @param string[] $errors
     */
    public function __construct(
        string $id,
        array $containerStates,
        array $containerSuccess,
        array $networkStates,
        array $networkSuccess,
        array $errors,
        ?string $buildLastLine,
        bool $containersRunning
    ) {
        $this->id = $id;
        $this->containerStates = $containerStates;
        $this->containerSuccess = $containerSuccess;
        $this->networkStates = $networkStates;
        $this->networkSuccess = $networkSuccess;
        $this->errors = $errors;
        $this->buildLastLine = $buildLastLine;
        $this->containersRunning = $containersRunning;
    }

    /**
     * Identifier for the docker-compose definition.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Whether container startup reached a terminal state (running or ended due to an error).
     */
    public function areContainersRunning(): bool
    {
        return $this->containersRunning;
    }

    /**
     * State of a container if known.
     */
    public function getContainerState(string $containerName): ?string
    {
        return $this->containerStates[$containerName] ?? null;
    }

    /**
     * All container states keyed by name.
     *
     * @return array<string, string>
     */
    public function getContainerStates(): array
    {
        return $this->containerStates;
    }

    /**
     * Whether a specific container reached a successful state.
     */
    public function isContainerSuccessful(string $containerName): bool
    {
        return $this->containerSuccess[$containerName] ?? false;
    }

    /**
     * Success map for all containers keyed by name.
     *
     * @return array<string, bool>
     */
    public function getContainerSuccess(): array
    {
        return $this->containerSuccess;
    }

    /**
     * Whether a specific network reached a successful state.
     */
    public function isNetworkSuccessful(string $networkName): bool
    {
        return $this->networkSuccess[$networkName] ?? false;
    }

    /**
     * Success map for all networks keyed by name.
     *
     * @return array<string, bool>
     */
    public function getNetworkSuccess(): array
    {
        return $this->networkSuccess;
    }

    /**
     * Whether the execution as a whole is successful (all containers succeeded and no errors recorded).
     */
    public function isSuccessful(): bool
    {
        foreach ($this->containerSuccess as $successful) {
            if (!$successful) {
                return false;
            }
        }

        foreach ($this->networkSuccess as $successful) {
            if (!$successful) {
                return false;
            }
        }

        return !$this->hasErrors();
    }

    /**
     * State of a network if known.
     */
    public function getNetworkState(string $networkName): ?string
    {
        return $this->networkStates[$networkName] ?? null;
    }

    /**
     * All network states keyed by name.
     *
     * @return array<string, string>
     */
    public function getNetworkStates(): array
    {
        return $this->networkStates;
    }

    /**
     * Last build line for progress reporting.
     */
    public function getBuildLastLine(): ?string
    {
        return $this->buildLastLine;
    }

    /**
     * Errors recorded during parsing.
     *
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Whether any errors were captured.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}
