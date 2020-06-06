<?php

declare(strict_types=1);

namespace Xigen\CacheWarmer\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Robots\Model\Config\Value;
use Magento\Store\Model\StoreManagerInterface;
use Xigen\CacheWarmer\Model\ItemProvider\ItemProviderInterface;
use Xigen\CacheWarmer\Model\ResourceModel\Cms\PageFactory;

class Warm extends AbstractModel implements IdentityInterface
{
    /**
     * @var PageFactory
     */
    protected $cmsFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ItemProviderInterface
     */
    protected $itemProvider;

    /**
     * @var array
     */
    protected $items = [];

    /**
     * @var int
     */
    protected $storeId;

    /**
     * @var array
     */
    protected $urls = [];

    /**
     * Warm constructor.
     * @param Context $context
     * @param Registry $registry
     * @param PageFactory $cmsFactory
     * @param StoreManagerInterface $storeManager
     * @param ItemProviderInterface $itemProvider
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        PageFactory $cmsFactory,
        StoreManagerInterface $storeManager,
        ItemProviderInterface $itemProvider,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->cmsFactory = $cmsFactory;
        $this->storeManager = $storeManager;
        $this->itemProvider = $itemProvider;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    public function process()
    {
        $this->initItems();
        foreach ($this->items as $item) {
            $this->urls[] = $this->getRow(
                $item->getUrl()
            );
        }
    }

    protected function getRow($url)
    {
        return $this->getUrl($url);
    }

    protected function initItems()
    {
        $this->items = $this->itemProvider->getItems($this->getStoreId());
    }

    /**
     * Get unique page cache identities
     * @return array
     */
    public function getIdentities()
    {
        return [
            Value::CACHE_TAG . '_' . $this->getStoreId(),
        ];
    }

    /**
     * Get url
     * @param string $url
     * @param string $type
     * @return string
     */
    protected function getUrl($url, $type = UrlInterface::URL_TYPE_LINK)
    {
        return $this->getStoreBaseUrl($type) . ltrim($url, '/');
    }

    /**
     * Get store base url
     * @param string $type
     * @return string
     */
    protected function getStoreBaseUrl($type = UrlInterface::URL_TYPE_LINK)
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore($this->getStoreId());
        $isSecure = $store->isUrlSecure();
        return rtrim($store->getBaseUrl($type, $isSecure), '/') . '/';
    }

    /**
     * Return current store id
     * @return int
     */
    public function getStoreId()
    {
        if ($this->storeId === null) {
            $this->setStoreId($this->storeManager->getStore()->getId());
        }
        return $this->storeId;
    }

    /**
     * Set store scope ID
     * @param int $storeId
     * @return $this
     */
    public function setStoreId($storeId)
    {
        $this->storeId = $storeId;
        return $this;
    }

    /**
     * Get Urls
     * @return array
     */
    public function getUrls()
    {
        if (empty($this->urls)) {
            $this->process();
        }
        return $this->urls;
    }
}
