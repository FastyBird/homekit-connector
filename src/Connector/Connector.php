<?php declare(strict_types = 1);

/**
 * Connector.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Connector
 * @since          1.0.0
 *
 * @date           17.09.22
 */

namespace FastyBird\Connector\HomeKit\Connector;

use Doctrine\DBAL;
use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Documents;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Queue;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Writers;
use FastyBird\Core\Application\Exceptions as ApplicationExceptions;
use FastyBird\Core\Exchange\Exceptions as ExchangeExceptions;
use FastyBird\Core\Tools\Exceptions as ToolsExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Connectors as DevicesConnectors;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use Nette;
use React\EventLoop;
use React\Promise;
use TypeError;
use ValueError;
use z4kn4fein\SemVer;
use function assert;
use function React\Async\async;

/**
 * Connector service executor
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Connector
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector implements DevicesConnectors\Connector
{

	use Nette\SmartObject;

	private const QUEUE_PROCESSING_INTERVAL = 0.01;

	private const DRIVER_RELOAD_INTERVAL = 60;

	/** @var array<Servers\Server> */
	private array $servers = [];

	private Writers\Writer|null $writer = null;

	private EventLoop\TimerInterface|null $consumersTimer = null;

	/**
	 * @param array<Writers\WriterFactory> $writersFactories
	 * @param array<Servers\ServerFactory> $serversFactories
	 */
	public function __construct(
		private readonly DevicesDocuments\Connectors\Connector $connector,
		private readonly array $writersFactories,
		private readonly Queue\Queue $queue,
		private readonly Queue\Consumers $consumers,
		private readonly array $serversFactories,
		private readonly Protocol\Loader $accessoriesLoader,
		private readonly HomeKit\Logger $logger,
		private readonly EventLoop\LoopInterface $eventLoop,
	)
	{
		assert($this->connector instanceof Documents\Connectors\Connector);
	}

	/**
	 * @return Promise\PromiseInterface<bool>
	 *
	 * @throws ApplicationExceptions\InvalidArgument
	 * @throws ApplicationExceptions\InvalidState
	 * @throws ApplicationExceptions\MalformedInput
	 * @throws ApplicationExceptions\Mapping
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws ExchangeExceptions\InvalidArgument
	 * @throws Nette\IOException
	 * @throws SemVer\SemverException
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws ToolsExceptions\InvalidState
	 * @throws ToolsExceptions\Runtime
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function execute(bool $standalone = true): Promise\PromiseInterface
	{
		assert($this->connector instanceof Documents\Connectors\Connector);

		$this->logger->info(
			'Starting HomeKit connector service',
			[
				'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
				'type' => 'connector',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			],
		);

		$this->accessoriesLoader->load($this->connector);

		foreach ($this->serversFactories as $serverFactory) {
			$server = $serverFactory->create($this->connector);
			$server->initialize();
			$server->connect();

			$this->servers[] = $server;
		}

		foreach ($this->writersFactories as $writerFactory) {
			if (
				(
					$standalone
					&& $writerFactory instanceof Writers\ExchangeFactory
				) || (
					!$standalone
					&& $writerFactory instanceof Writers\EventFactory
				)
			) {
				$this->writer = $writerFactory->create($this->connector);
				$this->writer->connect();
			}
		}

		$this->consumersTimer = $this->eventLoop->addPeriodicTimer(
			self::QUEUE_PROCESSING_INTERVAL,
			async(function (): void {
				$this->consumers->consume();
			}),
		);

		$this->consumersTimer = $this->eventLoop->addPeriodicTimer(
			self::DRIVER_RELOAD_INTERVAL,
			function (): void {
				assert($this->connector instanceof Documents\Connectors\Connector);
				$this->accessoriesLoader->load($this->connector);
			},
		);

		$this->logger->info(
			'HomeKit connector service has been started',
			[
				'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
				'type' => 'connector',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			],
		);

		return Promise\resolve(true);
	}

	/**
	 * @return Promise\PromiseInterface<bool>
	 */
	public function discover(): Promise\PromiseInterface
	{
		assert($this->connector instanceof Documents\Connectors\Connector);

		return Promise\reject(
			new Exceptions\InvalidState('Devices discovery is not allowed for HomeKit connector type'),
		);
	}

	public function terminate(): void
	{
		assert($this->connector instanceof Documents\Connectors\Connector);

		$this->writer?->disconnect();

		if ($this->consumersTimer !== null && $this->queue->isEmpty()) {
			$this->eventLoop->cancelTimer($this->consumersTimer);
		}

		foreach ($this->servers as $server) {
			$server->disconnect();
		}

		$this->logger->info(
			'HomeKit connector has been terminated',
			[
				'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
				'type' => 'connector',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			],
		);
	}

	public function hasUnfinishedTasks(): bool
	{
		return !$this->queue->isEmpty() && $this->consumersTimer !== null;
	}

}
