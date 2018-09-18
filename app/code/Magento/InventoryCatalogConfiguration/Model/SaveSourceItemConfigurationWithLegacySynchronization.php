<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogConfiguration\Model;

use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryConfigurationApi\Api\Data\SourceItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\SaveSourceConfigurationInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterface;

class SaveSourceItemConfigurationWithLegacySynchronization implements SaveSourceConfigurationInterface
{
    /**
     * @var SaveSourceConfigurationInterface
     */
    private $saveSourceConfiguration;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var GetProductIdsBySkusInterface
     */
    private $getProductIdsBySkus;

    /**
     * @var DefaultStockProviderInterface
     */
    private $defaultStockProvider;

    /**
     * @var DefaultSourceProviderInterface
     */
    private $defaultSourceProvider;

    /**
     * @param SaveSourceConfigurationInterface $saveSourceConfiguration
     * @param ResourceConnection $resourceConnection
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param DefaultSourceProviderInterface $defaultSourceProvider
     */
    public function __construct(
        SaveSourceConfigurationInterface $saveSourceConfiguration,
        ResourceConnection $resourceConnection,
        GetProductIdsBySkusInterface $getProductIdsBySkus,
        DefaultStockProviderInterface $defaultStockProvider,
        DefaultSourceProviderInterface $defaultSourceProvider
    ) {
        $this->saveSourceConfiguration = $saveSourceConfiguration;
        $this->resourceConnection = $resourceConnection;
        $this->getProductIdsBySkus = $getProductIdsBySkus;
        $this->defaultStockProvider = $defaultStockProvider;
        $this->defaultSourceProvider = $defaultSourceProvider;
    }

    /**
     * @inheritdoc
     */
    public function forSourceItem(
        string $sku,
        string $sourceCode,
        SourceItemConfigurationInterface $sourceItemConfiguration
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $sourceConfigurationTable = $this->resourceConnection->getTableName('inventory_source_configuration');

        $data = [
            'sku' => $sku,
            'source_code' => $sourceCode,
            SourceItemConfigurationInterface::BACKORDERS => $sourceItemConfiguration->getBackorders(),
            SourceItemConfigurationInterface::NOTIFY_STOCK_QTY => $sourceItemConfiguration->getNotifyStockQty()
        ];
        $connection->insertOnDuplicate(
            $sourceConfigurationTable,
            $data
        );

        if ($sourceCode === $this->defaultSourceProvider->getCode()) {
            $this->updateLegacyStockItem($sku, $sourceItemConfiguration);
        }
    }

    /**
     * @param string $sku
     * @param SourceItemConfigurationInterface $sourceItemConfiguration
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function updateLegacyStockItem(
        string $sku,
        SourceItemConfigurationInterface $sourceItemConfiguration
    ): void {
        $connection = $this->resourceConnection->getConnection();
        $productId = $this->getProductIdsBySkus->execute([$sku])[$sku];

        if ($sourceItemConfiguration->getBackorders() === null) {
            $data[StockItemInterface::USE_CONFIG_BACKORDERS] = 1;
            $data[StockItemInterface::BACKORDERS] = null;
        } else {
            $data[StockItemInterface::USE_CONFIG_BACKORDERS] = 0;
            $data[StockItemInterface::BACKORDERS] = $sourceItemConfiguration->getBackorders();
        }

        if ($sourceItemConfiguration->getNotifyStockQty() === null) {
            $data[StockItemInterface::USE_CONFIG_NOTIFY_STOCK_QTY] = 1;
            $data[StockItemInterface::NOTIFY_STOCK_QTY] = null;
        } else {
            $data[StockItemInterface::USE_CONFIG_NOTIFY_STOCK_QTY] = 0;
            $data[StockItemInterface::NOTIFY_STOCK_QTY] = $sourceItemConfiguration->getNotifyStockQty();
        }

        $whereCondition[StockItemInterface::STOCK_ID . ' = ?'] = $this->defaultStockProvider->getId();
        $whereCondition[StockItemInterface::PRODUCT_ID . ' = ?'] = $productId;

        $connection->update(
            $connection->getTableName('cataloginventory_stock_item'),
            $data,
            $whereCondition
        );
    }

    /**
     * @inheritdoc
     */
    public function forSource(string $sourceCode, SourceItemConfigurationInterface $sourceItemConfiguration): void
    {
        $this->saveSourceConfiguration->forSource($sourceCode, $sourceItemConfiguration);
    }

    /**
     * @inheritdoc
     */
    public function forGlobal(SourceItemConfigurationInterface $sourceItemConfiguration): void
    {
        $this->saveSourceConfiguration->forGlobal($sourceItemConfiguration);
    }
}
