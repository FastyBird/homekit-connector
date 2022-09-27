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
use FastyBird\HomeKitConnector\Clients;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Helpers;
use FastyBird\HomeKitConnector\Types;
use Fig\Http\Message\StatusCodeInterface;
use IPub\SlimRouter;
use Nette\Utils;
use Psr\Http\Message;
use Ramsey\Uuid;

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

	/** @var Helpers\Connector */
	private Helpers\Connector $connectorHelper;

	/**
	 * @param Helpers\Connector $connectorHelper
	 */
	public function __construct(
		Helpers\Connector $connectorHelper
	) {
		$this->connectorHelper = $connectorHelper;
	}

	/**
	 * Handles a client request to get the accessories
	 *
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @return Message\ResponseInterface
	 *
	 * @throws Utils\JsonException
	 */
	public function index(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response
	): Message\ResponseInterface {
		var_dump($request->getUri()->getPath());
		$connectorId = strval($request->getAttribute(Clients\Http::REQUEST_ATTRIBUTE_CONNECTOR));

		if (!Uuid\Uuid::isValid($connectorId)) {
			throw new Exceptions\InvalidState('Connector id could not be determined');
		}

		$connectorId = Uuid\Uuid::fromString($connectorId);

		// TODO: Implement
		$result = [
			'accessories' => [],
		];

		$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', Clients\Http::JSON_CONTENT_TYPE);
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode($result)));

		return $response;
	}

	/**
	 * @param Message\ServerRequestInterface $request
	 * @param Message\ResponseInterface $response
	 *
	 * @return Message\ResponseInterface
	 *
	 * @throws DBAL\Exception
	 * @throws SlimRouter\Exceptions\HttpException
	 */
	public function identify(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response
	): Message\ResponseInterface {
		var_dump($request->getUri()->getPath());
		var_dump($request->getBody()->getContents());
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
			throw new Exceptions\HapRequestError(
				$request,
				Types\ServerStatus::get(Types\ServerStatus::STATUS_INSUFFICIENT_PRIVILEGES),
				'Connector is already paired with client',
				StatusCodeInterface::STATUS_BAD_REQUEST
			);
		}

		// TODO: Implement

		$response = $response->withStatus(StatusCodeInterface::STATUS_NO_CONTENT);

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
	 * @throws Utils\JsonException
	 */
	public function prepare(
		Message\ServerRequestInterface $request,
		Message\ResponseInterface $response
	): Message\ResponseInterface {
		var_dump($request->getUri()->getPath());
		var_dump($request->getBody()->getContents());
		// TODO: Implement

		$result = [];

		$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', Clients\Http::JSON_CONTENT_TYPE);
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode($result)));

		return $response;
	}

	/**
	 * Get a snapshot from the camera
	 *
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

		$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', 'image/jpeg');

		return $response;
	}

}
