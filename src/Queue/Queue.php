<?php declare(strict_types = 1);

/**
 * Queue.php
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
use FastyBird\Connector\HomeKit\Queue\Messages\Message;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use Nette;
use SplQueue;

/**
 * Clients message consumer proxy
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queue
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Queue
{

	use Nette\SmartObject;

	/** @var SplQueue<Message> */
	private SplQueue $queue;

	public function __construct(private readonly HomeKit\Logger $logger)
	{
		$this->queue = new SplQueue();
	}

	public function append(Messages\Message $message): void
	{
		$this->queue->enqueue($message);

		$this->logger->debug(
			'Appended new message into messages queue',
			[
				'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
				'type' => 'queue',
				'message' => $message->toArray(),
			],
		);
	}

	public function dequeue(): Messages\Message|false
	{
		$this->queue->rewind();

		if ($this->queue->isEmpty()) {
			return false;
		}

		return $this->queue->dequeue();
	}

	public function isEmpty(): bool
	{
		return $this->queue->isEmpty();
	}

}
