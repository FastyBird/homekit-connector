<?php declare(strict_types = 1);

/**
 * AccessoriesController.php
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

use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Core\Tools\Exceptions as ToolsExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use IPub\SlimRouter;
use Nette\Utils;
use Psr\Http\Message;
use Ramsey\Uuid;
use TypeError;
use ValueError;
use function boolval;
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
		private readonly Protocol\Driver $accessoriesDriver,
		private readonly DevicesModels\Configuration\Connectors\Properties\Repository $connectorsPropertiesConfigurationRepository,
	)
	{
	}

	/**
	 * Handles a client request to get the accessories
	 *
	 * @throws Exceptions\InvalidState
	 * @throws InvalidArgumentException
	 * @throws Utils\JsonException
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function index(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		$this->logger->debug(
			'Requested list of all registered accessories',
			[
				'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
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
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\HapRequestError
	 * @throws Exceptions\InvalidState
	 * @throws InvalidArgumentException
	 * @throws ToolsExceptions\InvalidState
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function identify(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		$this->logger->debug(
			'Requested accessories identify routine',
			[
				'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
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

		$findConnectorPropertyQuery = new Queries\Configuration\FindConnectorVariableProperties();
		$findConnectorPropertyQuery->byConnectorId($connectorId);
		$findConnectorPropertyQuery->byIdentifier(Types\ConnectorPropertyIdentifier::PAIRED);

		$pairedProperty = $this->connectorsPropertiesConfigurationRepository->findOneBy(
			$findConnectorPropertyQuery,
			DevicesDocuments\Connectors\Properties\Variable::class,
		);

		if ($pairedProperty !== null && boolval($pairedProperty->getValue()) === true) {
			$this->logger->error(
				'Paired connector could not trigger identify routine',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'accessories-controller',
					'request' => [
						'address' => $request->getServerParams()['REMOTE_ADDR'],
						'path' => $request->getUri()->getPath(),
					],
				],
			);

			throw new Exceptions\HapRequestError(
				$request,
				Types\ServerStatus::INSUFFICIENT_PRIVILEGES,
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
				'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
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
