<?php

declare(strict_types=1);

namespace Xigen\CacheWarmer\Model\ResourceModel\Cms;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\GetUtilityPageIdentifiersInterface;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\EntityManager;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

/**
 * cms page collection model
 */
class Page extends AbstractDb
{
    /**
     * @var MetadataPool
     */
    protected $metadataPool;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var GetUtilityPageIdentifiersInterface
     */
    private $getUtilityPageIdentifiers;

    /**
     * Page constructor.
     * @param Context $context
     * @param MetadataPool $metadataPool
     * @param EntityManager $entityManager
     * @param GetUtilityPageIdentifiersInterface $getUtilityPageIdentifiers
     * @param null $connectionName
     */
    public function __construct(
        Context $context,
        MetadataPool $metadataPool,
        EntityManager $entityManager,
        GetUtilityPageIdentifiersInterface $getUtilityPageIdentifiers,
        $connectionName = null
    ) {
        $this->metadataPool = $metadataPool;
        $this->entityManager = $entityManager;
        $this->getUtilityPageIdentifiers = $getUtilityPageIdentifiers;
        parent::__construct($context, $connectionName);
    }

    /**
     * Init resource model
     * @return void
     */
    protected function _construct()
    {
        $this->_init('cms_page', 'page_id');
    }

    /**
     * @inheritDoc
     */
    public function getConnection()
    {
        return $this->metadataPool->getMetadata(PageInterface::class)->getEntityConnection();
    }

    /**
     * Retrieve cms page collection array
     * @param int $storeId
     * @return array
     */
    public function getCollection($storeId)
    {
        $entityMetadata = $this->metadataPool->getMetadata(PageInterface::class);
        $linkField = $entityMetadata->getLinkField();

        $select = $this->getConnection()->select()->from(
            ['main_table' => $this->getMainTable()],
            [$this->getIdFieldName(), 'url' => 'identifier']
        )->join(
            ['store_table' => $this->getTable('cms_page_store')],
            "main_table.{$linkField} = store_table.$linkField",
            []
        )->where(
            'main_table.is_active = 1'
        )->where(
            'main_table.identifier NOT IN (?)',
            $this->getUtilityPageIdentifiers->execute()
        )->where(
            'store_table.store_id IN(?)',
            [0, $storeId]
        );

        $pages = [];
        $query = $this->getConnection()->query($select);
        while ($row = $query->fetch()) {
            $page = $this->prepareObject($row);
            $pages[$page->getId()] = $page;
        }

        return $pages;
    }

    /**
     * Prepare page object
     * @param array $data
     * @return \Magento\Framework\DataObject
     */
    protected function prepareObject(array $data)
    {
        $object = new DataObject();
        $object->setId($data[$this->getIdFieldName()]);
        $object->setUrl($data['url']);
        return $object;
    }

    /**
     * Load an object
     * @param CmsPage|AbstractModel $object
     * @param mixed $value
     * @param string $field field to load by (defaults to model id)
     * @return $this
     */
    public function load(AbstractModel $object, $value, $field = null)
    {
        $entityMetadata = $this->metadataPool->getMetadata(PageInterface::class);

        if (!is_numeric($value) && $field === null) {
            $field = 'identifier';
        } elseif (!$field) {
            $field = $entityMetadata->getIdentifierField();
        }

        $isId = true;
        if ($field != $entityMetadata->getIdentifierField() || $object->getStoreId()) {
            $select = $this->_getLoadSelect($field, $value, $object);
            $select->reset(Select::COLUMNS)
                ->columns($this->getMainTable() . '.' . $entityMetadata->getIdentifierField())
                ->limit(1);
            $result = $this->getConnection()->fetchCol($select);
            $value = count($result) ? $result[0] : $value;
            $isId = count($result);
        }

        if ($isId) {
            $this->entityManager->load($object, $value);
        }
        return $this;
    }
}
