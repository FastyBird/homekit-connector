<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Subscribers
 * @since          0.1.0
 *
 * @date           28.10.22
 */

namespace FastyBird\Connector\HomeKit\Subscribers;

use FastyBird\Connector\HomeKit\Consumers;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Library\Exchange\Consumers as ExchangeConsumers;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Events as DevicesEvents;
use Psr\Log;
use Symfony\Component\EventDispatcher;

/**
 * Connector subscriber
 *
 * @package         FastyBird:HomeKitConnector!
 * @subpackage      Subscribers
 *
 * @author          Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Connector implements EventDispatcher\EventSubscriberInterface
{

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly ExchangeConsumers\Container $consumer,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	public static function getSubscribedEvents(): array
	{
		return [
			DevicesEvents\ConnectorStartup::class => 'startup',
		];
	}

	public function startup(DevicesEvents\ConnectorStartup $event): void
	{
		if (!$event->getConnector() instanceof Entities\HomeKitConnector) {
			return;
		}

		$this->consumer->enable(Consumers\Consumer::class);

		$this->logger->debug(
			'Registering HomeKit consumer',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'subscriber',
			],
		);
	}

}
