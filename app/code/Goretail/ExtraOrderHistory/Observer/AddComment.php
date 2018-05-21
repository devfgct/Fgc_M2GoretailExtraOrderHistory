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
		$orderId = $this->_request->getParam('order_id');
		$eventName = $event->getName();
		//$eventData = $event->getData();
		$order = $event->getOrder();
		$invoice = $observer->getEvent()->getInvoice();
		$shipment = $observer->getEvent()->getShipment();

		$design = $objectManager->create('\Magento\Framework\View\DesignInterface');
		$area = $design->getArea();
		if($area=='adminhtml') {
			$by = $this->_authSession->getUser()->getUsername();
		} else {
			/* if($order->getCustomerIsGuest()) {
				$by = 'Customer'; //$by = $order->getCustomerEmail(); // 'Guest';
			} elseif($this->_customerSession->isLoggedIn()) {
				$by = 'Customer'; //$this->_customerSession->getCustomer()->getName();
			} */
			$by = 'Customer';
		}
		if ($order instanceof \Magento\Framework\Model\AbstractModel) {
			$origData = $order->getOrigData();
			$data = $order->getData();
			$state = $order->getState();
			if(!$origData || ($origData['state'] != $data['state'])) {
				if(isset($origData['state']) && $origData['state']=='holded') $state = 'unhold';

				if(!$origData) {
					$message = __(
						'Order #%1 created by %2.',
						$order->getIncrementId(),
						$by
					);
				} else {
					$message = __(
						'Order #%1 changed status to %2 by %3.',
						$order->getIncrementId(),
						$state,
						$by
					);
				}
			}
		} elseif (($invoice instanceof \Magento\Framework\Model\AbstractModel) && $orderId) {
			$order = $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);
			$message = __(
				'Order #%1 has created invoice by %2.',
				$order->getIncrementId(),
				$by
			);
		} elseif (($shipment instanceof \Magento\Framework\Model\AbstractModel) && $orderId) {
			$order = $objectManager->create('\Magento\Sales\Model\Order')->load($orderId);
			$message = __(
				'Order #%1 has created shipment by %2.',
				$order->getIncrementId(),
				$by
			);
		}
		if($order && $order->getEntityId() && isset($message)) {
			$comment = $order->addStatusHistoryComment($message)->setIsCustomerNotified(false)->setEntityName('order');
			if($order->getState() == 'new' && !$order->getOrigData()) {
				$comment->setIsVisibleOnFront(true);
			}
			$comment->save();
		}
		return $this;
	}
}
