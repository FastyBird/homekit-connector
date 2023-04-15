<?php declare(strict_types = 1);

/**
 * SecureConnection.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 * @since          1.0.0
 *
 * @date           26.09.22
 */

namespace FastyBird\Connector\HomeKit\Servers;

use Evenement;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use Nette;
use Psr\Log;
use React\Socket;
use React\Stream;
use SodiumException;
use function array_merge;
use function array_pop;
use function array_splice;
use function array_values;
use function count;
use function hash_hkdf;
use function is_array;
use function is_string;
use function pack;
use function sodium_crypto_aead_chacha20poly1305_ietf_decrypt;
use function sodium_crypto_aead_chacha20poly1305_ietf_encrypt;
use function unpack;

/**
 * HTTP secured server connection wrapper
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
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

	private int $securedRequestCnt = 0;

	private int $securedResponsesCnt = 0;

	private bool $securedRequest = false;

	private string|null $encryptionKey = null;

	private string|null $decryptionKey = null;

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly Entities\HomeKitConnector $connector,
		string|null $sharedKey,
		private readonly Socket\ConnectionInterface $connection,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->setSharedKey($sharedKey);

		$connection->on(
			'data',
			function (string $data): void {
				$this->securedRequest = false;

				$this->emit('data', [$this->decodeData($data)]);
			},
		);

		Stream\Util::forwardEvents($connection, $this, ['end', 'error', 'close', 'pipe', 'drain']);

		$this->logger = $logger ?? new Log\NullLogger();
	}

	public function setSharedKey(string|null $sharedKey): void
	{
		if ($sharedKey !== null) {
			$this->encryptionKey = hash_hkdf(
				'sha512',
				$sharedKey,
				32,
				self::INFO_CONTROL_READ,
				self::SALT_CONTROL,
			);

			$this->decryptionKey = hash_hkdf(
				'sha512',
				$sharedKey,
				32,
				self::INFO_CONTROL_WRITE,
				self::SALT_CONTROL,
			);
		}
	}

	public function getLocalAddress(): string|null
	{
		return $this->connection->getLocalAddress();
	}

	public function getRemoteAddress(): string|null
	{
		return $this->connection->getRemoteAddress();
	}

	public function isReadable(): bool
	{
		return $this->connection->isReadable();
	}

	public function isWritable(): bool
	{
		return $this->connection->isWritable();
	}

	public function write($data): bool
	{
		if (is_string($data) && $this->securedRequest) {
			$data = $this->encodeData($data);
		}

		return $this->connection->write($data);
	}

	public function pause(): void
	{
		$this->connection->pause();
	}

	public function resume(): void
	{
		$this->connection->resume();
	}

	public function end($data = null): void
	{
		$this->connection->end($data);
	}

	public function close(): void
	{
		$this->connection->close();
	}

	/**
	 * @param array<mixed> $options
	 */
	public function pipe(Stream\WritableStreamInterface $dest, array $options = []): Stream\WritableStreamInterface
	{
		return $this->connection->pipe($dest, $options);
	}

	private function decodeData(string $receivedData): string
	{
		if ($this->decryptionKey === null) {
			return $receivedData;
		}

		$binaryData = unpack('C*', $receivedData);

		if (!is_array($binaryData) || count($binaryData) <= self::ENCRYPTED_DATA_LENGTH + self::AUTH_TAG_LENGTH) {
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
			array_values((array) unpack('C*', pack('v', $this->securedRequestCnt))) + [0, 0, 0, 0, 0, 0, 0, 0],
		);

		try {
			$decryptedData = sodium_crypto_aead_chacha20poly1305_ietf_decrypt(
				pack('C*', ...$binaryData),
				pack('C*', ...$dataLength),
				pack('C*', ...$nonce),
				$this->decryptionKey,
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
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'secure-connection',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
					'connector' => [
						'id' => $this->connector->getPlainId(),
					],
				],
			);
		}

		return $receivedData;
	}

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
			array_values((array) unpack('C*', pack('v', $this->securedResponsesCnt))) + [0, 0, 0, 0, 0, 0, 0, 0],
		);

		try {
			$encryptedData = sodium_crypto_aead_chacha20poly1305_ietf_encrypt(
				pack('C*', ...$binaryData),
				pack('C*', ...$dataLength),
				pack('C*', ...$nonce),
				$this->encryptionKey,
			);

			$this->securedResponsesCnt++;

			return pack('C*', ...$dataLength) . $encryptedData;
		} catch (SodiumException $ex) {
			$this->logger->error(
				'Data encryption failed',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'secure-connection',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
					'connector' => [
						'id' => $this->connector->getPlainId(),
					],
				],
			);
		}

		return $data;
	}

}
