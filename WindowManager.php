<?php

namespace ScrapyardIO\Tubes\Windows;

use ScrapyardIO\Tubes\Canvas\OSWindow;
use ScrapyardIO\Tubes\Contracts\Windows\WindowFactory;
use ScrapyardIO\Tubes\Core\Support\CanvasProfiles;
use Throwable;

class WindowManager implements WindowFactory
{
    /**
     * @var array<string, callable(PendingWindow): WindowHandler>
     */
    protected array $drivers = [];

    /**
     * @var array<string, string> driver => PHP extension
     */
    protected array $extensions = [];

    /**
     * @var array<string, class-string> driver => handler class
     */
    protected array $classes = [];

    protected string $defaultDriver = 'sdl3';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->defaultDriver = $this->resolveConfiguredDefault('window', $this->defaultDriver);

        if ($config !== []) {
            $this->registerFromConfig($config);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function registerFromConfig(array $config): static
    {
        if (isset($config['default']) && is_string($config['default']) && $config['default'] !== '') {
            $this->defaultDriver = strtolower($config['default']);
        }

        $drivers = $config['drivers'] ?? [];

        if (is_array($drivers)) {
            foreach ($drivers as $name => $definition) {
                if (! is_string($name) || ! is_array($definition)) {
                    continue;
                }

                $this->registerDriverDefinition($name, $definition);
            }
        }

        return $this;
    }

    public function registerFromConfigDirectory(string $directory): static
    {
        if (! is_dir($directory)) {
            return $this;
        }

        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.php') ?: [];

        foreach ($files as $file) {
            $slug = strtolower(basename($file, '.php'));
            $definition = require $file;

            if (! is_array($definition)) {
                continue;
            }

            if (isset($definition['driver']) && is_string($definition['driver'])) {
                $slug = strtolower($definition['driver']);
            }

            $this->registerDriverDefinition($slug, $definition);
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function registerDriverDefinition(string $name, array $definition): static
    {
        $key = $this->normalize($name);
        $class = $definition['class'] ?? null;
        $extension = $definition['extension'] ?? null;

        if (is_string($extension) && $extension !== '') {
            $this->extensions[$key] = $extension;
        }

        if (is_string($class) && $class !== '') {
            $this->classes[$key] = $class;

            if (! class_exists($class)) {
                return $this;
            }

            $this->extend($key, $class);
        }

        return $this;
    }

    public function defaultDriver(): string
    {
        return $this->defaultDriver;
    }

    public function make(?string $driver = null): PendingWindow
    {
        return $this->driver($driver);
    }

    public function driver(?string $driver = null): PendingWindow
    {
        $name = $this->normalize($driver ?? $this->defaultDriver);

        if (! isset($this->drivers[$name])) {
            throw new WindowException("Window driver [{$name}] is not defined.");
        }

        return new PendingWindow($this, $name);
    }

    /**
     * Hydrate a PendingWindow from config('tubes.canvas_profiles.windows.*').
     *
     * Accepts a short slug (`metal-canvas`) or a dotted config path
     * (`tubes.canvas_profiles.windows.metal-canvas`).
     */
    public function profile(string $name): PendingWindow
    {
        try {
            $definition = CanvasProfiles::window($name);
        } catch (Throwable $exception) {
            throw new WindowException($exception->getMessage(), previous: $exception);
        }

        $driver = $definition['driver'] ?? $this->defaultDriver;
        if (! is_string($driver) || $driver === '') {
            throw new WindowException("Window profile [{$name}] must define a non-empty driver.");
        }

        $pending = $this->driver($driver);
        $this->hydratePendingFromProfile($pending, $definition);

        return $pending;
    }

    public function extend(string $name, callable|string $creator): static
    {
        $key = $this->normalize($name);

        if (is_string($creator)) {
            if (! is_a($creator, WindowHandler::class, true)) {
                throw new WindowException(
                    "Window handler class [{$creator}] must extend WindowHandler."
                );
            }

            $this->classes[$key] = $creator;
            $this->drivers[$key] = static function (PendingWindow $pending) use ($creator): WindowHandler {
                return new $creator(
                    $pending->titleValue(),
                    $pending->widthValue(),
                    $pending->heightValue(),
                );
            };

            return $this;
        }

        $this->drivers[$key] = $creator;

        return $this;
    }

    public function listWindows(): array
    {
        $names = array_keys($this->drivers);
        sort($names);

        return array_values($names);
    }

    public function createFromPending(PendingWindow $pending): OSWindow
    {
        $name = $pending->driver();
        $this->assertDriverReady($name);

        $creator = $this->drivers[$name] ?? null;

        if (is_null($creator)) {
            throw new WindowException("Window driver [{$name}] is not defined.");
        }

        $handler = $creator($pending);

        if (! ($handler instanceof WindowHandler)) {
            throw new WindowException(
                "Window creator [{$name}] must return a WindowHandler instance."
            );
        }

        return new OSWindow($handler);
    }

    protected function assertDriverReady(string $name): void
    {
        if (isset($this->classes[$name]) && ! class_exists($this->classes[$name])) {
            throw new WindowException(
                "Window handler class [{$this->classes[$name]}] for driver [{$name}] is not installed."
            );
        }

        if (isset($this->extensions[$name]) && ! extension_loaded($this->extensions[$name])) {
            throw new WindowException(
                "PHP extension [{$this->extensions[$name]}] is required for window driver [{$name}]."
            );
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function hydratePendingFromProfile(PendingWindow $pending, array $definition): void
    {
        if (isset($definition['title']) && is_string($definition['title']) && $definition['title'] !== '') {
            $pending->title($definition['title']);
        }

        [$width, $height] = $this->profileResolution($definition);

        if (! is_null($width) && ! is_null($height)) {
            $pending->size($width, $height);
        } else {
            if (! is_null($width)) {
                $pending->width($width);
            }
            if (! is_null($height)) {
                $pending->height($height);
            }
        }

        $options = [];
        if (isset($definition['options']) && is_array($definition['options'])) {
            $options = $definition['options'];
        }

        $reserved = ['driver' => true, 'title' => true, 'width' => true, 'height' => true, 'resolution' => true, 'options' => true];
        foreach ($definition as $key => $value) {
            if (! is_string($key) || isset($reserved[$key])) {
                continue;
            }
            $options[$key] = $value;
        }

        if ($options !== []) {
            $pending->options($options);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{0: int|null, 1: int|null}
     */
    protected function profileResolution(array $definition): array
    {
        $width = isset($definition['width']) && is_numeric($definition['width'])
            ? (int) $definition['width']
            : null;
        $height = isset($definition['height']) && is_numeric($definition['height'])
            ? (int) $definition['height']
            : null;

        if ((! is_null($width) && ! is_null($height)) || ! isset($definition['resolution'])) {
            return [$width, $height];
        }

        $resolution = $definition['resolution'];

        if (is_array($resolution) && count($resolution) >= 2) {
            return [(int) $resolution[0], (int) $resolution[1]];
        }

        if (is_string($resolution) && preg_match('/^\s*(\d+)\s*[xX]\s*(\d+)\s*$/', $resolution, $matches) === 1) {
            return [(int) $matches[1], (int) $matches[2]];
        }

        return [$width, $height];
    }

    protected function resolveConfiguredDefault(string $alias, string $fallback): string
    {
        // Window *driver* slug comes from windows.default only.
        // tubes.defaults.canvas is a profile slug (windows.* or panels.*), not a driver.
        if ($alias === 'window') {
            return strtolower($fallback);
        }

        if (function_exists('config')) {
            $fromTubes = config("tubes.defaults.{$alias}");
            if (is_string($fromTubes) && $fromTubes !== '') {
                return strtolower($fromTubes);
            }
        }

        return strtolower($fallback);
    }

    /**
     * @return non-empty-string
     */
    protected function normalize(string $driver): string
    {
        $name = strtolower($driver);

        if ($name === '') {
            throw new WindowException('Window driver name must be non-empty.');
        }

        return $name;
    }
}
