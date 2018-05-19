<?php
namespace Goretail\ExtraOrderHistory\Observer;
use Magento\Framework\Event\ObserverInterface;

class AddComment implements ObserverInterface {
	protected $_coreRegistry;
	protected $_authSession;

	public function __construct(
		\Magento\Framework\Registry $coreRegistry,
		\Magento\Backend\Model\Auth\Session $authSession,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Framework\App\RequestInterface $request
	) {
		$this->_coreRegistry = $coreRegistry;
		$this->_authSession = $authSession;
		$this->_customerSession = $customerSession;
		$this->_request = $request;
	}
    public function execute(\Magento\Framework\Event\Observer $observer) {
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$event = $observer->getEvent();
		//$customer = $event->getCustomer();
		//$eventName = $event->getName();
		//$eventData = $event->getData();
		$order = $event->getOrder();
		$invoice = $observer->getEvent()->getInvoice();
		if ($order instanceof \Magento\Framework\Model\AbstractModel) {
			$origData = $order->getOrigData();
			$data = $order->getData();
			if(!$origData || ($origData['state'] != $data['state'])) {
				if($order->getCustomerIsGuest()) {
					$by = $order->getCustomerEmail(); // 'Guest';
				} elseif($this->_customerSession->isLoggedIn()) {
					$by = 'Customer'; //$this->_customerSession->getCustomer()->getName();
				} else {
					$by = $this->_authSession->getUser()->getUsername();
				}

				if($order->getState() == 'new') {
					$message = __(
						'Order #%1 created by %2.',
						$order->getIncrementId(),
						$by
					);
				} else {
					$message = __(
						'Order #%1 changed status to %2 by %3.',
						$order->getIncrementId(),
						$order->getState(),
						$by
					);
				}


			}
		} elseif (($invoice instanceof \Magento\Framework\Model\AbstractModel) && ($orderId = $this->_request->getParam('order_id'))) {
			$order = $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);
			$message = __(
				'Order #%1 has created invoice by %2.',
				$order->getIncrementId(),
				$this->_authSession->getUser()->getUsername()
			);
		}
		if($order && $order->getEntityId() && isset($message)) {
			$comment = $order->addStatusHistoryComment($message)->setIsCustomerNotified(false)->setEntityName('order');
			if($order->getState() == 'new') {
				$comment->setIsVisibleOnFront(true);
			}
			$comment->save();
		}
		return $this;
	}
}
