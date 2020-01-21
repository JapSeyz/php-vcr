<?php

namespace VCR;

use VCR\Util\Assertion;

/**
 * Configuration stores a Videorecorders configuration options.
 *
 * Those configuration options might be:
 *  - which LibraryHook to use,
 *  - where to store cassettes or
 *  - which files to scan when filtering source code.
 */
class Configuration
{
    /**
     * @var string Path where cassette files should be stored.
     */
    private $cassettePath = 'tests/fixtures';

    /**
     * List of enabled LibraryHook names.
     *
     * A value of null means all hooks are enabled.
     *
     * @see \VCR\LibraryHooks\LibraryHook
     * @var array|null List of enabled LibraryHook names.
     */
    private $enabledLibraryHooks;

    /**
     * List of library hooks.
     *
     * Format:
     * [
     *  'name' => 'class name'
     * ]
     * @var string[] List of library hooks.
     */
    private $availableLibraryHooks = [
        'stream_wrapper' => 'VCR\LibraryHooks\StreamWrapperHook',
        'curl'           => 'VCR\LibraryHooks\CurlHook',
        'soap'           => 'VCR\LibraryHooks\SoapHook',
    ];

    /**
     * Name of the enabled storage.
     *
     * Only one storage can be enabled at a time.
     * By default YAML is enabled.
     *
     * @var string Enabled storage name.
     */
    private $enabledStorage = 'yaml';

    /**
     * List of enabled storages.
     *
     * Format:
     * [
     *  'name' => 'class name'
     * ]
     *
     * @var string[] List of available storages.
     */
    private $availableStorages = [
        'blackhole' => 'VCR\Storage\Blackhole',
        'json'      => 'VCR\Storage\Json',
        'yaml'      => 'VCR\Storage\Yaml',
    ];

    /**
     * A value of null means all RequestMatchers are enabled.
     *
     * @var array Names of the RequestMatchers which are enabled.
     */
    private $enabledRequestMatchers;

    /**
     * Format:
     * [
     *  'name' => callback
     * ]
     *
     * The RequestMatcher callback takes two Request objects and
     * returns true if they match or false otherwise.
     *
     * @var array<string,callable(Request $first, Request $second):bool> List of RequestMatcher names and callbacks.
     */
    private $availableRequestMatchers = [
        'method'         => [RequestMatcher::class, 'matchMethod'],
        'url'            => [RequestMatcher::class, 'matchUrl'],
        'host'           => [RequestMatcher::class, 'matchHost'],
        'headers'        => [RequestMatcher::class, 'matchHeaders'],
        'body'           => [RequestMatcher::class, 'matchBody'],
        'post_fields'    => [RequestMatcher::class, 'matchPostFields'],
        'query_string'   => [RequestMatcher::class, 'matchQueryString'],
        'soap_operation' => [RequestMatcher::class, 'matchSoapOperation'],
    ];

    /**
     * A whitelist is a list of paths.
     *
     * When processing files for code transformation, only files matching
     * those paths are considered. By default the whitelist is empty and
     * all files (which are not blacklisted) are being considered for
     * code transformation.
     *
     * @var string[] A whitelist is a list of paths.
     */
    private $whiteList = [];

    /**
     * A blacklist is a list of paths which may not be processed for code transformation.
     *
     * Files in this path are left as is. Blacklisting PHP-VCRs own paths is necessary
     * to avoid infinite loops.
     *
     * @var string[] A blacklist is a list of paths.
     */
    private $blackList = ['src/VCR/LibraryHooks/', 'src/VCR/Util/SoapClient', 'tests/VCR/Filter'];

    /**
     * The mode which determines how requests are handled. One of the MODE constants.
     *
     * @var string Current mode
     */
    private $mode = VCR::MODE_NEW_EPISODES;

    /**
     * List of available modes.
     *
     * Format:
     * [
     *  'name'
     * ]
     *
     * @var array List of available modes.
     */
    private $availableModes = [
        VCR::MODE_NEW_EPISODES,
        VCR::MODE_ONCE,
        VCR::MODE_NONE,
    ];

    /**
     * Returns the current blacklist.
     *
     * @return string[]
     */
    public function getBlackList(): array
    {
        return $this->blackList;
    }

    /**
     * Sets one or more paths to blacklist.
     *
     * @param string|string[] $paths Path(s) to blacklist.
     *
     * @return Configuration
     */
    public function setBlackList($paths): self
    {
        $paths = (is_array($paths)) ? $paths : [$paths];

        $this->blackList = $paths;

        return $this;
    }

    /**
     * Returns the path to where cassettes are stored.
     *
     * @return string Path to where cassettes are stored.
     */
    public function getCassettePath(): string
    {
        $this->assertValidCassettePath($this->cassettePath);

        return $this->cassettePath;
    }

    /**
     * Sets the cassette path where a cassettes should be stored in.
     *
     * @param string $cassettePath Path where to store cassettes.
     *
     * @return Configuration
     * @throws VCRException If provided cassette path is invalid.
     */
    public function setCassettePath(string $cassettePath): self
    {
        $this->assertValidCassettePath($cassettePath);
        $this->cassettePath = $cassettePath;

        return $this;
    }

