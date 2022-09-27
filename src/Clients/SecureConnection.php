<?php declare(strict_types = 1);

/**
 * SecureConnection.php
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

use Evenement;
use FastyBird\HomeKitConnector\Models;
use FastyBird\HomeKitConnector\Queries;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;
use Psr\Log;
use React\Socket;
use React\Stream;
use SodiumException;

/**
 * HTTP secured server connection wrapper
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class SecureConnection extends Evenement\EventEmitter implements Socket\ConnectionInterface
{

	use Nette\SmartObject;

	private const ENCRYPTED_DATA_LENGTH = 2;
	private const AUTH_TAG_LENGTH = 16;

	/** @var int */
	private int $requestCnt = 0;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Socket\ConnectionInterface */
	private Socket\ConnectionInterface $connection;

	/** @var Models\Sessions\SessionsRepository */
	private Models\Sessions\SessionsRepository $sessionsRepository;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Socket\ConnectionInterface $connection
	 * @param Models\Sessions\SessionsRepository $sessionsRepository
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Socket\ConnectionInterface $connection,
		Models\Sessions\SessionsRepository $sessionsRepository,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;
		$this->connection = $connection;

		$this->sessionsRepository = $sessionsRepository;

		$connection->on(
			'data',
			function (string $data): void {
				$findSessionQuery = new Queries\FindSessionsQuery();
				$findSessionQuery->byConnectorId($this->connector->getId());

				$session = $this->sessionsRepository->findOneBy($findSessionQuery);

				if ($session !== null && $session->getDecryptKey() !== null) {
					$data = $this->decodeData($data, $session->getDecryptKey());
				}

				$this->emit('data', [$data]);
			}
		);

		Stream\Util::forwardEvents($connection, $this, ['end', 'error', 'close', 'pipe', 'drain']);

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @param string $receivedData
	 * @param string $decryptKey
	 *
	 * @return string
	 */
	private function decodeData(string $receivedData, string $decryptKey): string
	{
		$binaryData = unpack('C*', $receivedData);

		if (!is_array($binaryData) || count($binaryData) <= (self::ENCRYPTED_DATA_LENGTH + self::AUTH_TAG_LENGTH)) {
			return $receivedData;
		}

		$dataLength = array_splice($binaryData, 0, self::ENCRYPTED_DATA_LENGTH);
		$dataLengthFormatted = unpack('v', pack('C*', ...$dataLength));

		if ($dataLengthFormatted === false || $dataLengthFormatted === []) {
			return $receivedData;
		}

		$dataLengthFormatted = (int) array_pop($dataLengthFormatted);

		if (count($binaryData) !== $dataLengthFormatted + self::AUTH_TAG_LENGTH) {
			return $receivedData;
		}

		$nonce = array_merge(
			[0, 0, 0, 0],
			(array_values((array) unpack('C*', pack('v', $this->requestCnt))) + [0, 0, 0, 0, 0, 0, 0, 0])
		);

		try {
			$decryptedData = sodium_crypto_aead_chacha20poly1305_ietf_decrypt(
				pack('C*', ...$binaryData),
				pack('C*', ...$dataLength),
				pack('C*', ...$nonce),
				$decryptKey
			);

			if ($decryptedData !== false) {
				$this->requestCnt++;

				return $decryptedData;
			}
		} catch (SodiumException $ex) {
			$this->logger->error(
				'Data decryption failed',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'secure-connection',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				]
			);
		}

		return $receivedData;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getLocalAddress(): ?string
	{
		return $this->connection->getLocalAddress();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getRemoteAddress(): ?string
	{
		return $this->connection->getRemoteAddress();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isReadable(): bool
	{
		return $this->connection->isReadable();
	}

	/**
	 * {@inheritDoc}
	 */
	public function isWritable(): bool
	{
		return $this->connection->isWritable();
	}

	/**
	 * {@inheritDoc}
	 */
	public function write($data)
	{
		return $this->connection->write($data);
	}

	/**
	 * {@inheritDoc}
	 */
	public function pause()
	{
		$this->connection->pause();
	}

	/**
	 * {@inheritDoc}
	 */
	public function resume()
	{
		$this->connection->resume();
	}

	/**
	 * {@inheritDoc}
	 */
	public function end($data = null)
	{
		$this->connection->end($data);
	}

	/**
	 * {@inheritDoc}
	 */
	public function close()
	{
		$this->connection->close();
	}

	/**
	 * @param Stream\WritableStreamInterface $dest
	 * @param mixed[] $options
	 *
	 * @return Stream\WritableStreamInterface
	 */
	public function pipe(Stream\WritableStreamInterface $dest, array $options = [])
	{
		return $this->connection->pipe($dest, $options);
	}

}
