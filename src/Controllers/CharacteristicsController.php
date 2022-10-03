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

use FastyBird\DateTimeFactory;
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Exchange\Entities as ExchangeEntities;
use FastyBird\Exchange\Publisher as ExchangePublisher;
use FastyBird\HomeKitConnector\Clients;
use FastyBird\HomeKitConnector\Constants;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Events;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Helpers;
use FastyBird\HomeKitConnector\Protocol;
use FastyBird\HomeKitConnector\Servers;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata;
use Fig\Http\Message\StatusCodeInterface;
use IPub\SlimRouter;
use Nette\Utils;
use Psr\EventDispatcher;
use Psr\Http\Message;
use Ramsey\Uuid;
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

	/** @var Array<string, Array<int, int>> */
	private array $preparedWrites = [];

	/** @var Protocol\Driver */
	private Protocol\Driver $accessoryDriver;

	/** @var Clients\Subscriber */
	private Clients\Subscriber $subscriber;

	/** @var DateTimeFactory\DateTimeFactory */
	private DateTimeFactory\DateTimeFactory $dateTimeFactory;

	/** @var ExchangeEntities\EntityFactory */
	private ExchangeEntities\EntityFactory $entityFactory;

	/** @var ExchangePublisher\IPublisher|null */
	private ?ExchangePublisher\IPublisher $publisher;

	/** @var EventDispatcher\EventDispatcherInterface|null */
	private ?EventDispatcher\EventDispatcherInterface $dispatcher;

	/** @var DevicesModuleModels\DataStorage\ChannelsRepository */
	private DevicesModuleModels\DataStorage\ChannelsRepository $channelsRepository;

	/**
	 * @param Protocol\Driver $accessoryDriver
	 * @param Clients\Subscriber $subscriber
	 * @param DateTimeFactory\DateTimeFactory $dateTimeFactory
	 * @param ExchangeEntities\EntityFactory $entityFactory
	 * @param ExchangePublisher\IPublisher|null $publisher
	 * @param EventDispatcher\EventDispatcherInterface|null $dispatcher
	 * @param DevicesModuleModels\DataStorage\ChannelsRepository $channelsRepository
	 */
	public function __construct(
		Protocol\Driver $accessoryDriver,
		Clients\Subscriber $subscriber,
		DateTimeFactory\DateTimeFactory $dateTimeFactory,
		ExchangeEntities\EntityFactory $entityFactory,
		?ExchangePublisher\IPublisher $publisher,
		?EventDispatcher\EventDispatcherInterface $dispatcher,
		DevicesModuleModels\DataStorage\ChannelsRepository $channelsRepository,
	) {
		$this->accessoryDriver = $accessoryDriver;
		$this->subscriber = $subscriber;
		$this->dateTimeFactory = $dateTimeFactory;
		$this->entityFactory = $entityFactory;
		$this->publisher = $publisher;
		$this->dispatcher = $dispatcher;
		$this->channelsRepository = $channelsRepository;
	}

	/**
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @return Message\ResponseInterface
	 *
	 * @throws Exceptions\HapRequestError
	 * @throws Utils\JsonException
	 */
	public function index(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response
	): Message\ResponseInterface {
		var_dump($request->getUri()->getPath());
		var_dump($request->getHeaders());

		$this->logger->debug(
			'Requested list of characteristics of selected accessories',
			[
				'source'  => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type'    => 'characteristics-controller',
				'request' => [
					'query' => $request->getQueryParams(),
				],
			]
		);

		$connectorId = strval($request->getAttribute(Servers\Http::REQUEST_ATTRIBUTE_CONNECTOR));

		if (!Uuid\Uuid::isValid($connectorId)) {
			throw new Exceptions\InvalidState('Connector id could not be determined');
		}

		$connectorId = Uuid\Uuid::fromString($connectorId);

		$queryParams = $request->getQueryParams();

		if (array_key_exists('id', $queryParams)) {
			throw new Exceptions\HapRequestError(
				$request,
				Types\ServerStatus::get(Types\ServerStatus::STATUS_INVALID_VALUE_IN_REQUEST),
				'Request query does not have required parameters',
				StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		$meta = array_key_exists(Types\Representation::REPR_META, $queryParams) && (int) $queryParams[Types\Representation::REPR_META] === 1;
		$perms = array_key_exists(Types\Representation::REPR_PERM, $queryParams) && (int) $queryParams[Types\Representation::REPR_PERM] === 1;
		$type = array_key_exists(Types\Representation::REPR_TYPE, $queryParams) && (int) $queryParams[Types\Representation::REPR_TYPE] === 1;
		$ev = array_key_exists(Types\CharacteristicPermission::PERMISSION_NOTIFY, $queryParams) && (int) $queryParams[Types\CharacteristicPermission::PERMISSION_NOTIFY] === 1;

		$ids = explode(',', $queryParams['id']);

		$result = [
			Types\Representation::REPR_CHARS => [],
		];

		foreach ($ids as $id) {
			[$aid, $iid] = explode('.', $id) + [null, null];

			if ($aid === null || $iid === null) {
				throw new Exceptions\HapRequestError(
					$request,
					Types\ServerStatus::get(Types\ServerStatus::STATUS_INVALID_VALUE_IN_REQUEST),
					'Request query has invalid format pro ID parameter',
					StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY
				);
			}

			$aid = (int) $aid;
			$iid = (int) $iid;

			$result[Types\Representation::REPR_CHARS][] = $this->readCharacteristic(
				$connectorId,
				$aid,
				$iid,
				$meta,
				$perms,
				$type,
				$ev
			);
		}

		$anyError = false;

		foreach ($result[Types\Representation::REPR_CHARS] as $charResult) {
			if (
				array_key_exists(Types\Representation::REPR_STATUS, $charResult)
				&& $charResult[Types\Representation::REPR_STATUS] !== Types\ServerStatus::STATUS_SUCCESS
			) {
				$anyError = true;
			}
		}

		$response = $response->withStatus($anyError ? StatusCodeInterface::STATUS_MULTI_STATUS : StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', Servers\Http::JSON_CONTENT_TYPE);
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode($result)));

		return $response;
	}

	/**
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @return Message\ResponseInterface
	 *
	 * @throws Exceptions\HapRequestError
	 * @throws Metadata\Exceptions\FileNotFoundException
	 * @throws Utils\JsonException
	 */
	public function update(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response
	): Message\ResponseInterface {
		var_dump($request->getUri()->getPath());
		var_dump($request->getHeaders());

		$this->logger->debug(
			'Requested updating of characteristics of selected accessories',
			[
				'source'  => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type'    => 'characteristics-controller',
				'request' => [
					'query' => $request->getQueryParams(),
				],
			]
		);

		$connectorId = strval($request->getAttribute(Servers\Http::REQUEST_ATTRIBUTE_CONNECTOR));

		if (!Uuid\Uuid::isValid($connectorId)) {
			throw new Exceptions\InvalidState('Connector id could not be determined');
		}

		$connectorId = Uuid\Uuid::fromString($connectorId);

		$body = $request->getBody()->getContents();

		try {
			$body = Utils\Json::decode($body, Utils\Json::FORCE_ARRAY);
		} catch (Utils\JsonException $ex) {
			throw new Exceptions\HapRequestError(
				$request,
				Types\ServerStatus::get(Types\ServerStatus::STATUS_INVALID_VALUE_IN_REQUEST),
				'Request body could not be decoded',
				StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
				$ex
			);
		}

		if (
			!is_array($body)
			|| !array_key_exists(Types\Representation::REPR_CHARS, $body)
			|| is_array($body[Types\Representation::REPR_CHARS])
		) {
			throw new Exceptions\HapRequestError(
				$request,
				Types\ServerStatus::get(Types\ServerStatus::STATUS_INVALID_VALUE_IN_REQUEST),
				'Request body does not have required attributes',
				StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
			);
		}

		$pid = array_key_exists(Types\Representation::REPR_PID, $body) ? (int) $body[Types\Representation::REPR_PID] : null;

		$timedWriteError = false;

		if ($pid !== null) {
			if (
				!array_key_exists(strval($request->getServerParams()['REMOTE_ADDR']), $this->preparedWrites)
				|| !array_key_exists($pid, $this->preparedWrites[strval($request->getServerParams()['REMOTE_ADDR'])])
				|| $this->preparedWrites[strval($request->getServerParams()['REMOTE_ADDR'])][$pid] < $this->dateTimeFactory->getNow()->getTimestamp()
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
			Types\Representation::REPR_CHARS => [],
		];

		foreach ($body[Types\Representation::REPR_CHARS] as $setCharacteristic) {
			if (
				is_array($setCharacteristic)
				&& array_key_exists(Types\Representation::REPR_AID, $setCharacteristic)
				&& array_key_exists(Types\Representation::REPR_IID, $setCharacteristic)
			) {
				$aid = (int) $setCharacteristic[Types\Representation::REPR_AID];
				$iid = (int) $setCharacteristic[Types\Representation::REPR_IID];

				$value = array_key_exists(Types\Representation::REPR_VALUE, $setCharacteristic) ? $setCharacteristic[Types\Representation::REPR_VALUE] : null;
				$events = array_key_exists(Types\CharacteristicPermission::PERMISSION_NOTIFY, $setCharacteristic) ? (bool) $setCharacteristic[Types\CharacteristicPermission::PERMISSION_NOTIFY] : null;

				$result[Types\Representation::REPR_CHARS][] = $this->writeCharacteristic(
					$connectorId,
					$aid,
					$iid,
					$value,
					$events,
					strval($request->getServerParams()['REMOTE_ADDR']),
					intval($request->getServerParams()['REMOTE_PORT']),
					$pid,
					$timedWriteError
				);

			} else {
				throw new Exceptions\HapRequestError(
					$request,
					Types\ServerStatus::get(Types\ServerStatus::STATUS_INVALID_VALUE_IN_REQUEST),
					'Request body does not have required attributes',
					StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
				);
			}
		}

		$anyError = false;

		foreach ($result[Types\Representation::REPR_CHARS] as $charResult) {
			if (
				array_key_exists(Types\Representation::REPR_STATUS, $charResult)
				&& $charResult[Types\Representation::REPR_STATUS] !== Types\ServerStatus::STATUS_SUCCESS
			) {
				$anyError = true;
			}
		}

		$response = $response->withStatus($anyError ? StatusCodeInterface::STATUS_MULTI_STATUS : StatusCodeInterface::STATUS_NO_CONTENT);

		if ($anyError) {
			$response = $response->withHeader('Content-Type', Servers\Http::JSON_CONTENT_TYPE);
			$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode($result)));
		}

		return $response;
	}

	/**
	 * Handles a client request to prepare to write
	 *
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @return Message\ResponseInterface
	 *
	 * @throws Exceptions\HapRequestError
	 * @throws Utils\JsonException
	 */
	public function prepare(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response
	): Message\ResponseInterface {
		var_dump($request->getUri()->getPath());

		$body = $request->getBody()->getContents();

		try {
			$body = Utils\Json::decode($body, Utils\Json::FORCE_ARRAY);
		} catch (Utils\JsonException $ex) {
			throw new Exceptions\HapRequestError(
				$request,
				Types\ServerStatus::get(Types\ServerStatus::STATUS_INVALID_VALUE_IN_REQUEST),
				'Request body could not be decoded',
				StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
				$ex
			);
		}

		if (
			!is_array($body)
			|| !array_key_exists(Types\Representation::REPR_TTL, $body)
			|| !array_key_exists(Types\Representation::REPR_PID, $body)
		) {
			throw new Exceptions\HapRequestError(
				$request,
				Types\ServerStatus::get(Types\ServerStatus::STATUS_INVALID_VALUE_IN_REQUEST),
				'Request body does not have required attributes',
				StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
			);
		}

		$clientAddress = strval($request->getServerParams()['REMOTE_ADDR']);

		if (!array_key_exists($clientAddress, $this->preparedWrites)) {
			$this->preparedWrites[$clientAddress] = [];
		}

		$this->preparedWrites[$clientAddress][(int) $body[Types\Representation::REPR_PID]] = intval($this->dateTimeFactory->getNow()->getTimestamp()) + ((int) $body[Types\Representation::REPR_TTL] / 1000);

		$result = [
			Types\Representation::REPR_STATUS => Types\ServerStatus::STATUS_SUCCESS,
		];

		$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', Servers\Http::JSON_CONTENT_TYPE);
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode($result)));

		return $response;
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param int $aid
	 * @param int $iid
	 * @param bool $meta
	 * @param bool $perms
	 * @param bool $type
	 * @param bool $ev
	 *
	 * @return Array<string, bool|int|int[]|float|string|string[]|null>
	 */
	private function readCharacteristic(
		Uuid\UuidInterface $connectorId,
		int $aid,
		int $iid,
		bool $meta,
		bool $perms,
		bool $type,
		bool $ev
	): array {
		$representation = $this->getCharacteristicRepresentationSkeleton($aid, $iid);

		$characteristic = $this->getCharacteristic($connectorId, $aid, $iid);

		if ($characteristic === null) {
			return $representation;
		}

		if (!in_array(Types\CharacteristicPermission::PERMISSION_READ, $characteristic->getPermissions(), true)) {
			$representation[Types\Representation::REPR_STATUS] = Types\ServerStatus::STATUS_WRITE_ONLY_CHARACTERISTIC;

			return $representation;
		}

		$representation[Types\Representation::REPR_STATUS] = Types\ServerStatus::STATUS_SUCCESS;
		$representation[Types\Representation::REPR_VALUE] = $characteristic->getProperty() !== null ? Protocol\Transformer::toClient(
			$characteristic->getProperty(),
			$characteristic->getDataType(),
			$characteristic->getValidValues(),
			$characteristic->getMaxLength(),
			$characteristic->getMinValue(),
			$characteristic->getMaxValue(),
			$characteristic->getMinStep(),
			$characteristic->getActualValue(),
		) : null;

		if ($perms) {
			$representation[Types\Representation::REPR_PERM] = $characteristic->getPermissions();
		}

		if ($type) {
			$representation[Types\Representation::REPR_PERM] = Helpers\Protocol::uuidToHapType(
				$characteristic->getTypeId()
			);
		}

		if ($meta) {
			$representation = array_merge($representation, $characteristic->getMeta());
		}

		if ($ev) {
			$representation[Types\CharacteristicPermission::PERMISSION_NOTIFY] = in_array(
				Types\CharacteristicPermission::PERMISSION_NOTIFY,
				$characteristic->getPermissions(),
				true
			);
		}

		return $representation;
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param int $aid
	 * @param int $iid
	 * @param int|float|string|bool|null $value
	 * @param bool|null $events
	 * @param string $clientAddress
	 * @param int $clientPort
	 * @param int|null $pid
	 * @param bool $timedWriteError
	 *
	 * @return Array<string, int>
	 *
	 * @throws Metadata\Exceptions\FileNotFoundException
	 * @throws Utils\JsonException
	 */
	public function writeCharacteristic(
		Uuid\UuidInterface $connectorId,
		int $aid,
		int $iid,
		int|float|string|bool|null $value,
		?bool $events,
		string $clientAddress,
		int $clientPort,
		?int $pid,
		bool $timedWriteError
	): array {
		$representation = $this->getCharacteristicRepresentationSkeleton($aid, $iid);

		$characteristic = $this->getCharacteristic($connectorId, $aid, $iid);

		if ($characteristic === null) {
			return $representation;
		}

		if (
			!in_array(Types\CharacteristicPermission::PERMISSION_WRITE, $characteristic->getPermissions(), true)
			&& !in_array(Types\CharacteristicPermission::PERMISSION_TIMED_WRITE, $characteristic->getPermissions(), true)
			&& !in_array(Types\CharacteristicPermission::PERMISSION_WRITE_RESPONSE, $characteristic->getPermissions(), true)
		) {
			$representation[Types\Representation::REPR_STATUS] = Types\ServerStatus::STATUS_READ_ONLY_CHARACTERISTIC;

			return $representation;
		}

		if (
			$pid !== null
			&& !in_array(Types\CharacteristicPermission::PERMISSION_TIMED_WRITE, $characteristic->getPermissions(), true)
		) {
			return $representation;
		}

		if (
			$pid === null
			&& in_array(Types\CharacteristicPermission::PERMISSION_TIMED_WRITE, $characteristic->getPermissions(), true)
		) {
			$representation[Types\Representation::REPR_STATUS] = Types\ServerStatus::STATUS_INVALID_VALUE_IN_REQUEST;

			return $representation;
		}

		if ($timedWriteError) {
			$representation[Types\Representation::REPR_STATUS] = Types\ServerStatus::STATUS_INVALID_VALUE_IN_REQUEST;

			return $representation;
		}

		if ($value !== null) {
			$this->dispatcher?->dispatch(new Events\ClientWriteCharacteristic($characteristic, $value));

			if ($characteristic->getProperty() === null) {
				$this->logger->error(
					'Accessory characteristic is not connected to any property',
					[
						'source'         => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
						'type'           => 'characteristics-controller',
						'characteristic' => [
							'type' => $characteristic->getTypeId()->toString(),
							'name' => $characteristic->getName(),
						],
					]
				);
			} else {
				$value = Protocol\Transformer::fromClient(
					$characteristic->getProperty(),
					$characteristic->getDataType(),
					$value,
				);

				if (!$characteristic->isAlwaysNull()) {
					$characteristic->setExpectedValue($value);
				}

				if ($characteristic->getProperty() instanceof Metadata\Entities\Modules\DevicesModule\IConnectorDynamicPropertyEntity) {
					$this->publisher?->publish(
						Metadata\Types\ModuleSourceType::get(Metadata\Types\ModuleSourceType::SOURCE_MODULE_DEVICES),
						Metadata\Types\RoutingKeyType::get(Metadata\Types\RoutingKeyType::ROUTE_CONNECTOR_PROPERTY_ACTION),
						$this->entityFactory->create(
							Utils\Json::encode([
								'action'    => Metadata\Types\PropertyActionType::ACTION_SET,
								'connector' => $characteristic->getProperty()->getConnector()->toString(),
								'property'  => $characteristic->getProperty()->getId()->toString(),
							]),
							Metadata\Types\RoutingKeyType::get(Metadata\Types\RoutingKeyType::ROUTE_CONNECTOR_PROPERTY_ACTION)
						),
					);

					$this->logger->debug(
						'Apple client requested to set expected value to connector dynamic property',
						[
							'source'         => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
							'type'           => 'characteristics-controller',
							'characteristic' => [
								'type' => $characteristic->getTypeId()->toString(),
								'name' => $characteristic->getName(),
							],
							'connector'      => [
								'id' => $characteristic->getProperty()->getConnector()->toString(),
							],
							'property'       => [
								'id' => $characteristic->getProperty()->getId()->toString(),
							],
						]
					);
				} elseif ($characteristic->getProperty() instanceof Metadata\Entities\Modules\DevicesModule\IDeviceDynamicPropertyEntity) {
					$this->publisher?->publish(
						Metadata\Types\ModuleSourceType::get(Metadata\Types\ModuleSourceType::SOURCE_MODULE_DEVICES),
						Metadata\Types\RoutingKeyType::get(Metadata\Types\RoutingKeyType::ROUTE_DEVICE_PROPERTY_ACTION),
						$this->entityFactory->create(
							Utils\Json::encode([
								'action'   => Metadata\Types\PropertyActionType::ACTION_SET,
								'device'   => $characteristic->getProperty()->getDevice()->toString(),
								'property' => $characteristic->getProperty()->getId()->toString(),
							]),
							Metadata\Types\RoutingKeyType::get(Metadata\Types\RoutingKeyType::ROUTE_DEVICE_PROPERTY_ACTION)
						),
					);

					$this->logger->debug(
						'Apple client requested to set expected value to device dynamic property',
						[
							'source'         => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
							'type'           => 'characteristics-controller',
							'characteristic' => [
								'type' => $characteristic->getTypeId()->toString(),
								'name' => $characteristic->getName(),
							],
							'device'         => [
								'id' => $characteristic->getProperty()->getDevice()->toString(),
							],
							'property'       => [
								'id' => $characteristic->getProperty()->getId()->toString(),
							],
						]
					);
				} elseif ($characteristic->getProperty() instanceof Metadata\Entities\Modules\DevicesModule\IChannelDynamicPropertyEntity) {
					$channel = $this->channelsRepository->findById($characteristic->getProperty()->getChannel());

					if ($channel !== null) {
						$this->publisher?->publish(
							Metadata\Types\ModuleSourceType::get(Metadata\Types\ModuleSourceType::SOURCE_MODULE_DEVICES),
							Metadata\Types\RoutingKeyType::get(Metadata\Types\RoutingKeyType::ROUTE_CHANNEL_PROPERTY_ACTION),
							$this->entityFactory->create(
								Utils\Json::encode([
									'action'   => Metadata\Types\PropertyActionType::ACTION_SET,
									'device'   => $channel->getDevice()->toString(),
									'channel'  => $characteristic->getProperty()->getChannel()->toString(),
									'property' => $characteristic->getProperty()->getId()->toString(),
								]),
								Metadata\Types\RoutingKeyType::get(Metadata\Types\RoutingKeyType::ROUTE_CHANNEL_PROPERTY_ACTION)
							),
						);

						$this->logger->debug(
							'Apple client requested to set expected value to device channel dynamic property',
							[
								'source'         => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
								'type'           => 'characteristics-controller',
								'characteristic' => [
									'type' => $characteristic->getTypeId()->toString(),
									'name' => $characteristic->getName(),
								],
								'device'         => [
									'id' => $channel->getDevice()->toString(),
								],
								'channel'       => [
									'id' => $characteristic->getProperty()->getChannel()->toString(),
								],
								'property'       => [
									'id' => $characteristic->getProperty()->getId()->toString(),
								],
							]
						);
					} else {
						$this->logger->error(
							'Channel for characteristic dynamic property was not found',
							[
								'source'         => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
								'type'           => 'characteristics-controller',
								'characteristic' => [
									'type' => $characteristic->getTypeId()->toString(),
									'name' => $characteristic->getName(),
								],
								'channel'       => [
									'id' => $characteristic->getProperty()->getChannel()->toString(),
								],
								'property'       => [
									'id' => $characteristic->getProperty()->getId()->toString(),
								],
							]
						);
					}
				}
			}
		}

		if ($events !== null) {
			if ($events) {
				$this->subscriber->subscribe($aid, $iid, $clientAddress);
			} else {
				$this->subscriber->unsubscribe($aid, $iid, $clientAddress);
			}
		}

		$representation[Types\Representation::REPR_STATUS] = Types\ServerStatus::STATUS_SUCCESS;

		return $representation;
	}

	/**
	 * @param int $aid
	 * @param int $iid
	 *
	 * @return Array<string, int>
	 */
	private function getCharacteristicRepresentationSkeleton(int $aid, int $iid): array
	{
		return [
			Types\Representation::REPR_AID    => $aid,
			Types\Representation::REPR_IID    => $iid,
			Types\Representation::REPR_STATUS => Types\ServerStatus::STATUS_SERVICE_COMMUNICATION_FAILURE,
		];
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 * @param int $aid
	 * @param int $iid
	 *
	 * @return Entities\Protocol\Characteristic|null
	 */
	private function getCharacteristic(
		Uuid\UuidInterface $connectorId,
		int $aid,
		int $iid
	): ?Entities\Protocol\Characteristic {
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
