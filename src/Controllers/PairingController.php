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
use FastyBird\HomeKitConnector\Clients;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Helpers;
use FastyBird\HomeKitConnector\Protocol;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata;
use IPub\SlimRouter;
use Psr\Http\Message;
use Ramsey\Uuid;

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

	/** @var bool */
	private bool $activePairing = false;

	/** @var int */
	private int $pairingAttempts = 0;

	/** @var Helpers\Connector */
	private Helpers\Connector $connectorHelper;

	/** @var Protocol\Tlv */
	private Protocol\Tlv $tlv;

	/** @var Protocol\Srp|null */
	private ?Protocol\Srp $srp = null;

	/** @var Types\TlvState */
	private Types\TlvState $expectedState;

	/**
	 * @param Helpers\Connector $connectorHelper
	 * @param Protocol\Tlv $tlv
	 */
	public function __construct(
		Helpers\Connector $connectorHelper,
		Protocol\Tlv $tlv
	) {
		$this->connectorHelper = $connectorHelper;
		$this->tlv = $tlv;

		$this->expectedState = Types\TlvState::get(Types\TlvState::STATE_M1);
	}

	/**
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @return Message\ResponseInterface
	 */
	public function setup(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response
	): Message\ResponseInterface {
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

		} elseif (
			$requestedState === Types\TlvState::STATE_M3
			&& array_key_exists(Types\TlvCode::CODE_PUBLIC_KEY, $tlvEntry)
			&& is_array($tlvEntry[Types\TlvCode::CODE_PUBLIC_KEY])
			&& array_key_exists(Types\TlvCode::CODE_PROOF, $tlvEntry)
			&& is_array($tlvEntry[Types\TlvCode::CODE_PROOF])
		) {
			$result = $this->srpVerify(
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
			$result = $this->srpExchange(
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
	public function verify(
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
	 * @return Array<int, Array<int, int|string>>
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
						'id' => $connectorId,
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
						'id' => $connectorId,
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
				'Unexpected pair_setup state',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId,
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
						'id' => $connectorId,
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

		return [
			[
				Types\TlvCode::CODE_STATE      => Types\TlvState::STATE_M2,
				Types\TlvCode::CODE_PUBLIC_KEY => $this->srp->getServerPublicKey()->toBytes(false),
				Types\TlvCode::CODE_SALT       => $this->srp->getSalt(),
			],
		];
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param int[] $clientPublicKey
	 * @param int[] $clientProof
	 *
	 * @return Array<int, Array<int, int|string>>
	 */
	private function srpVerify(
		Uuid\UuidInterface $connectorId,
		array $clientPublicKey,
		array $clientProof
	): array {
		if ($this->srp === null || !$this->expectedState->equalsValue(Types\TlvState::STATE_M3)) {
			$this->logger->error(
				'Unexpected pairing setup state',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId,
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

		$this->srp->computeSharedSessionKey(Math\BigInteger::fromBytes(pack('C*', ...$clientPublicKey)));

		if (!$this->srp->verifyProof(pack('C*', ...$clientProof))) {
			$this->logger->error(
				'Incorrect setup code, try again',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId,
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

		return [
			[
				Types\TlvCode::CODE_STATE => Types\TlvState::STATE_M4,
				Types\TlvCode::CODE_PROOF => $this->srp->getServerProofOfSessionKey() ?? '',
			],
		];
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param int[] $encryptedData
	 *
	 * @return Array<int, Array<int, int|int[]|string>>
	 */
	public function srpExchange(
		Uuid\UuidInterface $connectorId,
		array $encryptedData
	): array {
		if ($this->srp === null || !$this->expectedState->equalsValue(Types\TlvState::STATE_M5)) {
			$this->logger->error(
				'Unexpected pairing setup state',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'pairing-controller',
					'connector' => [
						'id' => $connectorId,
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

		return [
			[
				Types\TlvCode::CODE_STATE          => Types\TlvState::STATE_M6,
				Types\TlvCode::CODE_ENCRYPTED_DATA => $encryptedData,
			],
		];
	}

}
