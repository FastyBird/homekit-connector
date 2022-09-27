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

	private const SALT_CONTROL = 'Control-Salt';
	private const INFO_CONTROL_WRITE = 'Control-Write-Encryption-Key';
	private const INFO_CONTROL_READ = 'Control-Read-Encryption-Key';

	/** @var int */
	private int $securedRequestCnt = 0;

	/** @var int */
	private int $securedResponsesCnt = 0;

	/** @var bool */
	private bool $securedRequest = false;

	/** @var string|null */
	private ?string $encryptionKey = null;

	/** @var string|null */
	private ?string $decryptionKey = null;

	/** @var MetadataEntities\Modules\DevicesModule\IConnectorEntity */
	private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector;

	/** @var Socket\ConnectionInterface */
	private Socket\ConnectionInterface $connection;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param string|null $sharedKey
	 * @param Socket\ConnectionInterface $connection
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		?string $sharedKey,
		Socket\ConnectionInterface $connection,
		?Log\LoggerInterface $logger = null
	) {
		$this->connector = $connector;

		$this->connection = $connection;

		if ($sharedKey !== null) {
			$this->encryptionKey = hash_hkdf(
				'sha512',
				$sharedKey,
				32,
				self::INFO_CONTROL_READ,
				self::SALT_CONTROL
			);

			$this->decryptionKey = hash_hkdf(
				'sha512',
				$sharedKey,
				32,
				self::INFO_CONTROL_WRITE,
				self::SALT_CONTROL
			);
		}

		$connection->on(
			'data',
			function (string $data): void {
				$this->securedRequest = false;

				$this->emit('data', [$this->decodeData($data)]);
			}
		);

		Stream\Util::forwardEvents($connection, $this, ['end', 'error', 'close', 'pipe', 'drain']);

		$this->logger = $logger ?? new Log\NullLogger();
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
	public function write($data): bool
	{
		if (is_string($data) && $this->securedRequest) {
			$data = $this->encodeData($data);
		}

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
	public function pipe(Stream\WritableStreamInterface $dest, array $options = []): Stream\WritableStreamInterface
	{
		return $this->connection->pipe($dest, $options);
	}

	/**
	 * @param string $receivedData
	 *
	 * @return string
	 */
	private function decodeData(string $receivedData): string
	{
		if ($this->decryptionKey === null) {
			return $receivedData;
		}

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
			(array_values((array) unpack('C*', pack('v', $this->securedRequestCnt))) + [0, 0, 0, 0, 0, 0, 0, 0])
		);

		try {
			$decryptedData = sodium_crypto_aead_chacha20poly1305_ietf_decrypt(
				pack('C*', ...$binaryData),
				pack('C*', ...$dataLength),
				pack('C*', ...$nonce),
				$this->decryptionKey
			);

			if ($decryptedData !== false) {
				$this->securedRequestCnt++;
				$this->securedRequest = true;

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
	 * @param string $data
	 *
	 * @return string
	 */
	private function encodeData(string $data): string
	{
		if ($this->encryptionKey === null) {
			return $data;
		}

		$binaryData = unpack('C*', $data);

		if (!is_array($binaryData) || count($binaryData) <= self::AUTH_TAG_LENGTH) {
			return $data;
		}

		$dataLength = unpack('C*', pack('v', count($binaryData)));

		if ($dataLength === false) {
			return $data;
		}

		$nonce = array_merge(
			[0, 0, 0, 0],
			(array_values((array) unpack('C*', pack('v', $this->securedResponsesCnt))) + [0, 0, 0, 0, 0, 0, 0, 0])
		);

		try {
			$encryptedData = sodium_crypto_aead_chacha20poly1305_ietf_encrypt(
				pack('C*', ...$binaryData),
				pack('C*', ...$dataLength),
				pack('C*', ...$nonce),
				$this->encryptionKey
			);

			$this->securedResponsesCnt++;

			return pack('C*', ...$dataLength) . $encryptedData;

		} catch (SodiumException $ex) {
			$this->logger->error(
				'Data encryption failed',
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

		return $data;
	}

}
