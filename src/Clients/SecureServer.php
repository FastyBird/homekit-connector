<?php declare(strict_types = 1);

/**
 * SecureServer.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 * @since          0.19.0
 *
 * @date           26.09.22
 */

namespace FastyBird\HomeKitConnector\Clients;

use Evenement\EventEmitter;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;
use React\Socket;

/**
 * HTTP secured server wrapper
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SecureServer extends EventEmitter implements Socket\ServerInterface
{

	use Nette\SmartObject;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Socket\ServerInterface */
	private Socket\ServerInterface $server;

	/** @var SecureConnectionFactory */
	private SecureConnectionFactory $secureConnectionFactory;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Socket\ServerInterface $server
	 * @param SecureConnectionFactory $secureConnectionFactory
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Socket\ServerInterface $server,
		SecureConnectionFactory $secureConnectionFactory
	) {
		$this->connector = $connector;

		$this->server = $server;
		$this->secureConnectionFactory = $secureConnectionFactory;

		$this->server->on('connection', function ($connection): void {
			$this->emit('connection', [$this->secureConnectionFactory->create($this->connector, $connection)]);
		});

		$this->server->on('error', function ($error): void {
			$this->emit('error', [$error]);
		});
	}

	/**
	 * @return string|null
	 */
	public function getAddress(): ?string
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
