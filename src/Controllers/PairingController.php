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

namespace FastyBird\HomeKitConnector\Controllers;

use Brick\Math;
use Doctrine\DBAL;
use Elliptic\EdDSA;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\DevicesModule\Queries as DevicesModuleQueries;
use FastyBird\HomeKitConnector\Clients;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Helpers;
use FastyBird\HomeKitConnector\Models;
use FastyBird\HomeKitConnector\Protocol;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata;
use IPub\SlimRouter;
use Nette\Utils\ArrayHash;
use Psr\Http\Message;
use Ramsey\Uuid;
use SodiumException;
use function Curve25519\publicKey;
use function Curve25519\sharedKey;

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
	private const SALT_CONTROL = 'Control-Salt';
	private const INFO_CONTROL_WRITE = 'Control-Write-Encryption-Key';
	private const INFO_CONTROL_READ = 'Control-Read-Encryption-Key';

	private const NONCE_SETUP_M5 = [
		0,   // 0
		0,   // 0
		0,   // 0
		0,   // 0
		80,  // P
		83,  // S
		45,  // -
		77,  // M
		115, // s
		103, // g
		48,  // 0
		53,  // 5
	];
	private const NONCE_SETUP_M6 = [
		0,   // 0
		0,   // 0
		0,   // 0
		0,   // 0
		80,  // P
		83,  // S
		45,  // -
		77,  // M
		115, // s
		103, // g
		48,  // 0
		54,  // 6
	];
	private const NONCE_VERIFY_M2 = [
		0,   // 0
		0,   // 0
		0,   // 0
		0,   // 0
		80,  // P
		86,  // V
		45,  // -
		77,  // M
		115, // s
		103, // g
		48,  // 0
		50,  // 2
	];
	private const NONCE_VERIFY_M3 = [
		0,   // 0
		0,   // 0
		0,   // 0
		0,   // 0
		80,  // P
		86,  // V
		45,  // -
		77,  // M
		115, // s
		103, // g
		48,  // 0
		51,  // 3
	];

	/** @var bool */
	private bool $activePairing = false;

	/** @var int */
	private int $pairingAttempts = 0;

	/** @var Entities\Session|null */
	private ?Entities\Session $pairingSession = null;

	/** @var Helpers\Connector */
	private Helpers\Connector $connectorHelper;

	/** @var Helpers\Database */
	private Helpers\Database $databaseHelper;

	/** @var Protocol\Tlv */
	private Protocol\Tlv $tlv;

	/** @var Protocol\Srp|null */
	private ?Protocol\Srp $srp = null;

	/** @var EdDSA */
	private EdDSA $edDsa;

	/** @var Types\TlvState */
	private Types\TlvState $expectedState;

	/** @var Models\Sessions\SessionsManager */
	private Models\Sessions\SessionsManager $sessionsManager;

	/** @var DevicesModuleModels\Connectors\IConnectorsRepository */
	private DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository;

	/**
	 * @param Helpers\Connector $connectorHelper
	 * @param Helpers\Database $databaseHelper
	 * @param Protocol\Tlv $tlv
	 * @param Models\Sessions\SessionsManager $sessionsManager
	 * @param DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository
	 */
	public function __construct(
		Helpers\Connector $connectorHelper,
		Helpers\Database $databaseHelper,
		Protocol\Tlv $tlv,
		Models\Sessions\SessionsManager $sessionsManager,
		DevicesModuleModels\Connectors\IConnectorsRepository $connectorsRepository
	) {
		$this->connectorHelper = $connectorHelper;
		$this->databaseHelper = $databaseHelper;

		$this->tlv = $tlv;

		$this->sessionsManager = $sessionsManager;
		$this->connectorsRepository = $connectorsRepository;

		$this->edDsa = new EdDSA('ed25519');

		$this->expectedState = Types\TlvState::get(Types\TlvState::STATE_M1);
	}

	/**
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @return Message\ResponseInterface
	 *
	 * @throws DBAL\Exception
	 */
	public function setup(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response
	): Message\ResponseInterface {
		var_dump($request->getUri()->getPath());
		$connectorId = strval($request->getAttribute(Clients\Http::REQUEST_ATTRIBUTE_CONNECTOR));

		if (!Uuid\Uuid::isValid($connectorId)) {
			throw new Exceptions\InvalidState('Connector id could not be determined');
		}

		$connectorId = Uuid\Uuid::fromString($connectorId);

		$tlv = $this->tlv->decode($request->getBody()->getContents());

		if ($tlv === []) {
			throw new Exceptions\InvalidArgument('Provided TLV content is not valid');
		}

		$tlvEntry = array_pop($tlv);

		$requestedState = array_key_exists(Types\TlvCode::CODE_STATE, $tlvEntry) ? $tlvEntry[Types\TlvCode::CODE_STATE] : null;

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
				$tlvEntry[Types\TlvCode::CODE_PROOF]
			);

			$this->expectedState = Types\TlvState::get(Types\TlvState::STATE_M5);

		} elseif (
			$requestedState === Types\TlvState::STATE_M5
			&& array_key_exists(Types\TlvCode::CODE_ENCRYPTED_DATA, $tlvEntry)
			&& is_array($tlvEntry[Types\TlvCode::CODE_ENCRYPTED_DATA])
		) {
			$result = $this->exchange(
				$connectorId,
				$tlvEntry[Types\TlvCode::CODE_ENCRYPTED_DATA]
			);

			$this->expectedState = Types\TlvState::get(Types\TlvState::STATE_M1);

		} else {
			throw new Exceptions\InvalidState('Unknown data received');
		}

		if (array_key_exists(Types\TlvCode::CODE_ERROR, $result)) {
			$this->activePairing = false;
			$this->expectedState = Types\TlvState::get(Types\TlvState::STATE_M1);
		}

		$response = $response->withStatus(200);
		$response = $response->withHeader('Content-Type', Clients\Http::PAIRING_CONTENT_TYPE);
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString($this->tlv->encode($result)));

		return $response;
	}

	/**
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @return Message\ResponseInterface
	 *
	 * @throws DBAL\Exception
	 */
	public function verify(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response
	): Message\ResponseInterface {
		var_dump($request->getUri()->getPath());
		$connectorId = strval($request->getAttribute(Clients\Http::REQUEST_ATTRIBUTE_CONNECTOR));

		if (!Uuid\Uuid::isValid($connectorId)) {
			throw new Exceptions\InvalidState('Connector id could not be determined');
		}

		$connectorId = Uuid\Uuid::fromString($connectorId);

		$paired = $this->connectorHelper->getConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED)
		);

		if ((bool) $paired) {
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

			$requestedState = array_key_exists(Types\TlvCode::CODE_STATE, $tlvEntry) ? $tlvEntry[Types\TlvCode::CODE_STATE] : null;

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

		$response = $response->withStatus(200);
		$response = $response->withHeader('Content-Type', Clients\Http::PAIRING_CONTENT_TYPE);
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString($this->tlv->encode($result)));

		return $response;
	}

	/**
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @return Message\ResponseInterface
	 */
	public function prepare(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response
	): Message\ResponseInterface {
		var_dump($request->getUri()->getPath());
		var_dump($request->getBody()->getContents());
		// TODO: Implement
		return $response;
	}

	/**
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @return Message\ResponseInterface
	 */
	public function pairings(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response
	): Message\ResponseInterface {
		var_dump($request->getUri()->getPath());
		var_dump($request->getBody()->getContents());
		// TODO: Implement
		return $response;
	}

	/**
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @return Message\ResponseInterface
	 */
	public function identify(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response
	): Message\ResponseInterface {
		var_dump($request->getUri()->getPath());
		var_dump($request->getBody()->getContents());
		// TODO: Implement
		return $response;
	}

	/**
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @return Message\ResponseInterface
	 */
	public function resource(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response
	): Message\ResponseInterface {
		var_dump($request->getUri()->getPath());
		var_dump($request->getBody()->getContents());
		// TODO: Implement
		return $response;
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 *
	 * @return Array<int, Array<int, int|int[]|string>>
	 */
	private function srpStart(
		Uuid\UuidInterface $connectorId
	): array {
		$paired = $this->connectorHelper->getConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED)
		);

		if ((bool) $paired) {
			$this->logger->error(
				'Accessory already paired, cannot accept additional pairings',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'srp_start',
						'state' => Types\TlvState::STATE_M2,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'srp_start',
						'state' => Types\TlvState::STATE_M2,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'srp_start',
						'state' => Types\TlvState::STATE_M2,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'srp_start',
						'state' => Types\TlvState::STATE_M2,
					],
				]
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
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_PIN_CODE)
		);

		$this->srp = new Protocol\Srp(self::SRP_USERNAME, strval($pinCode));

		$serverPublicKey = unpack('C*', $this->srp->getServerPublicKey()->toBytes(false));

		if ($serverPublicKey === false) {
			$this->logger->error(
				'Server public key could not be converted to bytes',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'srp_start',
						'state' => Types\TlvState::STATE_M2,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'srp_start',
						'state' => Types\TlvState::STATE_M2,
					],
				]
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		return [
			[
				Types\TlvCode::CODE_STATE      => Types\TlvState::STATE_M2,
				Types\TlvCode::CODE_PUBLIC_KEY => $serverPublicKey,
				Types\TlvCode::CODE_SALT       => $salt,
			],
		];
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param int[] $clientPublicKey
	 * @param int[] $clientProof
	 *
	 * @return Array<int, Array<int, int|int[]|string>>
	 */
	private function srpFinish(
		Uuid\UuidInterface $connectorId,
		array $clientPublicKey,
		array $clientProof
	): array {
		if ($this->srp === null || !$this->expectedState->equalsValue(Types\TlvState::STATE_M3)) {
			$this->logger->error(
				'Unexpected pairing setup state. Expected is M3',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'srp_finish',
						'state' => Types\TlvState::STATE_M4,
					],
				]
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$this->srp->computeSharedSessionKey(
			Math\BigInteger::fromBytes(pack('C*', ...$clientPublicKey), false)
		);

		if (!$this->srp->verifyProof(pack('C*', ...$clientProof))) {
			$this->logger->error(
				'Incorrect pin code, try again',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'srp_finish',
						'state' => Types\TlvState::STATE_M4,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'srp_finish',
						'state' => Types\TlvState::STATE_M4,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'srp_finish',
						'state' => Types\TlvState::STATE_M4,
					],
				]
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		return [
			[
				Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
				Types\TlvCode::CODE_PROOF => $serverProof,
			],
		];
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param int[] $encryptedData
	 *
	 * @return Array<int, Array<int, int|int[]|string>>
	 *
	 * @throws DBAL\Exception
	 */
	public function exchange(
		Uuid\UuidInterface $connectorId,
		array $encryptedData
	): array {
		if ($this->srp === null || !$this->expectedState->equalsValue(Types\TlvState::STATE_M5)) {
			$this->logger->error(
				'Unexpected pairing setup state. Expected is M5',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				]
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
			self::SALT_ENCRYPT
		);

		try {
			$decryptedData = sodium_crypto_aead_chacha20poly1305_ietf_decrypt(
				pack('C*', ...$encryptedData),
				'',
				pack('C*', ...self::NONCE_SETUP_M5),
				$decryptKey
			);
		} catch (SodiumException $ex) {
			$this->logger->error(
				'Data could not be encrypted',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				]
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
			self::SALT_CONTROLLER
		);

		$iosDeviceInfo = $iosDeviceX
			. $tlvEntry[Types\TlvCode::CODE_IDENTIFIER]
			. pack('C*', ...$tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY]);

		if (
			!$this->edDsa->verify(
				unpack('C*', $iosDeviceInfo),
				$tlvEntry[Types\TlvCode::CODE_SIGNATURE],
				$tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY]
			)
		) {
			$this->logger->error(
				'iOS device info ed25519 signature verification is failed',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				]
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M6,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		/** @var Entities\HomeKitConnector|null $connectorEntity */
		$connectorEntity = $this->databaseHelper->query(
			function () use ($connectorId): ?Entities\HomeKitConnector {
				$findConnectorQuery = new DevicesModuleQueries\FindConnectorsQuery();
				$findConnectorQuery->byId($connectorId);

				/** @var Entities\HomeKitConnector|null $connector */
				$connector = $this->connectorsRepository->findOneBy(
					$findConnectorQuery,
					Entities\HomeKitConnector::class
				);

				return $connector;
			}
		);

		if ($connectorEntity === null) {
			throw new Exceptions\InvalidState('Connector entity could not be loaded from database');
		}

		$this->pairingSession = $this->databaseHelper->transaction(
			function () use ($tlvEntry, $connectorEntity): Entities\Session {
				return $this->sessionsManager->create(ArrayHash::from([
					'clientUid'  => $tlvEntry[Types\TlvCode::CODE_IDENTIFIER],
					'clientLtpk' => pack('C*', ...$tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY]),
					'connector'  => $connectorEntity,
				]));
			}
		);

		// M6 response generation

		$serverX = hash_hkdf(
			'sha512',
			(string) $this->srp->getSessionKey(),
			32,
			self::INFO_ACCESSORY,
			self::SALT_ACCESSORY
		);

		$serverPrivateKey = $this->edDsa->keyFromSecret(
			unpack('C*', $this->pairingSession->getServerPrivateKey())
		);
		$serverPublicKey = $serverPrivateKey->getPublic();

		$macAddress = $this->connectorHelper->getConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_MAC_ADDRESS)
		);

		$serverInfo = $serverX . $macAddress . pack('C*', ...$serverPublicKey);
		$serverSignature = $serverPrivateKey->sign(unpack('C*', $serverInfo))->toBytes();

		$responseInnerData = [
			[
				Types\TlvCode::CODE_IDENTIFIER => strval($macAddress),
				Types\TlvCode::CODE_PUBLIC_KEY => $serverPublicKey,
				Types\TlvCode::CODE_SIGNATURE  => $serverSignature,
			],
		];

		try {
			$responseEncryptedData = unpack('C*', sodium_crypto_aead_chacha20poly1305_ietf_encrypt(
				$this->tlv->encode($responseInnerData),
				'',
				pack('C*', ...self::NONCE_SETUP_M6),
				$decryptKey
			));
		} catch (SodiumException $ex) {
			$this->logger->error(
				'Data could not be encrypted',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'exchange',
						'state' => Types\TlvState::STATE_M6,
					],
				]
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M6,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$this->activePairing = false;

		return [
			[
				Types\TlvCode::CODE_STATE          => Types\TlvState::STATE_M6,
				Types\TlvCode::CODE_ENCRYPTED_DATA => $responseEncryptedData,
			],
		];
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param int[] $clientPublicKey
	 *
	 * @return Array<int, Array<int, int|int[]|string>>
	 *
	 * @throws DBAL\Exception
	 */
	private function verifyStart(
		Uuid\UuidInterface $connectorId,
		array $clientPublicKey
	): array {
		if ($this->pairingSession === null) {
			$this->logger->error(
				'Pairing session instance is not created',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'verify_start',
						'state' => Types\TlvState::STATE_M2,
					],
				]
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$macAddress = $this->connectorHelper->getConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_MAC_ADDRESS)
		);

		$serverPublicKey = publicKey($this->pairingSession->getServerPrivateKey());
		$sharedSecret = sharedKey($this->pairingSession->getServerPrivateKey(), pack('C*', ...$clientPublicKey));

		$serverInfo = $serverPublicKey . $macAddress . pack('C*', ...$clientPublicKey);

		$serverPrivateKey = $this->edDsa->keyFromSecret(
			unpack('C*', $this->pairingSession->getServerPrivateKey())
		);
		$serverSignature = $serverPrivateKey->sign(unpack('C*', $serverInfo))->toBytes();

		$responseInnerData = [
			[
				Types\TlvCode::CODE_IDENTIFIER => strval($macAddress),
				Types\TlvCode::CODE_SIGNATURE  => $serverSignature,
			],
		];

		$encodeKey = hash_hkdf(
			'sha512',
			$sharedSecret,
			32,
			self::INFO_VERIFY,
			self::SALT_VERIFY
		);

		try {
			$responseEncryptedData = unpack('C*', sodium_crypto_aead_chacha20poly1305_ietf_encrypt(
				$this->tlv->encode($responseInnerData),
				'',
				pack('C*', ...self::NONCE_VERIFY_M2),
				$encodeKey
			));
		} catch (SodiumException $ex) {
			$this->logger->error(
				'Data could not be encrypted',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'verify_start',
						'state' => Types\TlvState::STATE_M2,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'verify_start',
						'state' => Types\TlvState::STATE_M2,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'verify_start',
						'state' => Types\TlvState::STATE_M2,
					],
				]
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M2,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_UNKNOWN,
				],
			];
		}

		$pairingSession = $this->pairingSession;

		$this->databaseHelper->transaction(
			function () use (
				$pairingSession,
				$clientPublicKey,
				$sharedSecret,
				$encodeKey
			): Entities\Session {
				return $this->sessionsManager->update(
					$pairingSession,
					ArrayHash::from([
						'clientPublicKey' => pack('C*', ...$clientPublicKey),
						'sharedKey'       => $sharedSecret,
						'hashingKey'      => $encodeKey,
					])
				);
			}
		);

		return [
			[
				Types\TlvCode::CODE_STATE          => Types\TlvState::STATE_M2,
				Types\TlvCode::CODE_PUBLIC_KEY     => $serverPublicKey,
				Types\TlvCode::CODE_ENCRYPTED_DATA => $responseEncryptedData,
			],
		];
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param int[] $encryptedData
	 *
	 * @return Array<int, Array<int, int|int[]|string>>
	 *
	 * @throws DBAL\Exception
	 */
	private function verifyFinish(
		Uuid\UuidInterface $connectorId,
		array $encryptedData
	): array {
		if ($this->pairingSession === null) {
			$this->logger->error(
				'Pairing session instance is not created',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'verify_finish',
						'state' => Types\TlvState::STATE_M2,
					],
				]
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		try {
			$decryptedData = sodium_crypto_aead_chacha20poly1305_ietf_decrypt(
				pack('C*', ...$encryptedData),
				'',
				pack('C*', ...self::NONCE_VERIFY_M3),
				$this->pairingSession->getHashingKey() ?? ''
			);
		} catch (SodiumException $ex) {
			$this->logger->error(
				'Data could not be encrypted',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'verify_finish',
						'state' => Types\TlvState::STATE_M2,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'verify_finish',
						'state' => Types\TlvState::STATE_M2,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'verify_finish',
						'state' => Types\TlvState::STATE_M2,
					],
				]
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
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'verify_finish',
						'state' => Types\TlvState::STATE_M2,
					],
				]
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		$iosDeviceInfo = $this->pairingSession->getClientPublicKey()
			. $tlvEntry[Types\TlvCode::CODE_IDENTIFIER]
			. publicKey($this->pairingSession->getServerPrivateKey());

		if (
			!$this->edDsa->verify(
				array_values((array) unpack('C*', $iosDeviceInfo)),
				$tlvEntry[Types\TlvCode::CODE_SIGNATURE],
				array_values((array) unpack('C*', $this->pairingSession->getClientLtpk()))
			)
		) {
			$this->logger->error(
				'iOS device info ed25519 signature verification is failed',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId->toString(),
					],
					'pairing'   => [
						'type'  => 'verify_finish',
						'state' => Types\TlvState::STATE_M2,
					],
				]
			);

			return [
				[
					Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
					Types\TlvCode::CODE_ERROR => Types\TlvError::ERROR_AUTHENTICATION,
				],
			];
		}

		$decryptKey = hash_hkdf(
			'sha512',
			$this->pairingSession->getSharedKey() ?? '',
			32,
			self::INFO_CONTROL_WRITE,
			self::SALT_CONTROL
		);

		$encryptKey = hash_hkdf(
			'sha512',
			$this->pairingSession->getSharedKey() ?? '',
			32,
			self::INFO_CONTROL_READ,
			self::SALT_CONTROL
		);

		$pairingSession = $this->pairingSession;

		$this->databaseHelper->transaction(
			function () use ($pairingSession, $decryptKey, $encryptKey): Entities\Session {
				return $this->sessionsManager->update(
					$pairingSession,
					ArrayHash::from([
						'decryptKey' => $decryptKey,
						'encryptKey' => $encryptKey,
					])
				);
			}
		);

		$this->connectorHelper->setConfiguration(
			$connectorId,
			Types\ConnectorPropertyIdentifier::get(Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED),
			true
		);

		$this->pairingSession = null;

		return [
			[
				Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
			],
		];
	}

}
