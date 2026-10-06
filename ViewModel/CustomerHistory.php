<?php
declare(strict_types=1);

namespace Bulmeg\AdminTools\ViewModel;

use Bulmeg\AdminTools\Model\Config;
use Bulmeg\AdminTools\Model\CustomerHistory\History;
use Bulmeg\AdminTools\Model\CustomerHistory\HistoryProvider;
use Bulmeg\AdminTools\Model\ResourceModel\CustomerOrders;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class CustomerHistory implements ArgumentInterface
{
    public const ACL_RESOURCE = 'Bulmeg_AdminTools::customer_history';
    public const ANCHOR = 'bulmeg-customer-history';

    private ?History $history = null;
    private bool $isLoaded = false;

    public function __construct(
        private readonly Config $config,
        private readonly HistoryProvider $historyProvider,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RequestInterface $request,
        private readonly AuthorizationInterface $authorization,
        private readonly UrlInterface $url,
        private readonly LoggerInterface $logger
    ) {
    }

    public function isAllowed(): bool
    {
        return $this->config->isCustomerHistoryEnabled()
            && $this->authorization->isAllowed(self::ACL_RESOURCE);
    }

    public function getHistory(): ?History
    {
        if ($this->isLoaded) {
            return $this->history;
        }
        $this->isLoaded = true;
        if (!$this->isAllowed()) {
            return null;
        }

        $order = $this->getOrder();
        if ($order === null) {
            return null;
        }
        try {
            $this->history = $this->historyProvider->getHistory($order);
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Bulmeg_AdminTools: customer history of order %d could not be loaded.', $order->getEntityId()),
                ['exception' => $e]
            );
        }

        return $this->history;
    }

    public function getOrderUrl(int $orderId): string
    {
        return $this->url->getUrl('sales/order/view', ['order_id' => $orderId]);
    }

    public function getMatchLimit(): int
    {
        return CustomerOrders::MAX_MATCHES;
    }

    public function getAnchor(): string
    {
        return self::ANCHOR;
    }

    public function hasSeveralStores(History $history): bool
    {
        $storeIds = array_column($history->getOrders(), 'store_id');
        $order = $this->getOrder();
        if ($order !== null) {
            $storeIds[] = (int)$order->getStoreId();
        }

        return count(array_unique($storeIds)) > 1;
    }

    private function getOrder(): ?OrderInterface
    {
        $orderId = (int)$this->request->getParam('order_id');
        if ($orderId <= 0) {
            return null;
        }

        try {
            return $this->orderRepository->get($orderId);
        } catch (\Exception $e) {
            return null;
        }
    }
}
