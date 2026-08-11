<?php

namespace ScrapyardIO\Tubes\Windows;

use ScrapyardIO\Tubes\Contracts\Framebuffers\DeferredFramebuffer;
use ScrapyardIO\Tubes\Contracts\Framebuffers\FormatSpec;

/**
 * Engine-owned OS window driver.
 *
 * Companions (sdl3-gfx, ogx, metal-gfx, vulkan-gfx, cuda-gfx) extend this class. The
 * handler defines its host {@see FormatSpec} at construction — matching the
 * package DeferredFramebuffer — so windowed present never muxes mismatched
 * packings or requires a PHP-land {@see DeferredFramebuffer::flush()} path.
 *
 * Lifecycle: construct → {@see open()} (native window + bound framebuffer) →
 * draw via {@see framebuffer()} → {@see present()} / {@see pollEvents()} →
 * {@see close()}.
 */
abstract class WindowHandler
{
    protected FormatSpec $format_spec;

    protected ?DeferredFramebuffer $framebuffer = null;

    protected bool $opened = false;

    public function __construct(
        protected string $title,
        protected int $width,
        protected int $height,
    ) {
        if ($width < 1 || $height < 1) {
            throw new WindowException("Window size must be positive, got {$width}x{$height}.");
        }

        $this->format_spec = $this->defineFormatSpec();
    }

    /**
     * Host packing for this engine's window framebuffer (fixed per companion).
     */
    abstract protected function defineFormatSpec(): FormatSpec;

    /**
     * Create / show the native window and make the engine context current.
     */
    abstract protected function bootNative(): void;

    /**
     * Bind (or create) the package DeferredFramebuffer to the native drawable.
     *
     * Prefer {@see DeferredFramebuffer} `attachedTo(...)` so pixels stay in the
     * engine — no PHP PixelStore mirror required for present.
     */
    abstract protected function bindFramebuffer(): DeferredFramebuffer;

    /**
     * Engine present / swap. Must not require packing through PHP flush.
     */
    abstract protected function presentNative(): void;

    /**
     * Pump the engine event queue (required for macOS visibility).
     */
    abstract protected function pollNative(): void;

    /**
     * True when the user requested close (or the native window is gone).
     */
    abstract public function shouldClose(): bool;

    /**
     * Destroy the native window / context. Idempotent.
     */
    abstract protected function destroyNative(): void;

    public function title(): string
    {
        return $this->title;
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }

    public function formatSpec(): FormatSpec
    {
        return $this->format_spec;
    }

    public function isOpen(): bool
    {
        return $this->opened;
    }

    /**
     * @throws WindowException
     */
    public function open(): static
    {
        if ($this->opened) {
            throw WindowException::alreadyOpen();
        }

        $this->bootNative();
        $this->framebuffer = $this->bindFramebuffer();
        $this->opened = true;

        return $this;
    }

    /**
     * Bound deferred framebuffer (engine-owned pixels).
     *
     * @throws WindowException
     */
    public function framebuffer(): DeferredFramebuffer
    {
        if (! $this->opened || is_null($this->framebuffer)) {
            throw WindowException::notOpen('framebuffer()');
        }

        return $this->framebuffer;
    }

    /**
     * Present the drawable. Bypasses PHP FormatSpec packing / flush.
     *
     * @throws WindowException
     */
    public function present(): static
    {
        if (! $this->opened) {
            throw WindowException::notOpen('present()');
        }

        $this->presentNative();

        return $this;
    }

    /**
     * @throws WindowException
     */
    public function pollEvents(): static
    {
        if (! $this->opened) {
            throw WindowException::notOpen('pollEvents()');
        }

        $this->pollNative();

        return $this;
    }

    public function close(): static
    {
        if (! $this->opened) {
            return $this;
        }

        $this->destroyNative();
        $this->framebuffer = null;
        $this->opened = false;

        return $this;
    }

    public function __destruct()
    {
        $this->close();
    }
}
