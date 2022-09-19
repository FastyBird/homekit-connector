<?php declare(strict_types = 1);

/**
 * Router.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Router
 * @since          0.19.0
 *
 * @date           19.09.22
 */

namespace FastyBird\HomeKitConnector\Router;

use FastyBird\HomeKitConnector\Controllers;
use IPub\SlimRouter\Routing;

/**
 * Connector router configuration
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Router
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Router extends Routing\Router
{

	/** @var Controllers\PairingController */
	private Controllers\PairingController $pairingController;

	/** @var Controllers\AccessoriesController */
	private Controllers\AccessoriesController $accessoriesController;

	/** @var Controllers\CharacteristicsController */
	private Controllers\CharacteristicsController $characteristicsController;

	/**
	 * @param Controllers\PairingController $pairingController
	 * @param Controllers\AccessoriesController $accessoriesController
	 * @param Controllers\CharacteristicsController $characteristicsController
	 */
	public function __construct(
		Controllers\PairingController $pairingController,
		Controllers\AccessoriesController $accessoriesController,
		Controllers\CharacteristicsController $characteristicsController
	) {
		parent::__construct();

		$this->pairingController = $pairingController;
		$this->accessoriesController = $accessoriesController;
		$this->characteristicsController = $characteristicsController;
	}

	/**
	 * @return void
	 */
	public function registerRoutes(): void
	{
		// Pairing process requests
		$this->post('/pair-setup', [$this->pairingController, 'setup']);
		$this->post('/pair-verify', [$this->pairingController, 'verify']);
		$this->post('/pairings', [$this->pairingController, 'pairings']);
		$this->post('/resource', [$this->pairingController, 'resource']);

		$this->group('/prepare', function (Routing\RouteCollector $group): void {
			$group->put('', [$this->pairingController, 'prepare']);
		});

		$this->group('/accessories', function (Routing\RouteCollector $group): void {
			$group->get('', [$this->accessoriesController, 'index']);
		});

		$this->group('/characteristics', function (Routing\RouteCollector $group): void {
			$group->get('', [$this->characteristicsController, 'index']);
			$group->put('', [$this->characteristicsController, 'update']);
		});
	}

}
