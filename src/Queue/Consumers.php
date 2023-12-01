<?php declare(strict_types = 1);

/**
 * Consumers.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queue
 * @since          1.0.0
 *
 * @date           30.11.23
 */

namespace FastyBird\Connector\HomeKit\Queue;

use FastyBird\Connector\HomeKit;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use Nette;
use SplObjectStorage;

/**
 * Clients message queue consumers container
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queue
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Consumers
{

	use Nette\SmartObject;

	/** @var SplObjectStorage<Consumer, null> */
	private SplObjectStorage $consumers;

	/**
	 * @param array<Consumer> $consumers
	 */
	public function __construct(
		array $consumers,
		private readonly Queue $queue,
		private readonly HomeKit\Logger $logger,
	)
	{
		$this->consumers = new SplObjectStorage();

		foreach ($consumers as $consumer) {
			$this->append($consumer);
		}
	}

	public function append(Consumer $consumer): void
	{
		$this->consumers->attach($consumer);

		$this->logger->debug(
			'Appended new messages consumer',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'consumers',
			],
		);
	}

	public function consume(): void
	{
		$entity = $this->queue->dequeue();

		if ($entity === false) {
			return;
		}

		$this->consumers->rewind();

		if ($this->consumers->count() === 0) {
			$this->logger->error(
				'No consumer is registered, messages could not be consumed',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'consumers',
				],
			);

			return;
		}

		foreach ($this->consumers as $consumer) {
			if ($consumer->consume($entity) === true) {
				return;
			}
		}

		$this->logger->error(
			'Message could not be consumed',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'consumers',
				'message' => $entity->toArray(),
			],
		);
	}

}
