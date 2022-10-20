<?php declare(strict_types = 1);

/**
 * SecureServer.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 * @since          0.19.0
 *
 * @date           26.09.22
 */

namespace FastyBird\Connector\HomeKit\Servers;

use Evenement\EventEmitter;
use FastyBird\Library\Metadata\Entities as MetadataEntities;
use Nette;
use React\Socket;
use SplObjectStorage;
use function str_replace;

/**
 * HTTP secured server wrapper
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SecureServer extends EventEmitter implements Socket\ServerInterface
{

	use Nette\SmartObject;

	/** @var SplObjectStorage<SecureConnection, null> */
	private SplObjectStorage $activeConnections;

	public function __construct(
		private readonly MetadataEntities\DevicesModule\Connector $connector,
		private readonly Socket\ServerInterface $server,
		private readonly SecureConnectionFactory $secureConnectionFactory,
		private string|null $sharedKey = null,
	)
	{
		$this->activeConnections = new SplObjectStorage();

		$this->server->on('connection', function (Socket\ConnectionInterface $connection): void {
			$securedConnection = $this->secureConnectionFactory->create(
				$this->connector,
				$this->sharedKey,
				$connection,
			);

			$this->emit('connection', [$securedConnection]);

			$this->activeConnections->attach($securedConnection);

			$securedConnection->on('close', function () use ($securedConnection): void {
				$this->activeConnections->detach($securedConnection);
			});
		});

		$this->server->on('error', function ($error): void {
			$this->emit('error', [$error]);
		});
	}

	public function setSharedKey(string|null $sharedKey): void
	{
		$this->sharedKey = $sharedKey;

		$this->activeConnections->rewind();

		foreach ($this->activeConnections as $connection) {
			$connection->setSharedKey($sharedKey);
		}
	}

	public function getAddress(): string|null
	{
		$address = $this->server->getAddress();

		if ($address === null) {
			return null;
		}

		return str_replace('tcp://', 'tls://', $address);
	}

	public function pause(): void
	{
		$this->server->pause();
	}

	public function resume(): void
	{
		$this->server->resume();
	}

	public function close(): void
	{
		$this->server->close();
	}

}
