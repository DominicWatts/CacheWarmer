<?php

declare(strict_types=1);

namespace Xigen\CacheWarmer\Model;

/**
 * Representation of item
 */
interface ItemInterface
{
    /**
     * Get url
     * @return string
     */
    public function getUrl();
}
