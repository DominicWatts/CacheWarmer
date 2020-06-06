<?php

declare(strict_types=1);

namespace Xigen\CacheWarmer\Model\ItemProvider;

use Xigen\CacheWarmer\Model\ItemInterfaceFactory;

/**
 * Class for adding Store URL
 */
class StoreUrl implements ItemProviderInterface
{
    /**
     * @var ItemInterfaceFactory
     */
    private $itemFactory;

    /**
     * StoreUrlSitemapItemResolver constructor.
     * @param ItemInterfaceFactory $itemFactory
     */
    public function __construct(
        ItemInterfaceFactory $itemFactory
    ) {
        $this->itemFactory = $itemFactory;
    }

    /**
     * @inheritdoc
     */
    public function getItems($storeId)
    {
        $items[] = $this->itemFactory->create([
            'url' => '',
        ]);

        return $items;
    }
}
