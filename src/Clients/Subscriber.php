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

namespace FastyBird\HomeKitConnector\Clients;

use FastyBird\Metadata;
use Nette;
use Psr\Log;
use React\Socket;
use SplObjectStorage;
use function array_diff;
use function array_key_exists;
use function in_array;

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

	/** @var SplObjectStorage<Socket\ConnectionInterface, null> */
	private SplObjectStorage $connections;

	/** @var Array<string, Array<string>> */
	private array $subscriptions;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(Log\LoggerInterface|null $logger = null)
	{
		$this->connections = new SplObjectStorage();
		$this->subscriptions = [];

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @param Socket\ConnectionInterface $connection
	 *
	 * @return void
	 */
	public function registerConnection(Socket\ConnectionInterface $connection): void
	{
		$this->logger->debug(
			'Registering client to subscriber',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type' => 'subscriber',
				'connection' => [
					'address' => $connection->getRemoteAddress(),
				],
			],
		);

		$this->connections->attach($connection);
	}

	/**
	 * @param Socket\ConnectionInterface $connection
	 *
	 * @return void
	 */
	public function unregisterConnection(Socket\ConnectionInterface $connection): void
	{
		$this->logger->debug(
			'Unregistering client from subscriber',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
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

	/**
	 * @param int $aid
	 * @param int $iid
	 * @param string $address
	 *
	 * @return void
	 */
	public function subscribe(int $aid, int $iid, string $address): void
	{
		$this->logger->debug(
			'Subscribing to characteristic',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type' => 'subscriber',
				'subscription' => [
					'aid' => $aid,
					'iid' => $iid,
					'address' => $address,
				],
			],
		);

		if (!array_key_exists($aid . '.' . $iid, $this->subscriptions)) {
			$this->subscriptions[$aid . '.' . $iid] = [];
		}

		$this->subscriptions[$aid . '.' . $iid][] = $address;
	}

	/**
	 * @param int $aid
	 * @param int $iid
	 * @param string $address
	 *
	 * @return void
	 */
	public function unsubscribe(int $aid, int $iid, string $address): void
	{
		$this->logger->debug(
			'Unsubscribing from characteristic',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type' => 'subscriber',
				'subscription' => [
					'aid' => $aid,
					'iid' => $iid,
					'address' => $address,
				],
			],
		);

		if (!array_key_exists($aid . '.' . $iid, $this->subscriptions)) {
			return;
		}

		if (in_array($address, $this->subscriptions[$aid . '.' . $iid], true)) {
			$this->subscriptions[$aid . '.' . $iid] = array_diff($this->subscriptions[$aid . '.' . $iid], [$address]);
		}
	}

	public function publish(): void
	{
		// TODO: Implement
	}

}
