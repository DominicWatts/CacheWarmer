<?php

declare(strict_types=1);

namespace Xigen\CacheWarmer\Model\ItemProvider;

use Xigen\CacheWarmer\Model\ItemInterfaceFactory;
use Xigen\CacheWarmer\Model\ResourceModel\Cms\PageFactory;

/**
 * Class for fetching CMS page URLs
 */
class CmsPage implements ItemProviderInterface
{
    /**
     * @var PageFactory
     */
    private $cmsPageFactory;

    /**
     * @var ItemInterfaceFactory
     */
    private $itemFactory;

    /**
     * CmsPage constructor.
     * @param PageFactory $cmsPageFactory
     * @param ItemInterfaceFactory $itemFactory
     */
    public function __construct(
        PageFactory $cmsPageFactory,
        ItemInterfaceFactory $itemFactory
    ) {
        $this->cmsPageFactory = $cmsPageFactory;
        $this->itemFactory = $itemFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems($storeId)
    {
        $collection = $this->cmsPageFactory
            ->create()
            ->getCollection($storeId);

        $items = array_map(function ($item) use ($storeId) {
            return $this->itemFactory->create([
                'url' => $item->getUrl(),
            ]);
        }, $collection);

        return $items;
    }
}