    /**
     * Returns a list of enabled LibraryHook class names.
     *
     * Only class names are returned, any object creation happens
     * in the VCRFactory.
     *
     * @return string[] List of LibraryHook class names.
     */
    public function getLibraryHooks(): array
    {
        if ($this->enabledLibraryHooks === null) {
            return array_values($this->availableLibraryHooks);
        }

        return array_values(array_intersect_key(
            $this->availableLibraryHooks,
            array_flip($this->enabledLibraryHooks)
        ));
    }

    /**
     * Enables specified LibraryHook(s) by its name.
     *
     * @param string|string[] $hooks Name of the LibraryHook(s) to enable.
     * @return Configuration
     * @throws \InvalidArgumentException If a specified library hook doesn't exist.
     */
    public function enableLibraryHooks($hooks): self
    {
        $hooks = is_array($hooks) ? $hooks : [$hooks];
        $invalidHooks = array_diff($hooks, array_keys($this->availableLibraryHooks));
        if ($invalidHooks) {
            throw new \InvalidArgumentException("Library hooks don't exist: " . join(', ', $invalidHooks));
        }
        $this->enabledLibraryHooks = $hooks;

        return $this;
    }

    /**
     * Returns the class name of the storage to use.
     *
     * Objects are created in the VCRFactory.
     *
     * @return string Class name of the storage to use.
     */
    public function getStorage(): string
    {
        return $this->availableStorages[$this->enabledStorage];
    }

    /**
     * Returns a list of enabled RequestMatcher callbacks.
     *
     * @return callable[] List of enabled RequestMatcher callbacks.
     */
    public function getRequestMatchers(): array
    {
        if ($this->enabledRequestMatchers === null) {
            return array_values($this->availableRequestMatchers);
        }

        return array_values(array_intersect_key(
            $this->availableRequestMatchers,
            array_flip($this->enabledRequestMatchers)
        ));
    }

    /**
     * Adds a new RequestMatcher callback.
     *
     * @param string $name Name of the RequestMatcher.
     * @param callable $callback A callback taking two Request objects as parameters and returns true if those match.
     *
     * @return Configuration
     * @throws VCRException If specified parameters are invalid.
     */
    public function addRequestMatcher(string $name, callable $callback): self
    {
        Assertion::minLength($name, 1, "A request matchers name must be at least one character long. Found ''");
        $this->availableRequestMatchers[$name] = $callback;

        return $this;
    }

    /**
     * Enables specified RequestMatchers by its name.
     *
     * @param string[] $matchers List of RequestMatcher names to enable.
     *
     * @return Configuration
     *
     * @throws \InvalidArgumentException If a specified request matcher does not exist.
     */
    public function enableRequestMatchers(array $matchers): self
    {
        $invalidMatchers = array_diff($matchers, array_keys($this->availableRequestMatchers));
        if ($invalidMatchers) {
            throw new \InvalidArgumentException("Request matchers don't exist: " . join(', ', $invalidMatchers));
        }
        $this->enabledRequestMatchers = $matchers;
        
        return $this;
    }

    /**
     * Enables a storage by name.
     *
     * @param string $storageName Name of the storage to enable.
     *
     * @return self
     * @throws VCRException If a invalid storage name is given.
     */
    public function setStorage(string $storageName): self
    {
        Assertion::keyExists($this->availableStorages, $storageName, "Storage '{$storageName}' not available.");
        $this->enabledStorage = $storageName;

        return $this;
    }

    /**
      * Returns a list of whitelisted paths.
      *
      * @return string[]
      */
    public function getWhiteList(): array
    {
        return $this->whiteList;
    }

    /**
     * Sets a list of paths to whitelist when processing in the StreamProcessor.
     *
     * @param string|array $paths Single path or list of path which are whitelisted.
     *
     * @return Configuration
     */
    public function setWhiteList($paths): Configuration
    {
        $paths = (is_array($paths)) ? $paths : [$paths];

        $this->whiteList = $paths;

        return $this;
    }

    /**
      * Returns the current mode.
      *
      * @return string
      */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Sets the current mode.
     *
     * @param string $mode The mode to set VCR to
     *
     * @return Configuration
     */
    public function setMode(string $mode): Configuration
    {
        Assertion::choice($mode, $this->availableModes, "Mode '{$mode}' does not exist.");
        $this->mode = $mode;

        return $this;
    }

    /**
     * Validates a specified cassette path.
     *
     * @param string $cassettePath Path to a cassette.
     * @throws VCRException If cassette path is invalid.
     */
    private function assertValidCassettePath(string $cassettePath): void
    {
        Assertion::directory(
            $cassettePath,
            "Cassette path '{$cassettePath}' is not a directory. Please either "
            . 'create it or set a different cassette path using '
            . "\\VCR\\VCR::configure()->setCassettePath('directory')."
        );
    }
}
