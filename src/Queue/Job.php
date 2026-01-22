<?php

declare(strict_types=1);

namespace Plugs\Queue;

interface Job
{
    /**
     * Execute the job.
     *
     * @param mixed $data
     * @return void
     */
    public function handle($data);
}
