<?php

declare(strict_types=1);

namespace Xigen\CacheWarmer\Model\ItemProvider;

use Xigen\CacheWarmer\Model\ItemInterfaceFactory;
use Xigen\CacheWarmer\Model\ResourceModel\Catalog\CategoryFactory;

/**
 * Class for fetching category URLs
 */
class Category implements ItemProviderInterface
{
    /**
     * @var CategoryFactory
     */
    private $categoryFactory;

    /**
     * Sitemap item factory
     *
     * @var SitemapItemInterfaceFactory
     */
    private $itemFactory;

    /**
     * @param CategoryFactory $categoryFactory
     * @param ItemInterfaceFactory $itemFactory
     */
    public function __construct(
        CategoryFactory $categoryFactory,
        ItemInterfaceFactory $itemFactory
    ) {
        $this->categoryFactory = $categoryFactory;
        $this->itemFactory = $itemFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems($storeId)
    {
        $collection = $this->categoryFactory->create()
            ->getCollection($storeId);

        $items = array_map(function ($item) use ($storeId) {
            return $this->itemFactory->create([
                'url' => $item->getUrl(),
            ]);
        }, $collection);

        return $items;
    }
}
