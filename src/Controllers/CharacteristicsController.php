<?php declare(strict_types = 1);

/**
 * PairingController.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Controllers
 * @since          1.0.0
 *
 * @date           19.09.22
 */

namespace FastyBird\Connector\HomeKit\Controllers;

use FastyBird\Connector\HomeKit\Clients;
use FastyBird\Connector\HomeKit\Constants;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Events;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Queue;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use IPub\SlimRouter;
use Nette\Utils;
use Psr\EventDispatcher;
use Psr\Http\Message;
use Ramsey\Uuid;
use RuntimeException;
use function array_key_exists;
use function array_merge;
use function explode;
use function in_array;
use function intval;
use function is_array;
use function strval;

/**
 * Accessories services characteristics controller
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Controllers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class CharacteristicsController extends BaseController
{

	/** @var array<string, array<int, int>> */
	private array $preparedWrites = [];

	public function __construct(
		private readonly Helpers\Entity $entityHelper,
		private readonly Queue\Queue $queue,
		private readonly Protocol\Driver $accessoryDriver,
		private readonly Clients\Subscriber $subscriber,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly EventDispatcher\EventDispatcherInterface|null $dispatcher = null,
	)
	{
	}

	/**
	 * @throws Exceptions\HapRequestError
	 * @throws Exceptions\InvalidState
	 * @throws InvalidArgumentException
	 * @throws MetadataExceptions\InvalidState
	 * @throws Utils\JsonException
	 */
	public function index(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		$this->logger->debug(
			'Requested list of characteristics of selected accessories',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'characteristics-controller',
				'request' => [
					'address' => $request->getServerParams()['REMOTE_ADDR'],
					'path' => $request->getUri()->getPath(),
					'query' => $request->getQueryParams(),
				],
			],
		);

		$connectorId = strval($request->getAttribute(Servers\Http::REQUEST_ATTRIBUTE_CONNECTOR));

		if (!Uuid\Uuid::isValid($connectorId)) {
			throw new Exceptions\InvalidState('Connector id could not be determined');
		}

		$connectorId = Uuid\Uuid::fromString($connectorId);

		$queryParams = $request->getQueryParams();

		if (!array_key_exists('id', $queryParams)) {
			throw new Exceptions\HapRequestError(
				$request,
				Types\ServerStatus::get(Types\ServerStatus::INVALID_VALUE_IN_REQUEST),
				'Request query does not have required parameters',
				StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
			);
		}

		$meta
			= array_key_exists(Types\Representation::META, $queryParams)
			&& (int) $queryParams[Types\Representation::META] === 1;
		$perms
			= array_key_exists(Types\Representation::PERM, $queryParams)
			&& (int) $queryParams[Types\Representation::PERM] === 1;
		$type
			= array_key_exists(Types\Representation::TYPE, $queryParams)
			&& (int) $queryParams[Types\Representation::TYPE] === 1;
		$ev
			= array_key_exists(
				Types\CharacteristicPermission::NOTIFY,
				$queryParams,
			)
			&& (int) $queryParams[Types\CharacteristicPermission::NOTIFY] === 1;

		$ids = explode(',', $queryParams['id']);

		$result = [
			Types\Representation::CHARS => [],
		];

		foreach ($ids as $id) {
			[$aid, $iid] = explode('.', $id) + [null, null];

			if ($aid === null || $iid === null) {
				throw new Exceptions\HapRequestError(
					$request,
					Types\ServerStatus::get(Types\ServerStatus::INVALID_VALUE_IN_REQUEST),
					'Request query has invalid format pro ID parameter',
					StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
				);
			}

			$aid = (int) $aid;
			$iid = (int) $iid;

			$result[Types\Representation::CHARS][] = $this->readCharacteristic(
				$connectorId,
				$aid,
				$iid,
				$meta,
				$perms,
				$type,
				$ev,
			);
		}

		$anyError = false;

		foreach ($result[Types\Representation::CHARS] as $charResult) {
			if (
				array_key_exists(Types\Representation::STATUS, $charResult)
				&& $charResult[Types\Representation::STATUS] !== Types\ServerStatus::SUCCESS
			) {
				$anyError = true;
			}
		}

		if ($anyError) {
			foreach ($result[Types\Representation::CHARS] as $key => $charResult) {
				if (!array_key_exists(Types\Representation::STATUS, $charResult)) {
					$result[Types\Representation::CHARS][$key][Types\Representation::STATUS] = Types\ServerStatus::SUCCESS;
				}
			}
		}

		$response = $response->withStatus(
			$anyError ? StatusCodeInterface::STATUS_MULTI_STATUS : StatusCodeInterface::STATUS_OK,
		);
		$response = $response->withHeader('Content-Type', Servers\Http::JSON_CONTENT_TYPE);
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode($result)));

		return $response;
	}

	/**
	 * @throws Exceptions\HapRequestError
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 * @throws Utils\JsonException
	 */
	public function update(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		$this->logger->debug(
			'Requested updating of characteristics of selected accessories',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'characteristics-controller',
				'request' => [
					'address' => $request->getServerParams()['REMOTE_ADDR'],
					'path' => $request->getUri()->getPath(),
					'query' => $request->getQueryParams(),
					'body' => $request->getBody()->getContents(),
				],
			],
		);

		$connectorId = strval($request->getAttribute(Servers\Http::REQUEST_ATTRIBUTE_CONNECTOR));

		if (!Uuid\Uuid::isValid($connectorId)) {
			throw new Exceptions\InvalidState('Connector id could not be determined');
		}

		$connectorId = Uuid\Uuid::fromString($connectorId);

		$request->getBody()->rewind();

		$body = $request->getBody()->getContents();

		try {
			$body = Utils\Json::decode($body, Utils\Json::FORCE_ARRAY);
		} catch (Utils\JsonException $ex) {
			throw new Exceptions\HapRequestError(
				$request,
				Types\ServerStatus::get(Types\ServerStatus::INVALID_VALUE_IN_REQUEST),
				'Request body could not be decoded',
				StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
				$ex,
			);
		}

		if (
			!is_array($body)
			|| !array_key_exists(Types\Representation::CHARS, $body)
			|| !is_array($body[Types\Representation::CHARS])
		) {
			throw new Exceptions\HapRequestError(
				$request,
				Types\ServerStatus::get(Types\ServerStatus::INVALID_VALUE_IN_REQUEST),
				'Request body does not have required attributes',
				StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
			);
		}

		$pid = array_key_exists(Types\Representation::PID, $body)
			? (int) $body[Types\Representation::PID]
			: null;

		$timedWriteError = false;

		if ($pid !== null) {
			if (
				!array_key_exists(strval($request->getServerParams()['REMOTE_ADDR']), $this->preparedWrites)
				|| !array_key_exists($pid, $this->preparedWrites[strval($request->getServerParams()['REMOTE_ADDR'])])
				|| $this->preparedWrites[strval(
					$request->getServerParams()['REMOTE_ADDR'],
				)][$pid] < $this->dateTimeFactory->getNow()->getTimestamp()
			) {
				$timedWriteError = true;
			}

			if (
				array_key_exists(strval($request->getServerParams()['REMOTE_ADDR']), $this->preparedWrites)
				&& array_key_exists($pid, $this->preparedWrites[strval($request->getServerParams()['REMOTE_ADDR'])])
			) {
				unset($this->preparedWrites[strval($request->getServerParams()['REMOTE_ADDR'])][$pid]);
			}
		}

		$result = [
			Types\Representation::CHARS => [],
		];

		foreach ($body[Types\Representation::CHARS] as $setCharacteristic) {
			if (
				is_array($setCharacteristic)
				&& array_key_exists(Types\Representation::AID, $setCharacteristic)
				&& array_key_exists(Types\Representation::IID, $setCharacteristic)
			) {
				$aid = intval($setCharacteristic[Types\Representation::AID]);
				$iid = intval($setCharacteristic[Types\Representation::IID]);

				$value = array_key_exists(
					Types\Representation::VALUE,
					$setCharacteristic,
				)
					? $setCharacteristic[Types\Representation::VALUE]
					: null;
				$events = array_key_exists(
					Types\CharacteristicPermission::NOTIFY,
					$setCharacteristic,
				)
					? (bool) $setCharacteristic[Types\CharacteristicPermission::NOTIFY]
					: null;
				$includeValue = array_key_exists('r', $setCharacteristic)
					? (bool) $setCharacteristic[Types\CharacteristicPermission::NOTIFY]
					: null;

				$result[Types\Representation::CHARS][] = $this->writeCharacteristic(
					$connectorId,
					$aid,
					$iid,
					$value,
					$events,
					$includeValue,
					strval($request->getServerParams()['REMOTE_ADDR']),
					$pid,
					$timedWriteError,
				);

			} else {
				throw new Exceptions\HapRequestError(
					$request,
					Types\ServerStatus::get(Types\ServerStatus::INVALID_VALUE_IN_REQUEST),
					'Request body does not have required attributes',
					StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
				);
			}
		}

		$anyError = false;

		foreach ($result[Types\Representation::CHARS] as $charResult) {
			if (
				array_key_exists(Types\Representation::STATUS, $charResult)
				&& $charResult[Types\Representation::STATUS] !== Types\ServerStatus::SUCCESS
			) {
				$anyError = true;
			}
		}

		$response = $response->withStatus(
			$anyError ? StatusCodeInterface::STATUS_MULTI_STATUS : StatusCodeInterface::STATUS_NO_CONTENT,
		);

		if ($anyError) {
			$response = $response->withHeader('Content-Type', Servers\Http::JSON_CONTENT_TYPE);
			$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode($result)));
		}

		return $response;
	}

	/**
	 * Handles a client request to prepare to write
	 *
	 * @throws Exceptions\HapRequestError
	 * @throws InvalidArgumentException
	 * @throws RuntimeException
	 * @throws Utils\JsonException
	 */
	public function prepare(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		$body = $request->getBody()->getContents();

		try {
			$body = Utils\Json::decode($body, Utils\Json::FORCE_ARRAY);
		} catch (Utils\JsonException $ex) {
			throw new Exceptions\HapRequestError(
				$request,
				Types\ServerStatus::get(Types\ServerStatus::INVALID_VALUE_IN_REQUEST),
				'Request body could not be decoded',
				StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
				$ex,
			);
		}

		if (
			!is_array($body)
			|| !array_key_exists(Types\Representation::TTL, $body)
			|| !array_key_exists(Types\Representation::PID, $body)
		) {
			throw new Exceptions\HapRequestError(
				$request,
				Types\ServerStatus::get(Types\ServerStatus::INVALID_VALUE_IN_REQUEST),
				'Request body does not have required attributes',
				StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
			);
		}

		$clientAddress = strval($request->getServerParams()['REMOTE_ADDR']);

		if (!array_key_exists($clientAddress, $this->preparedWrites)) {
			$this->preparedWrites[$clientAddress] = [];
		}

		$this->preparedWrites[$clientAddress][intval($body[Types\Representation::PID])]
			= intval($this->dateTimeFactory->getNow()->getTimestamp())
			+ (intval($body[Types\Representation::TTL]) / 1_000);

		$result = [
			Types\Representation::STATUS => Types\ServerStatus::SUCCESS,
		];

		$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', Servers\Http::JSON_CONTENT_TYPE);
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode($result)));

		return $response;
	}

	/**
	 * @return array<string, (bool|int|array<int>|float|string|array<string>|null)>
	 *
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function readCharacteristic(
		Uuid\UuidInterface $connectorId,
		int $aid,
		int $iid,
		bool $meta,
		bool $perms,
		bool $type,
		bool $ev,
	): array
	{
		$representation = $this->getCharacteristicRepresentationSkeleton($aid, $iid);

		$characteristic = $this->getCharacteristic($connectorId, $aid, $iid);

		if ($characteristic === null) {
			return $representation;
		}

		if (!in_array(Types\CharacteristicPermission::READ, $characteristic->getPermissions(), true)) {
			$representation[Types\Representation::STATUS] = Types\ServerStatus::WRITE_ONLY_CHARACTERISTIC;

			return $representation;
		}

		$representation[Types\Representation::STATUS] = Types\ServerStatus::SUCCESS;
		$representation[Types\Representation::VALUE] = Protocol\Transformer::toClient(
			$characteristic->getProperty(),
			$characteristic->getDataType(),
			$characteristic->getValidValues(),
			$characteristic->getMaxLength(),
			$characteristic->getMinValue(),
			$characteristic->getMaxValue(),
			$characteristic->getMinStep(),
			$characteristic->getValue(),
		);

		if ($perms) {
			$representation[Types\Representation::PERM] = $characteristic->getPermissions();
		}

		if ($type) {
			$representation[Types\Representation::PERM] = Helpers\Protocol::uuidToHapType(
				$characteristic->getTypeId(),
			);
		}

		if ($meta) {
			$representation = array_merge($representation, $characteristic->getMeta());
		}

		if ($ev) {
			$representation[Types\CharacteristicPermission::NOTIFY] = in_array(
				Types\CharacteristicPermission::NOTIFY,
				$characteristic->getPermissions(),
				true,
			);
		}

		if (
			array_key_exists(Types\Representation::STATUS, $representation)
			&& $representation[Types\Representation::STATUS] === Types\ServerStatus::SUCCESS
		) {
			unset($representation[Types\Representation::STATUS]);
		}

		return $representation;
	}

	/**
	 * @return array<string, bool|float|int|string|null>
	 *
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function writeCharacteristic(
		Uuid\UuidInterface $connectorId,
		int $aid,
		int $iid,
		int|float|string|bool|null $valueToWrite,
		bool|null $events,
		bool|null $includeValue,
		string $clientAddress,
		int|null $pid,
		bool $timedWriteError,
	): array
	{
		$representation = $this->getCharacteristicRepresentationSkeleton($aid, $iid);

		$characteristic = $this->getCharacteristic($connectorId, $aid, $iid);

		if ($characteristic === null) {
			return $representation;
		}

		if (
			$valueToWrite !== null
			&& !in_array(
				Types\CharacteristicPermission::WRITE,
				$characteristic->getPermissions(),
				true,
			)
			&& !in_array(
				Types\CharacteristicPermission::TIMED_WRITE,
				$characteristic->getPermissions(),
				true,
			)
			&& !in_array(
				Types\CharacteristicPermission::WRITE_RESPONSE,
				$characteristic->getPermissions(),
				true,
			)
		) {
			$representation[Types\Representation::STATUS] = Types\ServerStatus::READ_ONLY_CHARACTERISTIC;

			return $representation;
		}

		if (
			$pid !== null
			&& !in_array(
				Types\CharacteristicPermission::TIMED_WRITE,
				$characteristic->getPermissions(),
				true,
			)
		) {
			return $representation;
		}

		if (
			$pid === null
			&& in_array(Types\CharacteristicPermission::TIMED_WRITE, $characteristic->getPermissions(), true)
		) {
			$representation[Types\Representation::STATUS] = Types\ServerStatus::INVALID_VALUE_IN_REQUEST;

			return $representation;
		}

		if ($timedWriteError) {
			$representation[Types\Representation::STATUS] = Types\ServerStatus::INVALID_VALUE_IN_REQUEST;

			return $representation;
		}

		if ($valueToWrite !== null) {
			$this->dispatcher?->dispatch(new Events\ClientWriteCharacteristic($characteristic, $valueToWrite));

			if ($characteristic->getProperty() === null) {
				$this->logger->warning(
					'Accessory characteristic is not connected to any property',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
						'type' => 'characteristics-controller',
						'characteristic' => [
							'type' => $characteristic->getTypeId()->toString(),
							'name' => $characteristic->getName(),
						],
					],
				);
			} else {
				$value = Protocol\Transformer::fromClient(
					$characteristic->getProperty(),
					$characteristic->getDataType(),
					$valueToWrite,
				);

				if (!$characteristic->isAlwaysNull()) {
					$characteristic->setExpectedValue($value);
				}

				$this->logger->info(
					'Apple client requested to set expected value to characteristic',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
						'type' => 'characteristics-controller',
						'characteristic' => [
							'type' => $characteristic->getTypeId()->toString(),
							'name' => $characteristic->getName(),
						],
						'value' => [
							'expected' => $valueToWrite,
							'transformed' => $value,
						],
						'device' => [
							'id' => $characteristic->getService()->getChannel()?->getDevice()->toString(),
						],
						'channel' => [
							'id' => $characteristic->getService()->getChannel()?->getId()->toString(),
						],
						'property' => [
							'id' => $characteristic->getProperty()?->getId()->toString(),
						],
					],
				);

				foreach ($characteristic->getService()->getCharacteristics() as $row) {
					if (
						$row->getProperty() instanceof MetadataDocuments\DevicesModule\ChannelDynamicProperty
						|| $row->getProperty() instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty
						|| $row->getProperty() instanceof MetadataDocuments\DevicesModule\ChannelMappedProperty
					) {
						$this->queue->append(
							$this->entityHelper->create(
								Entities\Messages\StoreChannelPropertyState::class,
								[
									'connector' => $connectorId,
									'device' => $row->getService()->getChannel()?->getDevice(),
									'channel' => $row->getService()->getChannel()?->getId(),
									'property' => $row->getProperty()->getId(),
									'value' => $row->getValue(),
								],
							),
						);
					}
				}

				$this->logger->debug(
					'Apple client requested to set expected value to device channel property',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
						'type' => 'characteristics-controller',
						'characteristic' => [
							'type' => $characteristic->getTypeId()->toString(),
							'name' => $characteristic->getName(),
						],
						'device' => [
							'id' => $characteristic->getService()->getChannel()?->getDevice()->toString(),
						],
						'channel' => [
							'id' => $characteristic->getService()->getChannel()?->getId()->toString(),
						],
						'property' => [
							'id' => $characteristic->getProperty()?->getId()->toString(),
						],
					],
				);
			}

			if ($includeValue === true) {
				$representation[Types\Representation::VALUE] = Protocol\Transformer::toClient(
					$characteristic->getProperty(),
					$characteristic->getDataType(),
					$characteristic->getValidValues(),
					$characteristic->getMaxLength(),
					$characteristic->getMinValue(),
					$characteristic->getMaxValue(),
					$characteristic->getMinStep(),
					$characteristic->getValue(),
				);
			}

			$this->subscriber->publish(
				$aid,
				$iid,
				Protocol\Transformer::toClient(
					$characteristic->getProperty(),
					$characteristic->getDataType(),
					$characteristic->getValidValues(),
					$characteristic->getMaxLength(),
					$characteristic->getMinValue(),
					$characteristic->getMaxValue(),
					$characteristic->getMinStep(),
					$characteristic->getValue(),
				),
				$characteristic->immediateNotify(),
				$clientAddress,
			);
		}

		if ($events !== null) {
			if ($clientAddress !== '') {
				if ($events) {
					$this->subscriber->subscribe($aid, $iid, $clientAddress);
				} else {
					$this->subscriber->unsubscribe($aid, $iid, $clientAddress);
				}
			} else {
				$this->logger->warning(
					'Connected client is without defined IP address and could not subscribe for events',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
						'type' => 'characteristics-controller',
						'characteristic' => [
							'type' => $characteristic->getTypeId()->toString(),
							'name' => $characteristic->getName(),
						],
					],
				);
			}
		}

		$representation[Types\Representation::STATUS] = Types\ServerStatus::SUCCESS;

		return $representation;
	}

	/**
	 * @return array<string, int>
	 */
	private function getCharacteristicRepresentationSkeleton(int $aid, int $iid): array
	{
		return [
			Types\Representation::AID => $aid,
			Types\Representation::IID => $iid,
			Types\Representation::STATUS => Types\ServerStatus::SERVICE_COMMUNICATION_FAILURE,
		];
	}

	private function getCharacteristic(
		Uuid\UuidInterface $connectorId,
		int $aid,
		int $iid,
	): Entities\Protocol\Characteristic|null
	{
		$bridge = $this->accessoryDriver->getBridge($connectorId);

		if ($bridge === null) {
			return null;
		}

		if ($aid === Constants::STANDALONE_AID) {
			$characteristic = $bridge->getIidManager()->getObject($iid);

		} else {
			$accessory = $bridge->getAccessory($aid);

			if ($accessory === null) {
				return null;
			}

			$characteristic = $accessory->getIidManager()->getObject($iid);
		}

		if (!$characteristic instanceof Entities\Protocol\Characteristic) {
			return null;
		}

		return $characteristic;
	}

}
