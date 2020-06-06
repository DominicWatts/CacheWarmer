<?php

declare(strict_types=1);

namespace Xigen\CacheWarmer\Model\ItemProvider;

/**
 * Item provider interface class
 */
interface ItemProviderInterface
{
    /**
     * Get warmer items
     * @param int $storeId
     * @return SitemapItemInterface[]
     */
    public function getItems($storeId);
}
