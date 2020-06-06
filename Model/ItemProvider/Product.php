<?php

declare(strict_types=1);

namespace Xigen\CacheWarmer\Model\ItemProvider;

use Xigen\CacheWarmer\Model\ItemInterfaceFactory;
use Xigen\CacheWarmer\Model\ResourceModel\Catalog\ProductFactory;

/**
 * Class for fetch product URLs
 */
class Product implements ItemProviderInterface
{
    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * @var ItemInterfaceFactory
     */
    private $itemFactory;

    /**
     * ProductSitemapItemResolver constructor.
     * @param ProductFactory $productFactory
     * @param ItemInterfaceFactory $itemFactory
     */
    public function __construct(
        ProductFactory $productFactory,
        ItemInterfaceFactory $itemFactory
    ) {
        $this->productFactory = $productFactory;
        $this->itemFactory = $itemFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems($storeId)
    {
        $collection = $this->productFactory->create()
            ->getCollection($storeId);

        $items = array_map(function ($item) use ($storeId) {
            return $this->itemFactory->create([
                'url' => $item->getUrl(),
            ]);
        }, $collection);

        return $items;
    }
}
