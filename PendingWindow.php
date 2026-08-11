<?php

namespace ScrapyardIO\Tubes\Windows;

use ScrapyardIO\Tubes\Canvas\OSWindow;

/**
 * Fluent builder for a registered window driver.
 *
 * Usage:
 *   Window::driver('sdl3')->title('Demo')->size(800, 600)->create()
 *   Window::driver('sdl3')->title('Demo')->size(800, 600)->open()
 */
class PendingWindow
{
    /**
     * @var array<string, mixed>
     */
    protected array $options = [];

    protected ?string $title = null;

    protected ?int $width = null;

    protected ?int $height = null;

    /**
     * @param  non-empty-string  $driver
     */
    public function __construct(
        protected WindowManager $manager,
        protected string $driver,
    ) {}

    public function driver(): string
    {
        return $this->driver;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function size(int $width, int $height): static
    {
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    public function width(int $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function height(int $height): static
    {
        $this->height = $height;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function options(array $options): static
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    public function option(string $key, mixed $value): static
    {
        $this->options[$key] = $value;

        return $this;
    }

    public function titleValue(): string
    {
        if (is_null($this->title) || $this->title === '') {
            throw new WindowException('Window title is required.');
        }

        return $this->title;
    }

    public function widthValue(): int
    {
        if (is_null($this->width) || $this->width < 1) {
            throw new WindowException('Window width is required and must be >= 1.');
        }

        return $this->width;
    }

    public function heightValue(): int
    {
        if (is_null($this->height) || $this->height < 1) {
            throw new WindowException('Window height is required and must be >= 1.');
        }

        return $this->height;
    }

    /**
     * @return array<string, mixed>
     */
    public function optionsValue(): array
    {
        return $this->options;
    }

    public function create(): OSWindow
    {
        return $this->manager->createFromPending($this);
    }

    /**
     * Create and immediately open the native window.
     */
    public function open(): OSWindow
    {
        return $this->create()->open();
    }

    /**
     * Alias for {@see create()}.
     */
    public function get(): OSWindow
    {
        return $this->create();
    }
}
