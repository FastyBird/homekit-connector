<?php declare(strict_types = 1);

/**
 * AccessoriesController.php
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

use Doctrine\DBAL;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use IPub\SlimRouter;
use Nette\Utils;
use Psr\Http\Message;
use Ramsey\Uuid;
use function strval;

/**
 * Accessories controller
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Controllers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class AccessoriesController extends BaseController
{

	public function __construct(
		private readonly Helpers\Connector $connectorHelper,
		private readonly Protocol\Driver $accessoriesDriver,
	)
	{
	}

	/**
	 * Handles a client request to get the accessories
	 *
	 * @throws Exceptions\InvalidState
	 * @throws InvalidArgumentException
	 * @throws Utils\JsonException
	 */
	public function index(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		$this->logger->debug(
			'Requested list of all registered accessories',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type' => 'accessories-controller',
				'request' => [
					'address' => $request->getServerParams()['REMOTE_ADDR'],
					'path' => $request->getUri()->getPath(),
				],
			],
		);

		$connectorId = strval($request->getAttribute(Servers\Http::REQUEST_ATTRIBUTE_CONNECTOR));

		if (!Uuid\Uuid::isValid($connectorId)) {
			throw new Exceptions\InvalidState('Connector id could not be determined');
		}

		$connectorId = Uuid\Uuid::fromString($connectorId);

		$result = $this->accessoriesDriver->toHap($connectorId);

		$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', Servers\Http::JSON_CONTENT_TYPE);
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode($result)));

		return $response;
	}

	/**
	 * Help user to locate accessory
	 *
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\HapRequestError
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws InvalidArgumentException
	 * @throws MetadataExceptions\FileNotFound
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 */
	public function identify(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		$this->logger->debug(
			'Requested accessories identify routine',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type' => 'accessories-controller',
				'request' => [
					'address' => $request->getServerParams()['REMOTE_ADDR'],
					'path' => $request->getUri()->getPath(),
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

		if ((bool) $paired) {
			$this->logger->error(
				'Paired connector could not trigger identify routine',
				[
					'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type' => 'accessories-controller',
					'request' => [
						'address' => $request->getServerParams()['REMOTE_ADDR'],
						'path' => $request->getUri()->getPath(),
					],
				],
			);

			throw new Exceptions\HapRequestError(
				$request,
				Types\ServerStatus::get(Types\ServerStatus::STATUS_INSUFFICIENT_PRIVILEGES),
				'Connector is already paired with client',
				StatusCodeInterface::STATUS_BAD_REQUEST,
			);
		}

		// TODO: Call identify routine on connector? or accessories?

		$response = $response->withStatus(StatusCodeInterface::STATUS_NO_CONTENT);

		return $response;
	}

	/**
	 * Get a snapshot from the camera or other resource from accessory
	 *
	 * @throws InvalidArgumentException
	 */
	public function resource(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		$this->logger->debug(
			'Requested fetching accessory resource',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type' => 'accessories-controller',
				'request' => [
					'address' => $request->getServerParams()['REMOTE_ADDR'],
					'path' => $request->getUri()->getPath(),
				],
			],
		);

		// TODO: Implement

		$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', 'image/jpeg');

		return $response;
	}

}
