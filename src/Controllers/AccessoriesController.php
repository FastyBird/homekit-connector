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

namespace FastyBird\HomeKitConnector\Controllers;

use Doctrine\DBAL;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Helpers;
use FastyBird\HomeKitConnector\Protocol;
use FastyBird\HomeKitConnector\Servers;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata;
use Fig\Http\Message\StatusCodeInterface;
use IPub\SlimRouter;
use Nette\Utils;
use Psr\Http\Message;
use Ramsey\Uuid;
use function strval;
use function var_dump;

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
		private Helpers\Connector $connectorHelper,
		private Protocol\Driver $accessoriesDriver,
	)
	{
	}

	/**
	 * Handles a client request to get the accessories
	 *
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @throws Utils\JsonException
	 */
	public function index(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		var_dump($request->getUri()->getPath());

		$this->logger->debug(
			'Requested list of all registered accessories',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type' => 'accessories-controller',
			],
		);

		$connectorId = strval($request->getAttribute(Servers\Http::REQUEST_ATTRIBUTE_CONNECTOR));

		if (!Uuid\Uuid::isValid($connectorId)) {
			throw new Exceptions\InvalidState('Connector id could not be determined');
		}

		$connectorId = Uuid\Uuid::fromString($connectorId);

		$result = $this->accessoriesDriver->toHap($connectorId);
		var_dump($result);
		$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', Servers\Http::JSON_CONTENT_TYPE);
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode($result)));

		return $response;
	}

	/**
	 * Help user to locate accessory
	 *
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @throws DBAL\Exception
	 * @throws SlimRouter\Exceptions\HttpException
	 */
	public function identify(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		var_dump($request->getUri()->getPath());
		var_dump($request->getHeaders());

		$this->logger->debug(
			'Requested accessories identify routine',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type' => 'accessories-controller',
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
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 */
	public function resource(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response,
	): Message\ResponseInterface
	{
		var_dump($request->getUri()->getPath());
		var_dump($request->getHeaders());

		$this->logger->debug(
			'Requested fetching accessory resource',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type' => 'accessories-controller',
			],
		);

		// TODO: Implement

		$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', 'image/jpeg');

		return $response;
	}

}
