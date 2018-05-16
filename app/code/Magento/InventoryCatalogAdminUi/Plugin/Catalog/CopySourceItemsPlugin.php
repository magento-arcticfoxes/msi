<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogAdminUi\Plugin\Catalog;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Copier;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryCatalogAdminUi\Observer\SourceItemsProcessor;

/**
 * Copies source items from the original product to the duplicate
 */
class CopySourceItemsPlugin
{
    /**
     * @var SourceItemRepositoryInterface
     */
    private $sourceItemRepository;

    /**
     * @var SearchCriteriaBuilderFactory
     */
    private $searchCriteriaBuilderFactory;

    /**
     * @var SourceItemsProcessor
     */
    private $sourceItemsProcessor;

    /**
     * @param SourceItemRepositoryInterface $sourceItemRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param SourceItemsProcessor $sourceItemsProcessor
     */
    public function __construct(
        SourceItemRepositoryInterface $sourceItemRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        SourceItemsProcessor $sourceItemsProcessor
    ) {
        $this->sourceItemRepository = $sourceItemRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->sourceItemsProcessor = $sourceItemsProcessor;
    }

    /**
     * @param Copier $subject
     * @param Product $result
     * @param Product $product
     * @return Product $result
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCopy(
        Copier $subject,
        Product $result,
        Product $product
    ): Product {
        $this->copySourceItems($product->getSku(), $result->getSku());
        return $result;
    }

    /**
     * @param string $sku
     * @return array
     */
    private function getSourceItems(string $sku): array
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteria = $searchCriteriaBuilder
            ->addFilter(SourceItemInterface::SKU, $sku)
            ->create();
        return $this->sourceItemRepository->getList($searchCriteria)->getItems();
    }

    /**
     * @param string $originalSku
     * @param string $duplicateSku
     * @return void
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     */
    private function copySourceItems(string $originalSku, string $duplicateSku): void
    {
        $sourceItems = $this->getSourceItems($originalSku);

        $duplicateItemData = [];
        if ($sourceItems) {
            foreach ($sourceItems as $sourceItem) {
                $duplicateItemData[] = [
                    SourceItemInterface::SKU => $duplicateSku,
                    SourceItemInterface::SOURCE_CODE => $sourceItem->getSourceCode(),
                    SourceItemInterface::QUANTITY => $sourceItem->getQuantity(),
                    SourceItemInterface::STATUS => $sourceItem->getStatus()
                ];
            }
        }

        $this->sourceItemsProcessor->process(
            $duplicateSku,
            $duplicateItemData
        );
    }
}
