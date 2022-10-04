<?php declare(strict_types = 1);

/**
 * SecureServer.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 * @since          0.19.0
 *
 * @date           26.09.22
 */

namespace FastyBird\HomeKitConnector\Servers;

use Evenement\EventEmitter;
use FastyBird\Metadata\Entities as MetadataEntities;
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

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Socket\ServerInterface $server
	 * @param SecureConnectionFactory $secureConnectionFactory
	 * @param string|null $sharedKey
	 */
	public function __construct(
		private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		private Socket\ServerInterface $server,
		private SecureConnectionFactory $secureConnectionFactory,
		private string|null $sharedKey = null,
	) {
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

	/**
	 * @param string|null $sharedKey
	 *
	 * @return void
	 */
	public function setSharedKey(string|null $sharedKey): void
	{
		$this->sharedKey = $sharedKey;

		$this->activeConnections->rewind();

		foreach ($this->activeConnections as $connection) {
			$connection->setSharedKey($sharedKey);
		}
	}

	/**
	 * @return string|null
	 */
	public function getAddress(): string|null
	{
		$address = $this->server->getAddress();

		if ($address === null) {
			return null;
		}

		return str_replace('tcp://', 'tls://', $address);
	}

	/**
	 * @return void
	 */
	public function pause(): void
	{
		$this->server->pause();
	}

	/**
	 * @return void
	 */
	public function resume(): void
	{
		$this->server->resume();
	}

	/**
	 * @return void
	 */
	public function close(): void
	{
		$this->server->close();
	}

}
