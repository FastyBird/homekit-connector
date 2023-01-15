<?php declare(strict_types = 1);

/**
 * Subscriber.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 * @since          0.19.0
 *
 * @date           02.10.22
 */

namespace FastyBird\Connector\HomeKit\Clients;

use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use Nette;
use Nette\Utils;
use Psr\Log;
use React\EventLoop;
use React\Socket;
use SplObjectStorage;
use function array_diff;
use function array_key_exists;
use function in_array;
use function parse_url;
use function sprintf;
use function strlen;
use function strval;
use function trim;
use const PHP_URL_HOST;

/**
 * HTTP clients exchange subscriber
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Subscriber
{

	use Nette\SmartObject;

	private const PUBLISH_EVENT_DELAY = 0.5;

	/** @var SplObjectStorage<Socket\ConnectionInterface, string> */
	private SplObjectStorage $connections;

	/** @var array<string, array<string>> */
	private array $subscriptions;

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly EventLoop\LoopInterface $eventLoop,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->connections = new SplObjectStorage();
		$this->subscriptions = [];

		$this->logger = $logger ?? new Log\NullLogger();
	}

	public function registerConnection(Socket\ConnectionInterface $connection): void
	{
		if ($connection->getRemoteAddress() === null) {
			$this->logger->warning(
				'Connected client is without defined IP address and could not be registered to subscriber',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'subscriber',
				],
			);

			return;
		}

		$this->logger->debug(
			'Registering client to subscriber',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'subscriber',
				'connection' => [
					'address' => $connection->getRemoteAddress(),
				],
			],
		);

		$ip = trim(strval(parse_url(strval($connection->getRemoteAddress()), PHP_URL_HOST)), '[]');

		$this->connections->attach($connection, $ip);
	}

	public function unregisterConnection(Socket\ConnectionInterface $connection): void
	{
		$this->logger->debug(
			'Unregistering client from subscriber',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'subscriber',
				'connection' => [
					'address' => $connection->getRemoteAddress(),
				],
			],
		);

		$this->connections->rewind();

		foreach ($this->connections as $registeredConnection) {
			if ($connection->getRemoteAddress() === $connection->getRemoteAddress()) {
				$this->connections->detach($registeredConnection);

				return;
			}
		}
	}

	public function subscribe(int $aid, int $iid, string $address): void
	{
		$this->logger->debug(
			'Subscribing to characteristic',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'subscriber',
				'subscription' => [
					'aid' => $aid,
					'iid' => $iid,
					'address' => $address,
				],
			],
		);

		if (!array_key_exists($this->getTopic($aid, $iid), $this->subscriptions)) {
			$this->subscriptions[$this->getTopic($aid, $iid)] = [];
		}

		$this->subscriptions[$this->getTopic($aid, $iid)][] = $address;
	}

	public function unsubscribe(int $aid, int $iid, string $address): void
	{
		$this->logger->debug(
			'Unsubscribing from characteristic',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'subscriber',
				'subscription' => [
					'aid' => $aid,
					'iid' => $iid,
					'address' => $address,
				],
			],
		);

		if (!array_key_exists($this->getTopic($aid, $iid), $this->subscriptions)) {
			return;
		}

		if (in_array($address, $this->subscriptions[$this->getTopic($aid, $iid)], true)) {
			$this->subscriptions[$this->getTopic($aid, $iid)] = array_diff(
				$this->subscriptions[$this->getTopic($aid, $iid)],
				[$address],
			);
		}
	}

	public function publish(
		int $aid,
		int $iid,
		bool|float|int|string|null $value = null,
		bool $immediate = true,
		string|null $senderAddress = null,
	): void
	{
		// Skip invalid value
		if ($value === null) {
			return;
		}

		if ($immediate) {
			$this->eventLoop->futureTick(
				function () use ($aid, $iid, $value, $senderAddress): void {
					$this->sendToClients($aid, $iid, $value, $senderAddress);
				},
			);
		} else {
			$this->eventLoop->addTimer(
				self::PUBLISH_EVENT_DELAY,
				function () use ($aid, $iid, $value, $senderAddress): void {
					$this->sendToClients($aid, $iid, $value, $senderAddress);
				},
			);
		}
	}

	private function sendToClients(
		int $aid,
		int $iid,
		bool|float|int|string $value,
		string|null $senderAddress = null,
	): void
	{
		if (!array_key_exists($this->getTopic($aid, $iid), $this->subscriptions)) {
			return;
		}

		$data = $this->buildEvent($aid, $iid, $value);

		if ($data === null) {
			$this->logger->error(
				'Event message could not be created',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'subscriber',
					'data' => [
						'aid' => $aid,
						'iid' => $iid,
						'value' => $value,
					],
				],
			);

			return;
		}

		$this->connections->rewind();

		foreach ($this->connections as $connection) {
			$ip = $this->connections[$connection];

			if (!in_array($ip, $this->subscriptions[$this->getTopic($aid, $iid)], true)) {
				continue;
			}

			if ($senderAddress === null || $ip !== $senderAddress) {
				$connection->write($data);
			}
		}
	}

	private function buildEvent(int $aid, int $iid, bool|float|int|string $value): string|null
	{
		$body = "EVENT/1.0 200 OK\r\nContent-Type: %s\r\nContent-Length: %d\r\n\r\n%s";

		try {
			$content = Utils\Json::encode([
				Types\Representation::REPR_CHARS => [
					[
						Types\Representation::REPR_AID => $aid,
						Types\Representation::REPR_IID => $iid,
						Types\Representation::REPR_VALUE => $value,
					],
				],
			]);
		} catch (Utils\JsonException) {
			return null;
		}

		return sprintf($body, Servers\Http::JSON_CONTENT_TYPE, strlen($content), $content);
	}

	private function getTopic(int $aid, int $iid): string
	{
		return $aid . '.' . $iid;
	}

}
