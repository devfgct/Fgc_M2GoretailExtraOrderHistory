<?php
namespace Goretail\ExtraOrderHistory\Observer;
use Magento\Framework\Event\ObserverInterface;

class SalesOrderAfterSave implements ObserverInterface {
	protected $_coreRegistry;
	protected $_authSession;

	public function __construct(
		\Magento\Framework\Registry $coreRegistry,
		\Magento\Backend\Model\Auth\Session $authSession,
		\Magento\Customer\Model\Session $customerSession
	) {
		$this->_coreRegistry = $coreRegistry;
		$this->_authSession = $authSession;
		$this->_customerSession = $customerSession;
	}
    public function execute(\Magento\Framework\Event\Observer $observer) {
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$event = $observer->getEvent();
		$customer = $event->getCustomer();
		$eventName = $event->getName();
		$eventData = $event->getData();
		$order = $event->getOrder();
		if ($order instanceof \Magento\Framework\Model\AbstractModel) {
			$origData = $order->getOrigData();
			$data = $order->getData();
			/**
			 * Submit Invoice
			 * $origData: ['state' => 'new', 'status' => 'pending']
			 * $data: ['state' => 'processing', 'status' => 'processing']
			 *
			 * Hold
			 * $data: ['state' => 'holded', 'status' => 'holded']
			 *
			 * Unhold
			 * $data: ['state' => 'processing', 'status' => 'processing']
			 */
			if(!$origData || ($origData['state'] != $data['state'])) {
				if($order->getCustomerIsGuest()) {
					$by = $order->getCustomerEmail(); // 'Guest';
				} elseif($this->_customerSession->isLoggedIn()) {
					$by = $this->_customerSession->getCustomer()->getName();
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

				if($order->getEntityId()) {
					$comment = $order->addStatusHistoryComment($message)->setIsCustomerNotified(false)->setEntityName('order');
					if($order->getState() == 'new') {
						$comment->setIsVisibleOnFront(true);
					}
					$comment->save();
				}
				return $this;
			}
		}
		return $this;
	}
}
