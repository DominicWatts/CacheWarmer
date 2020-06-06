<?php

declare(strict_types=1);

namespace Xigen\CacheWarmer\Model\ResourceModel\Catalog;

use Magento\Catalog\Helper\Product as HelperProduct;
use Magento\Catalog\Model\Product as ModelProduct;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as ResourceModelProduct;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Xigen\CacheWarmer\Helper\Data;

/**
 * Item resource product collection model
 */
class Product extends AbstractDb
{
    /**
     * @var \Magento\Framework\DB\Select
     */
    protected $select;

    /**
     * @var array
     */
    protected $attributesCache = [];

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product
     */
    protected $productResource;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Catalog\Model\Product\Visibility
     */
    protected $productVisibility;

    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Source\Status
     */
    protected $productStatus;

    /**
     * @var \Magento\Catalog\Model\Product
     */
    private $productModel;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Undocumented function
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     * @param \Magento\Catalog\Model\ResourceModel\Product $productResource
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Product\Visibility $productVisibility
     * @param \Magento\Catalog\Model\Product\Attribute\Source\Status $productStatus
     * @param \Magento\Catalog\Model\Product $productModel
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param string $connectionName
     */
    public function __construct(
        Context $context,
        ResourceModelProduct $productResource,
        StoreManagerInterface $storeManager,
        Visibility $productVisibility,
        Status $productStatus,
        ModelProduct $productModel,
        ScopeConfigInterface $scopeConfig,
        $connectionName = null
    ) {
        $this->productResource = $productResource;
        $this->storeManager = $storeManager;
        $this->productVisibility = $productVisibility;
        $this->productStatus = $productStatus;
        $this->productModel = $productModel;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $connectionName);
    }

    /**
     * Construct
     * @return void
     */
    protected function _construct()
    {
        $this->_init('catalog_product_entity', 'entity_id');
    }

    /**
     * Add attribute to filter
     * @param int $storeId
     * @param string $attributeCode
     * @param mixed $value
     * @param string $type
     * @return \Magento\Framework\DB\Select|bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function addFilter($storeId, $attributeCode, $value, $type = '=')
    {
        if (!$this->select instanceof Select) {
            return false;
        }

        switch ($type) {
            case '=':
                $conditionRule = '=?';
                break;
            case 'in':
                $conditionRule = ' IN(?)';
                break;
            default:
                return false;
        }

        $attribute = $this->getAttribute($attributeCode);
        if ($attribute['backend_type'] == 'static') {
            $this->select->where('e.' . $attributeCode . $conditionRule, $value);
        } else {
            $this->joinAttribute($storeId, $attributeCode);
            if ($attribute['is_global']) {
                $this->select->where('t1_' . $attributeCode . '.value' . $conditionRule, $value);
            } else {
                $ifCase = $this->getConnection()->getCheckSql(
                    't2_' . $attributeCode . '.value_id > 0',
                    't2_' . $attributeCode . '.value',
                    't1_' . $attributeCode . '.value'
                );
                $this->select->where('(' . $ifCase . ')' . $conditionRule, $value);
            }
        }

        return $this->select;
    }

    /**
     * Join attribute by code
     * @param int $storeId
     * @param string $attributeCode
     * @param string $column Add attribute value to given column
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function joinAttribute($storeId, $attributeCode, $column = null)
    {
        $connection = $this->getConnection();
        $attribute = $this->getAttribute($attributeCode);

        $linkField = $this->productResource->getLinkField();
        $attrTableAlias = 't1_' . $attributeCode;
        $this->select->joinLeft(
            [$attrTableAlias => $attribute['table']],
            "e.{$linkField} = {$attrTableAlias}.{$linkField}"
            . ' AND ' . $connection->quoteInto($attrTableAlias . '.store_id = ?', Store::DEFAULT_STORE_ID)
            . ' AND ' . $connection->quoteInto($attrTableAlias . '.attribute_id = ?', $attribute['attribute_id']),
            []
        );
        // Global scope attribute value
        $columnValue = 't1_' . $attributeCode . '.value';

        if (!$attribute['is_global']) {
            $attrTableAlias2 = 't2_' . $attributeCode;
            $this->select->joinLeft(
                ['t2_' . $attributeCode => $attribute['table']],
                "{$attrTableAlias}.{$linkField} = {$attrTableAlias2}.{$linkField}"
                . ' AND ' . $attrTableAlias . '.attribute_id = ' . $attrTableAlias2 . '.attribute_id'
                . ' AND ' . $connection->quoteInto($attrTableAlias2 . '.store_id = ?', $storeId),
                []
            );
            // Store scope attribute value
            $columnValue = $this->getConnection()->getIfNullSql('t2_' . $attributeCode . '.value', $columnValue);
        }

        // Add attribute value to result set if needed
        if (isset($column)) {
            $this->select->columns(
                [
                    $column => $columnValue
                ]
            );
        }
    }

    /**
     * Get attribute data by attribute code
     * @param string $attributeCode
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getAttribute($attributeCode)
    {
        if (!isset($this->attributesCache[$attributeCode])) {
            $attribute = $this->productResource->getAttribute($attributeCode);

            $this->attributesCache[$attributeCode] = [
                'entity_type_id' => $attribute->getEntityTypeId(),
                'attribute_id' => $attribute->getId(),
                'table' => $attribute->getBackend()->getTable(),
                'is_global' => $attribute->getIsGlobal() == ScopedAttributeInterface::SCOPE_GLOBAL,
                'backend_type' => $attribute->getBackendType(),
            ];
        }
        return $this->attributesCache[$attributeCode];
    }

    /**
     * Get product collection array
     * @param null|string|bool|int|Store $storeId
     * @return array|bool
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Zend_Db_Statement_Exception
     */
    public function getCollection($storeId)
    {
        $products = [];

        /* @var $store Store */
        $store = $this->storeManager->getStore($storeId);
        if (!$store) {
            return false;
        }

        $connection = $this->getConnection();
        $urlRewriteMetaDataCondition = '';
        if (!$this->isCategoryProductURLsConfig($storeId)) {
            $urlRewriteMetaDataCondition = ' AND url_rewrite.metadata IS NULL';
        }

        $this->select = $connection->select()->from(
            ['e' => $this->getMainTable()],
            [$this->getIdFieldName(), $this->productResource->getLinkField(), 'updated_at']
        )->joinInner(
            ['w' => $this->getTable('catalog_product_website')],
            'e.entity_id = w.product_id',
            []
        )->joinLeft(
            ['url_rewrite' => $this->getTable('url_rewrite')],
            'e.entity_id = url_rewrite.entity_id AND url_rewrite.is_autogenerated = 1'
            . $urlRewriteMetaDataCondition
            . $connection->quoteInto(' AND url_rewrite.store_id = ?', $store->getId())
            . $connection->quoteInto(' AND url_rewrite.entity_type = ?', ProductUrlRewriteGenerator::ENTITY_TYPE),
            ['url' => 'request_path']
        )->where(
            'w.website_id = ?',
            $store->getWebsiteId()
        );

        $this->addFilter($store->getId(), 'visibility', $this->productVisibility->getVisibleInSiteIds(), 'in');
        $this->addFilter($store->getId(), 'status', $this->productStatus->getVisibleStatusIds(), 'in');

        $query = $connection->query($this->prepareSelectStatement($this->select));
        while ($row = $query->fetch()) {
            $product = $this->prepareProduct($row, $store->getId());
            $products[$product->getId()] = $product;
        }

        return $products;
    }

    /**
     * Prepare product
     * @param array $productRow
     * @param int $storeId
     * @return \Magento\Framework\DataObject
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function prepareProduct(array $productRow, $storeId)
    {
        $product = new DataObject();

        $product['id'] = $productRow[$this->getIdFieldName()];
        if (empty($productRow['url'])) {
            $productRow['url'] = 'catalog/product/view/id/' . $product->getId();
        }
        $product->addData($productRow);
        return $product;
    }

    /**
     * Allow to modify select statement with plugins
     * @param \Magento\Framework\DB\Select $select
     * @return \Magento\Framework\DB\Select
     */
    public function prepareSelectStatement(Select $select)
    {
        return $select;
    }

    /**
     * Return Use Categories Path for Product URLs config value
     * @param null|string $storeId
     * @return bool
     */
    private function isCategoryProductURLsConfig($storeId)
    {
        return $this->scopeConfig->isSetFlag(
            HelperProduct::XML_PATH_PRODUCT_URL_USE_CATEGORY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
