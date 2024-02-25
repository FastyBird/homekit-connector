<?php declare(strict_types = 1);

/**
 * Entities.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Subscribers
 * @since          1.0.0
 *
 * @date           17.02.23
 */

namespace FastyBird\Connector\HomeKit\Subscribers;

use Closure;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Events as DevicesEvents;
use Nette;
use Nette\Utils;
use Symfony\Component\EventDispatcher;

/**
 * Doctrine entities events
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Subscribers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Entities implements EventDispatcher\EventSubscriberInterface
{

	use Nette\SmartObject;

	/** @var array<Closure(DevicesEntities\Connectors\Properties\Variable $property): void> */
	public array $onUpdateSharedKey = [];

	/** @var array<Closure(DevicesEntities\Connectors\Properties\Variable $property): void> */
	public array $onRefresh = [];

	public static function getSubscribedEvents(): array
	{
		return [
			DevicesEvents\EntityCreated::class => 'entityCreated',
			DevicesEvents\EntityUpdated::class => 'entityUpdated',
			DevicesEvents\EntityDeleted::class => 'entityDeleted',
		];
	}

	public function entityCreated(DevicesEvents\EntityCreated $event): void
	{
		if ($event->getEntity() instanceof DevicesEntities\Connectors\Properties\Variable) {
			if ($event->getEntity()->getIdentifier() === Types\ConnectorPropertyIdentifier::SHARED_KEY->value) {
				Utils\Arrays::invoke($this->onUpdateSharedKey, $event->getEntity());
			}

			if (
				$event->getEntity()->getIdentifier() === Types\ConnectorPropertyIdentifier::PAIRED->value
				|| $event->getEntity()->getIdentifier() === Types\ConnectorPropertyIdentifier::CONFIG_VERSION->value
			) {
				Utils\Arrays::invoke($this->onRefresh, $event->getEntity());
			}
		}
	}

	public function entityUpdated(DevicesEvents\EntityUpdated $event): void
	{
		if ($event->getEntity() instanceof DevicesEntities\Connectors\Properties\Variable) {
			if ($event->getEntity()->getIdentifier() === Types\ConnectorPropertyIdentifier::SHARED_KEY->value) {
				Utils\Arrays::invoke($this->onUpdateSharedKey, $event->getEntity());
			}

			if (
				$event->getEntity()->getIdentifier() === Types\ConnectorPropertyIdentifier::PAIRED->value
				|| $event->getEntity()->getIdentifier() === Types\ConnectorPropertyIdentifier::CONFIG_VERSION->value
			) {
				Utils\Arrays::invoke($this->onRefresh, $event->getEntity());
			}
		}
	}

	public function entityDeleted(DevicesEvents\EntityDeleted $event): void
	{
		if ($event->getEntity() instanceof DevicesEntities\Connectors\Properties\Variable) {
			if (
				$event->getEntity()->getIdentifier() === Types\ConnectorPropertyIdentifier::PAIRED->value
				|| $event->getEntity()->getIdentifier() === Types\ConnectorPropertyIdentifier::CONFIG_VERSION->value
			) {
				Utils\Arrays::invoke($this->onRefresh, $event->getEntity());
			}
		}
	}

}
