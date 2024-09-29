<?php declare(strict_types = 1);

/**
 * DiagnosticsController.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Controllers
 * @since          1.0.0
 *
 * @date           23.09.24
 */

namespace FastyBird\Connector\HomeKit\Controllers;

use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Servers;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
use IPub\SlimRouter;
use Nette\Utils;
use Psr\Http\Message;
use Ramsey\Uuid;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_unique;
use function in_array;
use function is_array;
use function is_string;
use function strval;

/**
 * Diagnostics controller
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Controllers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class DiagnosticsController extends BaseController
{

	public function __construct(
		private readonly Protocol\Driver $accessoriesDriver,
	)
	{
	}

	/**
	 * Handles a client request to show diagnostics data
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
		$connectorId = strval($request->getAttribute(Servers\Http::REQUEST_ATTRIBUTE_CONNECTOR));

		if (!Uuid\Uuid::isValid($connectorId)) {
			throw new Exceptions\InvalidState('Connector id could not be determined');
		}

		$connectorId = Uuid\Uuid::fromString($connectorId);

		$onlyDevices = [];
		$filterDevices = false;

		$params = $request->getQueryParams();

		if (array_key_exists('device', $params) && is_string($params['device'])) {
			if (Uuid\Uuid::isValid($params['device'])) {
				$onlyDevices[] = Uuid\Uuid::fromString($params['device']);
			}

			$filterDevices = true;

		} elseif (array_key_exists('devices', $params) && is_array($params['devices'])) {
			$onlyDevices[] = array_filter(
				array_map(
					static fn (string $id): Uuid\UuidInterface|null => Uuid\Uuid::isValid($id) ? Uuid\Uuid::fromString(
						$id,
					) : null,
					$params['devices'],
				),
				static fn (Uuid\UuidInterface|null $id): bool => $id !== null,
			);

			$filterDevices = true;
		}

		$onlyDevices = array_unique($onlyDevices);

		$result = [];

		foreach ($this->accessoriesDriver->getBridge($connectorId)?->getAccessories() ?? [] as $accessory) {
			if ($filterDevices && in_array($accessory->getId(), $onlyDevices, true)) {
				continue;
			}

			$services = [];

			foreach ($accessory->getServices() as $service) {
				$characteristics = [];

				foreach ($service->getCharacteristics() as $characteristic) {
					$characteristics[] = [
						'iid' => $accessory->getIidManager()->getIid($characteristic),
						'name' => $characteristic->getName(),
						'type' => $characteristic->getTypeId()->toString(),
						'data_type' => $characteristic->getDataType()->value,
						'permissions' => $characteristic->getPermissions(),
						'valid_values' => $characteristic->getValidValues(),
						'min_value' => $characteristic->getMinValue(),
						'max_value' => $characteristic->getMaxValue(),
						'min_step' => $characteristic->getMinStep(),
						'max_length' => $characteristic->getMaxLength(),
						'value' => [
							'default' => $characteristic->getDefault(),
							'actual' => $characteristic->getActualValue(),
							'expected' => $characteristic->getExpectedValue(),
							'is_pending' => $characteristic->isPending(),
							'is_valid' => $characteristic->isValid(),
						],
					];
				}

				$services[] = [
					'iid' => $accessory->getIidManager()->getIid($service),
					'name' => $service->getName(),
					'type' => $service->getTypeId()->toString(),
					'characteristics' => $characteristics,
				];
			}

			$result[] = [
				'id' => $accessory->getId()->toString(),
				'aid' => $accessory->getAid(),
				'name' => $accessory->getName(),
				'category' => $accessory->getCategory(),
				'services' => $services,
			];
		}

		$response = $response->withStatus(StatusCodeInterface::STATUS_OK);
		$response = $response->withHeader('Content-Type', 'application/json');
		$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode($result)));

		return $response;
	}

}
