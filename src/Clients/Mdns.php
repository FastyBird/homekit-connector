<?php declare(strict_types = 1);

/**
 * Client.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 * @since          0.19.0
 *
 * @date           17.09.22
 */

namespace FastyBird\HomeKitConnector\Clients;

use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;
use Psr\Log;
use React\Datagram;
use React\EventLoop;
use Throwable;

/**
 * mDNS connector discovery client
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Mdns implements Client
{

	use Nette\SmartObject;

	/** @var EventLoop\LoopInterface */
	private EventLoop\LoopInterface $eventLoop;

	/** @var Datagram\SocketInterface|null */
	private ?Datagram\SocketInterface $server = null;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		EventLoop\LoopInterface $eventLoop,
		?Log\LoggerInterface $logger = null
	) {
		$this->eventLoop = $eventLoop;

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect(): void
	{
		$factory = new Datagram\Factory($this->eventLoop);

		$factory->createServer('0.0.0.0:5353')
			->then(function (Datagram\Socket $server): void {
				$server->on('message', function(string $message, string $address): void {

				});

				$server->on('error', function(Throwable $ex): void {
					$this->logger->error(
						'An error occurred during server handling',
						[
							'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
							'type'      => 'mdns-client',
							'exception' => [
								'message' => $ex->getMessage(),
								'code'    => $ex->getCode(),
							],
						]
					);
				});

				$server->on('close', function(): void {

				});
			})
			->otherwise(function (Throwable $ex): void {
				$this->logger->error(
					'Could not create mDNS discovery server',
					[
						'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
						'type'      => 'mdns-client',
						'exception' => [
							'message' => $ex->getMessage(),
							'code'    => $ex->getCode(),
						],
					]
				);
			});
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		$this->server?->close();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isConnected(): bool
	{
		return $this->server !== null;
	}

	public function writeDeviceControl(MetadataEntities\Actions\IActionDeviceControlEntity $action): void
	{
		// TODO: Implement writeDeviceControl() method.
	}

	public function writeChannelControl(MetadataEntities\Actions\IActionChannelControlEntity $action): void
	{
		// TODO: Implement writeChannelControl() method.
	}

}
