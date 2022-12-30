<?php

namespace Stevebauman\Location\Drivers;

use Illuminate\Support\Fluent;
use Stevebauman\Location\Position;
use Stevebauman\Location\Requestable;

abstract class Driver
{
    /**
     * The fallback driver.
     *
     * @var Driver|null
     */
    protected $fallback;

    /**
     * Append a fallback driver to the end of the chain.
     *
     * @param Driver $handler
     */
    public function fallback(Driver $handler)
    {
        if (is_null($this->fallback)) {
            $this->fallback = $handler;
        } else {
            $this->fallback->fallback($handler);
        }
    }

    /**
     * Get a position from the request.
     */
    public function get(Requestable $request): Position|false
    {
        $data = $this->process($request);

        $position = $this->makePosition();

        // Here we will ensure the location's data we received isn't empty.
        // Some IP location providers will return empty JSON. We want
        // to avoid this, so we can call the next fallback driver.
        if ($data instanceof Fluent && ! $this->isEmpty($data)) {
            $position = $this->hydrate($position, $data);

            $position->ip = $request->ip();
            $position->driver = get_class($this);
        }

        if (! $position->isEmpty()) {
            return $position;
        }

        return $this->fallback ? $this->fallback->get($request) : false;
    }

    /**
     * Hydrate the Position object with the given location data.
     */
    abstract protected function hydrate(Position $position, Fluent $location): Position;

    /**
     * Attempt to fetch and process the location data from the driver.
     */
    abstract protected function process(Requestable $request): Fluent|false;

    /**
     * Create a new position instance.
     */
    protected function makePosition(): Position
    {
        return app(config('location.position', Position::class));
    }

    /**
     * Determine if the given fluent data is not empty.
     *
     * @param Fluent $data
     *
     * @return bool
     */
    protected function isEmpty(Fluent $data): bool
    {
        return empty(array_filter($data->getAttributes()));
    }
}
