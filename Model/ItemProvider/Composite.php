<?php

declare(strict_types=1);

namespace Xigen\CacheWarmer\Model\ItemProvider;

/**
 * Class for collating the different item providors
 */
class Composite implements ItemProviderInterface
{
    /**
     * @var ItemProviderInterface[]
     */
    private $itemProviders;

    /**
     * Composite constructor.
     * @param ItemProviderInterface[] $itemProviders
     */
    public function __construct($itemProviders = [])
    {
        $this->itemProviders = $itemProviders;
    }

    /**
     * {@inheritdoc}
     */
    public function getItems($storeId)
    {
        $items = [];

        foreach ($this->itemProviders as $resolver) {
            foreach ($resolver->getItems($storeId) as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
