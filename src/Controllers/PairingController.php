<?php declare(strict_types = 1);

/**
 * PairingController.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Controllers
 * @since          0.19.0
 *
 * @date           19.09.22
 */

namespace FastyBird\Connector\HomeKit\Controllers;

use Brick\Math;
use Doctrine\DBAL;
use Elliptic\EdDSA;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Models;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use IPub\DoctrineOrmQuery;
use IPub\SlimRouter;
use Nette\Utils\ArrayHash;
use Psr\Http\Message;
use Ramsey\Uuid;
use RuntimeException;
use SodiumException;
use Throwable;
use function array_key_exists;
use function array_pop;
use function array_values;
use function assert;
use function bin2hex;
use function Curve25519\publicKey;
use function Curve25519\sharedKey;
use function hash_hkdf;
use function hex2bin;
use function is_array;
use function is_int;
use function is_string;
use function pack;
use function sodium_crypto_aead_chacha20poly1305_ietf_decrypt;
use function sodium_crypto_aead_chacha20poly1305_ietf_encrypt;
use function strval;
use function unpack;

/**
 * Connector pairing process controller
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Controllers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class PairingController extends BaseController
{

	private const MAX_AUTHENTICATION_ATTEMPTS = 100;

	private const SRP_USERNAME = 'Pair-Setup';

	private const SALT_ENCRYPT = 'Pair-Setup-Encrypt-Salt';

	private const INFO_ENCRYPT = 'Pair-Setup-Encrypt-Info';

	private const SALT_CONTROLLER = 'Pair-Setup-Controller-Sign-Salt';

	private const INFO_CONTROLLER = 'Pair-Setup-Controller-Sign-Info';

	private const SALT_ACCESSORY = 'Pair-Setup-Accessory-Sign-Salt';

	private const INFO_ACCESSORY = 'Pair-Setup-Accessory-Sign-Info';

	private const SALT_VERIFY = 'Pair-Verify-Encrypt-Salt';

	private const INFO_VERIFY = 'Pair-Verify-Encrypt-Info';

	private const NONCE_SETUP_M5
		= [
			0, // 0
			0, // 0
			0, // 0
			0, // 0
			80, // P
			83, // S
			45, // -
			77, // M
			115, // s
			103, // g
			48, // 0
			53, // 5
		];

	private const NONCE_SETUP_M6
		= [
			0, // 0
			0, // 0
			0, // 0
			0, // 0
			80, // P
			83, // S
			45, // -
			77, // M
			115, // s
			103, // g
			48, // 0
			54, // 6
		];

	private const NONCE_VERIFY_M2
		= [
			0, // 0
			0, // 0
			0, // 0
			0, // 0
			80, // P
			86, // V
			45, // -
			77, // M
			115, // s
			103, // g
			48, // 0
			50, // 2
		];

	private const NONCE_VERIFY_M3
		= [
			0, // 0
			0, // 0
			0, // 0
			0, // 0
			80, // P
			86, // V
			45, // -
			77, // M
			115, // s
			103, // g
			48, // 0
			51, // 3
		];

	private bool $activePairing = false;

	private int $pairingAttempts = 0;

	private Protocol\Srp|null $srp = null;

	private Types\TlvState $expectedState;

	private EdDSA $edDsa;

	public function __construct(
		private readonly Helpers\Connector $connectorHelper,
		private readonly Protocol\Tlv $tlv,
		private readonly Models\Clients\ClientsRepository $clientsRepository,
		private readonly Models\Clients\ClientsManager $clientsManager,
		private readonly DevicesModels\Connectors\ConnectorsRepository $connectorsRepository,
		private readonly DevicesUtilities\Database $databaseHelper,
	)
	{
		$this->edDsa = new EdDSA('ed25519');

		$this->expectedState = Types\TlvState::get(Types\TlvState::STATE_M1);
	}

	/**
	 * @throws DBAL\Exception
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws InvalidArgumentException
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 * @throws RuntimeException
	 */
	public function setup(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		$this->logger->debug(
			'Requested pairing setup',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'pairing-controller',
				'request' => [
					'query' => $request->getQueryParams(),
				],
			],
		);

		$connectorId = strval($request->getAttribute(Servers\Http::REQUEST_ATTRIBUTE_CONNECTOR));

		if (!Uuid\Uuid::isValid($connectorId)) {
			throw new Exceptions\InvalidState('Connector id could not be determined');
		}

		$connectorId = Uuid\Uuid::fromString($connectorId);

		$paired = $this->connectorHelper->getConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED),
		);

		if ((bool) $paired === true) {
			$result = [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		} else {
			$tlv = $this->tlv->decode($request->getBody()->getContents());

			if ($tlv === []) {
				throw new Exceptions\InvalidArgument('Provided TLV content is not valid');
			}

			$tlvEntry = array_pop($tlv);

			$requestedState = array_key_exists(
				Types\TlvCode::CODE_STATE,
				$tlvEntry,
			)
				? $tlvEntry[Types\TlvCode::CODE_STATE]
				: null;

			if (
				$requestedState === Types\TlvState::STATE_M1
				&& array_key_exists(Types\TlvCode::CODE_METHOD, $tlvEntry)
				&& $tlvEntry[Types\TlvCode::CODE_METHOD] === Types\TlvMethod::METHOD_RESERVED
			) {
				$result = $this->srpStart($connectorId);

				$this->expectedState = Types\TlvState::get(Types\TlvState::STATE_M3);

			} elseif (
				$requestedState === Types\TlvState::STATE_M3
				&& array_key_exists(Types\TlvCode::CODE_PUBLIC_KEY, $tlvEntry)
				&& is_array($tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY])
				&& array_key_exists(Types\TlvCode::CODE_PROOF, $tlvEntry)
				&& is_array($tlvEntry[Types\TlvCode::CODE_PROOF])
			) {
				$result = $this->srpFinish(
					$connectorId,
					$tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY],
					$tlvEntry[Types\TlvCode::CODE_PROOF],
				);

				$this->expectedState = Types\TlvState::get(Types\TlvState::STATE_M5);

			} elseif (
				$requestedState === Types\TlvState::STATE_M5
				&& array_key_exists(Types\TlvCode::CODE_ENCRYPTED_DATA, $tlvEntry)
				&& is_array($tlvEntry[Types\TlvCode::CODE_ENCRYPTED_DATA])
			) {
				$result = $this->exchange(
					$connectorId,
					$tlvEntry[Types\TlvCode::CODE_ENCRYPTED_DATA],
				);

				$this->expectedState = Types\TlvState::get(Types\TlvState::STATE_M1);

			} else {
				throw new Exceptions\InvalidState('Unknown data received');
			}
		}

		if (array_key_exists(Types\TlvCode::CODE_ERROR, $result)) {
			$this->srp = null;
			$this->activePairing = false;
			$this->expectedState = Types\TlvState::get(Types\TlvState::STATE_M1);
		}

		$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', Servers\Http::PAIRING_CONTENT_TYPE);
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString($this->tlv->encode($result)));

		return $response;
	}

	/**
	 * @throws DBAL\Exception
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws InvalidArgumentException
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 * @throws RuntimeException
	 */
	public function verify(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		$this->logger->debug(
			'Requested pairing verify',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'pairing-controller',
				'request' => [
					'query' => $request->getQueryParams(),
				],
			],
		);

		$connectorId = strval($request->getAttribute(Servers\Http::REQUEST_ATTRIBUTE_CONNECTOR));

		if (!Uuid\Uuid::isValid($connectorId)) {
			throw new Exceptions\InvalidState('Connector id could not be determined');
		}

		$connectorId = Uuid\Uuid::fromString($connectorId);

		$paired = $this->connectorHelper->getConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED),
		);

		if ((bool) $paired === false) {
			$result = [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		} else {
			$tlv = $this->tlv->decode($request->getBody()->getContents());

			if ($tlv === []) {
				throw new Exceptions\InvalidArgument('Provided TLV content is not valid');
			}

			$tlvEntry = array_pop($tlv);

			$requestedState = array_key_exists(
				Types\TlvCode::CODE_STATE,
				$tlvEntry,
			)
				? $tlvEntry[Types\TlvCode::CODE_STATE]
				: null;

			if (
				$requestedState === Types\TlvState::STATE_M1
				&& array_key_exists(Types\TlvCode::CODE_PUBLIC_KEY, $tlvEntry)
				&& is_array($tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY])
			) {
				$result = $this->verifyStart($connectorId, $tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY]);

			} elseif (
				$requestedState === Types\TlvState::STATE_M3
				&& array_key_exists(Types\TlvCode::CODE_ENCRYPTED_DATA, $tlvEntry)
				&& is_array($tlvEntry[Types\TlvCode::CODE_ENCRYPTED_DATA])
			) {
				$result = $this->verifyFinish($connectorId, $tlvEntry[Types\TlvCode::CODE_ENCRYPTED_DATA]);

			} else {
				throw new Exceptions\InvalidState('Unknown data received');
			}
		}

		$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', Servers\Http::PAIRING_CONTENT_TYPE);
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString($this->tlv->encode($result)));

		return $response;
	}

	/**
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 */
	public function pairings(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		$this->logger->debug(
			'Requested clients pairing update',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'pairing-controller',
				'request' => [
					'query' => $request->getQueryParams(),
				],
			],
		);

		$connectorId = strval($request->getAttribute(Servers\Http::REQUEST_ATTRIBUTE_CONNECTOR));

		if (!Uuid\Uuid::isValid($connectorId)) {
			throw new Exceptions\InvalidState('Connector id could not be determined');
		}

		$connectorId = Uuid\Uuid::fromString($connectorId);

		try {
			$paired = $this->connectorHelper->getConfiguration(
				$connectorId,
				Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED),
			);
		} catch (Throwable) {
			$result = [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];

			$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
			$response = $response->withHeader('Content-Type', Servers\Http::PAIRING_CONTENT_TYPE);
			$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString($this->tlv->encode($result)));

			return $response;
		}

		if ((bool) $paired === false) {
			$result = [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		} else {
			$tlv = $this->tlv->decode($request->getBody()->getContents());

			if ($tlv === []) {
				throw new Exceptions\InvalidArgument('Provided TLV content is not valid');
			}

			$tlvEntry = array_pop($tlv);

			$requestedState = array_key_exists(
				Types\TlvCode::CODE_STATE,
				$tlvEntry,
			)
				? $tlvEntry[Types\TlvCode::CODE_STATE]
				: null;
			$method = array_key_exists(Types\TlvCode::CODE_METHOD, $tlvEntry)
				? $tlvEntry[Types\TlvCode::CODE_METHOD]
				: null;

			if (
				$method === Types\TlvMethod::METHOD_LIST_PAIRINGS
				&& $requestedState === Types\TlvState::STATE_M1
			) {
				$result = $this->listPairings($connectorId);

			} elseif (
				$method === Types\TlvMethod::METHOD_ADD_PAIRING
				&& $requestedState === Types\TlvState::STATE_M1
				&& array_key_exists(Types\TlvCode::CODE_IDENTIFIER, $tlvEntry)
				&& is_string($tlvEntry[Types\TlvCode::CODE_IDENTIFIER])
				&& array_key_exists(Types\TlvCode::CODE_PUBLIC_KEY, $tlvEntry)
				&& is_array($tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY])
				&& array_key_exists(Types\TlvCode::CODE_PERMISSIONS, $tlvEntry)
				&& is_int($tlvEntry[Types\TlvCode::CODE_PERMISSIONS])
			) {
				$result = $this->addPairing(
					$connectorId,
					$tlvEntry[Types\TlvCode::CODE_IDENTIFIER],
					$tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY],
					$tlvEntry[Types\TlvCode::CODE_PERMISSIONS],
				);
			} elseif (
				$method === Types\TlvMethod::METHOD_REMOVE_PAIRING
				&& $requestedState === Types\TlvState::STATE_M1
				&& array_key_exists(Types\TlvCode::CODE_IDENTIFIER, $tlvEntry)
				&& is_string($tlvEntry[Types\TlvCode::CODE_IDENTIFIER])
			) {
				$result = $this->removePairing(
					$connectorId,
					$tlvEntry[Types\TlvCode::CODE_IDENTIFIER],
				);
			} else {
				throw new Exceptions\InvalidState('Unknown data received');
			}
		}

		$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', Servers\Http::PAIRING_CONTENT_TYPE);
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString($this->tlv->encode($result)));

		return $response;
	}

	/**
	 * @return Array<int, Array<int, (int|Array<int>|string)>>
	 *
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws DevicesExceptions\Runtime
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws InvalidArgumentException
	 * @throws Math\Exception\MathException
	 * @throws Math\Exception\NegativeNumberException
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	private function srpStart(Uuid\UuidInterface $connectorId): array
	{
		$paired = $this->connectorHelper->getConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED),
		);

		if ((bool) $paired) {
			$this->logger->error(
				'Accessory already paired, cannot accept additional pairings',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'srp-start',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNAVAILABLE,
				],
			];
		}

		if ($this->pairingAttempts >= self::MAX_AUTHENTICATION_ATTEMPTS) {
			$this->logger->error(
				'Max authentication attempts reached',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'srp-start',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_MAX_TRIES,
				],
			];
		}

		if (!$this->expectedState->equalsValue(Types\TlvState::STATE_M1)) {
			$this->logger->error(
				'Unexpected pairing setup state. Expected is M1',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'srp-start',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		if ($this->activePairing) {
			$this->logger->error(
				'Currently perform pair setup operation with a different controller',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'srp-start',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_BUSY,
				],
			];
		}

		$this->activePairing = true;

		$pinCode = $this->connectorHelper->getConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_PIN_CODE),
		);

		$this->srp = new Protocol\Srp(self::SRP_USERNAME, strval($pinCode));

		$serverPublicKey = unpack('C*', $this->srp->getServerPublicKey()->toBytes(false));

		if ($serverPublicKey === false) {
			$this->logger->error(
				'Server public key could not be converted to bytes',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'srp-start',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$salt = unpack('C*', $this->srp->getSalt());

		if ($salt === false) {
			$this->logger->error(
				'Slat could not be converted to bytes',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'srp-start',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$this->logger->debug(
			'SRP start success',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'pairing-controller',
				'connector' => [
					'id' => $connectorId->toString(),
				],
				'pairing' => [
					'type' => 'srp-start',
					'state' => Types\TlvState::STATE_M2,
				],
			],
		);

		return [
			[
				Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
				Types\TlvCode::CODE_PUBLIC_KEY => $serverPublicKey,
				Types\TlvCode::CODE_SALT => $salt,
			],
		];
	}

	/**
	 * @param Array<int> $clientPublicKey
	 * @param Array<int> $clientProof
	 *
	 * @return Array<int, Array<int, (int|Array<int>|string)>>
	 *
	 * @throws Math\Exception\MathException
	 * @throws Math\Exception\NumberFormatException
	 */
	private function srpFinish(
		Uuid\UuidInterface $connectorId,
		array $clientPublicKey,
		array $clientProof,
	): array
	{
		if ($this->srp === null || !$this->expectedState->equalsValue(Types\TlvState::STATE_M3)) {
			$this->logger->error(
				'Unexpected pairing setup state. Expected is M3',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'srp-finish',
						'state' => Types\TlvState::STATE_M4,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$this->srp->computeSharedSessionKey(
			Math\BigInteger::fromBytes(pack('C*', ...$clientPublicKey), false),
		);

		if (!$this->srp->verifyProof(pack('C*', ...$clientProof))) {
			$this->logger->error(
				'Incorrect pin code, try again',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'srp-finish',
						'state' => Types\TlvState::STATE_M4,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		if ($this->srp->getServerProof() === null) {
			$this->logger->error(
				'Server proof of session key is not computed',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'srp-finish',
						'state' => Types\TlvState::STATE_M4,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$serverProof = unpack('C*', $this->srp->getServerProof());

		if ($serverProof === false) {
			$this->logger->error(
				'Server proof of session key could not be converted to binary array',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'srp-finish',
						'state' => Types\TlvState::STATE_M4,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$this->logger->debug(
			'SRP finish success',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'pairing-controller',
				'connector' => [
					'id' => $connectorId->toString(),
				],
				'pairing' => [
					'type' => 'srp-finish',
					'state' => Types\TlvState::STATE_M4,
				],
			],
		);

		return [
			[
				Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
				Types\TlvCode::CODE_PROOF => $serverProof,
			],
		];
	}

	/**
	 * @param Array<int> $encryptedData
	 *
	 * @return Array<int, Array<int, (int|Array<int>|string)>>
	 *
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws DevicesExceptions\Runtime
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	public function exchange(
		Uuid\UuidInterface $connectorId,
		array $encryptedData,
	): array
	{
		if ($this->srp === null || !$this->expectedState->equalsValue(Types\TlvState::STATE_M5)) {
			$this->logger->error(
				'Unexpected pairing setup state. Expected is M5',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M6,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$decryptKey = hash_hkdf(
			'sha512',
			(string) $this->srp->getSessionKey(),
			32,
			self::INFO_ENCRYPT,
			self::SALT_ENCRYPT,
		);

		try {
			$decryptedData = sodium_crypto_aead_chacha20poly1305_ietf_decrypt(
				pack('C*', ...$encryptedData),
				'',
				pack('C*', ...self::NONCE_SETUP_M5),
				$decryptKey,
			);
		} catch (SodiumException $ex) {
			$this->logger->error(
				'Data could not be encrypted',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M6,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		if ($decryptedData === false) {
			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M6,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		try {
			$tlv = $this->tlv->decode($decryptedData);
		} catch (Exceptions\InvalidArgument) {
			$this->logger->error(
				'Unable to decode decrypted tlv data',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M6,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		if ($tlv === []) {
			$this->logger->error(
				'Data in decoded decrypted tlv data are missing',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M6,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$tlvEntry = array_pop($tlv);

		if (
			!array_key_exists(Types\TlvCode::CODE_IDENTIFIER, $tlvEntry)
			|| !is_string($tlvEntry[Types\TlvCode::CODE_IDENTIFIER])
			|| !array_key_exists(Types\TlvCode::CODE_PUBLIC_KEY, $tlvEntry)
			|| !is_array($tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY])
			|| !array_key_exists(Types\TlvCode::CODE_SIGNATURE, $tlvEntry)
			|| !is_array($tlvEntry[Types\TlvCode::CODE_SIGNATURE])
		) {
			$this->logger->error(
				'Data in decoded decrypted tlv data are invalid',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M6,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$iosDeviceX = hash_hkdf(
			'sha512',
			(string) $this->srp->getSessionKey(),
			32,
			self::INFO_CONTROLLER,
			self::SALT_CONTROLLER,
		);

		$iosDeviceInfo = $iosDeviceX
			. $tlvEntry[Types\TlvCode::CODE_IDENTIFIER]
			. pack('C*', ...$tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY]);

		if (
			!$this->edDsa->verify(
				unpack('C*', $iosDeviceInfo),
				$tlvEntry[Types\TlvCode::CODE_SIGNATURE],
				$tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY],
			)
		) {
			$this->logger->error(
				'iOS device info ed25519 signature verification is failed',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M6,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		/** @var mixed $connectorEntity */
		$connectorEntity = $this->databaseHelper->query(
			function () use ($connectorId): Entities\HomeKitConnector|null {
				$findConnectorQuery = new DevicesQueries\FindConnectors();
				$findConnectorQuery->byId($connectorId);

				$connector = $this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\HomeKitConnector::class,
				);
				assert($connector instanceof Entities\HomeKitConnector || $connector === null);

				return $connector;
			},
		);
		assert($connectorEntity instanceof Entities\HomeKitConnector || $connectorEntity === null);

		if ($connectorEntity === null) {
			throw new Exceptions\InvalidState('Connector entity could not be loaded from database');
		}

		$this->databaseHelper->transaction(
			function () use ($tlvEntry, $connectorEntity): void {
				$findClientQuery = new Queries\FindClients();
				$findClientQuery->forConnector($connectorEntity);
				$findClientQuery->byUid($tlvEntry[Types\TlvCode::CODE_IDENTIFIER]);

				$client = $this->clientsRepository->findOneBy($findClientQuery);

				if ($client !== null) {
					$this->clientsManager->update($client, ArrayHash::from([
						'publicKey' => pack('C*', ...$tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY]),
					]));
				} else {
					$this->clientsManager->create(ArrayHash::from([
						'uid' => $tlvEntry[Types\TlvCode::CODE_IDENTIFIER],
						'publicKey' => pack('C*', ...$tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY]),
						'connector' => $connectorEntity,
					]));
				}
			},
		);

		// M6 response generation

		$serverX = hash_hkdf(
			'sha512',
			(string) $this->srp->getSessionKey(),
			32,
			self::INFO_ACCESSORY,
			self::SALT_ACCESSORY,
		);

		$serverPrivateSecret = hex2bin(strval($this->connectorHelper->getConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_SERVER_SECRET),
		)));

		$serverPrivateKey = $this->edDsa->keyFromSecret(
			unpack('C*', (string) $serverPrivateSecret),
		);
		$serverPublicKey = $serverPrivateKey->getPublic();

		$macAddress = $this->connectorHelper->getConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_MAC_ADDRESS),
		);

		$serverInfo = $serverX . $macAddress . pack('C*', ...$serverPublicKey);
		$serverSignature = $serverPrivateKey->sign(unpack('C*', $serverInfo))->toBytes();

		$responseInnerData = [
			[
				Types\TlvCode::CODE_IDENTIFIER => strval($macAddress),
				Types\TlvCode::CODE_PUBLIC_KEY => $serverPublicKey,
				Types\TlvCode::CODE_SIGNATURE => $serverSignature,
			],
		];

		try {
			$responseEncryptedData = unpack('C*', sodium_crypto_aead_chacha20poly1305_ietf_encrypt(
				$this->tlv->encode($responseInnerData),
				'',
				pack('C*', ...self::NONCE_SETUP_M6),
				$decryptKey,
			));
		} catch (SodiumException $ex) {
			$this->logger->error(
				'Data could not be encrypted',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M6,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		if ($responseEncryptedData === false) {
			$this->logger->error(
				'Encrypted data could not be converted to bytes',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M6,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$this->activePairing = false;

		$this->logger->debug(
			'Pair finish exchange success',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'pairing-controller',
				'connector' => [
					'id' => $connectorId->toString(),
				],
				'pairing' => [
					'type' => 'exchange',
					'state' => Types\TlvState::STATE_M6,
				],
			],
		);

		$this->connectorHelper->setConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED),
			true,
		);

		return [
			[
				Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M6,
				Types\TlvCode::CODE_ENCRYPTED_DATA => $responseEncryptedData,
			],
		];
	}

	/**
	 * @param Array<int> $clientPublicKey
	 *
	 * @return Array<int, Array<int, (int|Array<int>|string)>>
	 *
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws DevicesExceptions\Runtime
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	private function verifyStart(
		Uuid\UuidInterface $connectorId,
		array $clientPublicKey,
	): array
	{
		$macAddress = $this->connectorHelper->getConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_MAC_ADDRESS),
		);

		$serverPrivateSecret = hex2bin(strval($this->connectorHelper->getConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_SERVER_SECRET),
		)));

		$serverPublicKey = publicKey($serverPrivateSecret);
		$sharedSecret = sharedKey($serverPrivateSecret, pack('C*', ...$clientPublicKey));

		$serverInfo = $serverPublicKey . $macAddress . pack('C*', ...$clientPublicKey);

		$serverPrivateKey = $this->edDsa->keyFromSecret(
			unpack('C*', (string) $serverPrivateSecret),
		);
		$serverSignature = $serverPrivateKey->sign(unpack('C*', $serverInfo))->toBytes();

		$responseInnerData = [
			[
				Types\TlvCode::CODE_IDENTIFIER => strval($macAddress),
				Types\TlvCode::CODE_SIGNATURE => $serverSignature,
			],
		];

		$encodeKey = hash_hkdf(
			'sha512',
			$sharedSecret,
			32,
			self::INFO_VERIFY,
			self::SALT_VERIFY,
		);

		try {
			$responseEncryptedData = unpack('C*', sodium_crypto_aead_chacha20poly1305_ietf_encrypt(
				$this->tlv->encode($responseInnerData),
				'',
				pack('C*', ...self::NONCE_VERIFY_M2),
				$encodeKey,
			));
		} catch (SodiumException $ex) {
			$this->logger->error(
				'Data could not be encrypted',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'verify-start',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		if ($responseEncryptedData === false) {
			$this->logger->error(
				'Encrypted data could not be converted to bytes',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'verify-start',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$serverPublicKey = unpack('C*', $serverPublicKey);

		if ($serverPublicKey === false) {
			$this->logger->error(
				'Accessory public key could not be converted to bytes',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'verify-start',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$this->connectorHelper->setConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_PUBLIC_KEY),
			bin2hex(pack('C*', ...$clientPublicKey)),
		);

		$this->connectorHelper->setConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_SHARED_KEY),
			bin2hex($sharedSecret),
		);

		$this->connectorHelper->setConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_HASHING_KEY),
			bin2hex($encodeKey),
		);

		$this->logger->debug(
			'Verify start success',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'pairing-controller',
				'connector' => [
					'id' => $connectorId->toString(),
				],
				'pairing' => [
					'type' => 'verify-start',
					'state' => Types\TlvState::STATE_M2,
				],
			],
		);

		return [
			[
				Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
				Types\TlvCode::CODE_PUBLIC_KEY => $serverPublicKey,
				Types\TlvCode::CODE_ENCRYPTED_DATA => $responseEncryptedData,
			],
		];
	}

	/**
	 * @param Array<int> $encryptedData
	 *
	 * @return Array<int, Array<int, (int|Array<int>|string)>>
	 *
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws DevicesExceptions\Runtime
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	private function verifyFinish(
		Uuid\UuidInterface $connectorId,
		array $encryptedData,
	): array
	{
		try {
			$decryptedData = sodium_crypto_aead_chacha20poly1305_ietf_decrypt(
				pack('C*', ...$encryptedData),
				'',
				pack('C*', ...self::NONCE_VERIFY_M3),
				(string) hex2bin(strval($this->connectorHelper->getConfiguration(
					$connectorId,
					Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_HASHING_KEY),
				))),
			);
		} catch (SodiumException $ex) {
			$this->logger->error(
				'Data could not be encrypted',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'verify-finish',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		if ($decryptedData === false) {
			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		try {
			$tlv = $this->tlv->decode($decryptedData);
		} catch (Exceptions\InvalidArgument) {
			$this->logger->error(
				'Unable to decode decrypted tlv data',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'verify-finish',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		if ($tlv === []) {
			$this->logger->error(
				'Data in decoded decrypted tlv data are missing',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'verify-finish',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		$tlvEntry = array_pop($tlv);

		if (
			!array_key_exists(Types\TlvCode::CODE_IDENTIFIER, $tlvEntry)
			|| !is_string($tlvEntry[Types\TlvCode::CODE_IDENTIFIER])
			|| !array_key_exists(Types\TlvCode::CODE_SIGNATURE, $tlvEntry)
			|| !is_array($tlvEntry[Types\TlvCode::CODE_SIGNATURE])
		) {
			$this->logger->error(
				'Data in decoded decrypted tlv data are invalid',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'verify-finish',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		$client = $this->databaseHelper->query(
			function () use ($tlvEntry, $connectorId) {
				$findClientQuery = new Queries\FindClients();
				$findClientQuery->byConnectorId($connectorId);
				$findClientQuery->byUid($tlvEntry[Types\TlvCode::CODE_IDENTIFIER]);

				return $this->clientsRepository->findOneBy($findClientQuery);
			},
		);

		if ($client === null) {
			$this->logger->error(
				'Pairing client instance is not created',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'verify-finish',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		$serverPrivateSecret = hex2bin(strval($this->connectorHelper->getConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_SERVER_SECRET),
		)));

		$iosDeviceInfo
			= hex2bin(strval($this->connectorHelper->getConfiguration(
				$connectorId,
				Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_PUBLIC_KEY),
			)))
			. $tlvEntry[Types\TlvCode::CODE_IDENTIFIER]
			. publicKey($serverPrivateSecret);

		if (
			!$this->edDsa->verify(
				array_values((array) unpack('C*', $iosDeviceInfo)),
				$tlvEntry[Types\TlvCode::CODE_SIGNATURE],
				array_values((array) unpack('C*', $client->getPublicKey())),
			)
		) {
			$this->logger->error(
				'iOS device info ed25519 signature verification is failed',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing' => [
						'type' => 'verify-finish',
						'state' => Types\TlvState::STATE_M2,
					],
				],
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		$this->logger->debug(
			'Verify finish success',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'pairing-controller',
				'connector' => [
					'id' => $connectorId->toString(),
				],
				'pairing' => [
					'type' => 'verify-start',
					'state' => Types\TlvState::STATE_M2,
				],
			],
		);

		return [
			[
				Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
			],
		];
	}

	/**
	 * @return Array<int, Array<int, (int|Array<int>|string)>>
	 */
	private function listPairings(Uuid\UuidInterface $connectorId): array
	{
		$this->logger->debug(
			'Requested list pairings',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'pairing-controller',
				'connector' => [
					'id' => $connectorId->toString(),
				],
			],
		);

		$result = [
			[
				Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
			],
		];

		try {
			/** @var DoctrineOrmQuery\ResultSet<Entities\Client> $clients */
			$clients = $this->databaseHelper->query(function () use ($connectorId): DoctrineOrmQuery\ResultSet {
				$findClientsQuery = new Queries\FindClients();
				$findClientsQuery->byConnectorId($connectorId);

				return $this->clientsRepository->getResultSet($findClientsQuery);
			});
		} catch (Throwable) {
			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		foreach ($clients as $client) {
			$result[] = [
				Types\TlvCode::CODE_IDENTIFIER => $client->getUid(),
				Types\TlvCode::CODE_PUBLIC_KEY => (array) unpack('C*', $client->getPublicKey()),
				Types\TlvCode::CODE_PERMISSIONS => $client->isAdmin() ?
					Types\ClientPermission::PERMISSION_ADMIN : Types\ClientPermission::PERMISSION_USER,
			];
		}

		return $result;
	}

	/**
	 * @param Array<int> $clientPublicKey
	 *
	 * @return Array<int, Array<int, int>>
	 */
	private function addPairing(
		Uuid\UuidInterface $connectorId,
		string $clientUid,
		array $clientPublicKey,
		int $clientPermission,
	): array
	{
		$this->logger->debug(
			'Requested add new pairing',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'pairing-controller',
				'connector' => [
					'id' => $connectorId->toString(),
				],
			],
		);

		try {
			$client = $this->databaseHelper->query(function () use ($connectorId, $clientUid): Entities\Client|null {
				$findClientQuery = new Queries\FindClients();
				$findClientQuery->byUid($clientUid);
				$findClientQuery->byConnectorId($connectorId);

				return $this->clientsRepository->findOneBy($findClientQuery);
			});
		} catch (Throwable) {
			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		if ($client !== null) {
			if ($client->getPublicKey() !== pack('C*', ...$clientPublicKey)) {
				$this->logger->error(
					'Received iOS device public key does not match with previously saved key',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
						'type' => 'pairing-controller',
						'connector' => [
							'id' => $connectorId->toString(),
						],
						'pairing' => [
							'type' => 'add-pairing',
							'state' => Types\TlvState::STATE_M2,
						],
					],
				);

				return [
					[
						Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
						Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
					],
				];
			} else {
				try {
					$this->databaseHelper->transaction(
						function () use ($client, $clientPermission): void {
							$this->clientsManager->update($client, ArrayHash::from([
								'admin' => $clientPermission === Types\ClientPermission::PERMISSION_ADMIN,
							]));
						},
					);
				} catch (Throwable) {
					return [
						[
							Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
							Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
						],
					];
				}
			}
		} else {
			try {
				$this->databaseHelper->transaction(
					function () use ($connectorId, $clientUid, $clientPublicKey, $clientPermission): void {
						$findConnectorQuery = new DevicesQueries\FindConnectors();
						$findConnectorQuery->byId($connectorId);

						$connector = $this->connectorsRepository->findOneBy(
							$findConnectorQuery,
							Entities\HomeKitConnector::class,
						);

						if ($connector === null) {
							throw new Exceptions\InvalidState('Connector entity could not be loaded from database');
						}

						$this->clientsManager->create(ArrayHash::from([
							'uid' => $clientUid,
							'publicKey' => pack('C*', ...$clientPublicKey),
							'admin' => $clientPermission === Types\ClientPermission::PERMISSION_ADMIN,
							'connector' => $connector,
						]));
					},
				);
			} catch (Throwable) {
				return [
					[
						Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
						Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
					],
				];
			}
		}

		return [
			[
				Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
			],
		];
	}

	/**
	 * @return Array<int, Array<int, int>>
	 */
	private function removePairing(
		Uuid\UuidInterface $connectorId,
		string $clientUid,
	): array
	{
		$this->logger->debug(
			'Requested remove pairing',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'pairing-controller',
				'connector' => [
					'id' => $connectorId->toString(),
				],
			],
		);

		try {
			$this->databaseHelper->transaction(function () use ($connectorId, $clientUid): void {
				$findClientQuery = new Queries\FindClients();
				$findClientQuery->byUid($clientUid);
				$findClientQuery->byConnectorId($connectorId);

				$client = $this->clientsRepository->findOneBy($findClientQuery);

				if ($client !== null) {
					$this->clientsManager->delete($client);
				}

				$findClientsQuery = new Queries\FindClients();
				$findClientsQuery->byConnectorId($connectorId);

				$clients = $this->clientsRepository->getResultSet($findClientsQuery);

				if ($clients->count() === 0) {
					$this->connectorHelper->setConfiguration(
						$connectorId,
						Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED),
						false,
					);

					$this->connectorHelper->setConfiguration(
						$connectorId,
						Types\ConnectorPropertyIdentifier::get(
							Types\ConnectorPropertyIdentifier::IDENTIFIER_CLIENT_PUBLIC_KEY,
						),
					);

					$this->connectorHelper->setConfiguration(
						$connectorId,
						Types\ConnectorPropertyIdentifier::get(
							Types\ConnectorPropertyIdentifier::IDENTIFIER_SHARED_KEY,
						),
					);

					$this->connectorHelper->setConfiguration(
						$connectorId,
						Types\ConnectorPropertyIdentifier::get(
							Types\ConnectorPropertyIdentifier::IDENTIFIER_HASHING_KEY,
						),
					);
				}
			});
		} catch (Throwable) {
			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		return [
			[
				Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
			],
		];
	}

}
