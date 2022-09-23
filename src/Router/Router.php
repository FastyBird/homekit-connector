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

		// Pairing process requests
		$this->post('/pair-setup', [$pairingController, 'setup']);
		$this->post('/pair-verify', [$pairingController, 'verify']);
		$this->post('/pairings', [$pairingController, 'pairings']);

		$this->post('/resource', [$pairingController, 'resource']);

		$this->group(
			'/prepare',
			function (Routing\RouteCollector $group) use ($pairingController): void {
				$group->put('', [$pairingController, 'prepare']);
			}
		);

		$this->group(
			'/identify',
			function (Routing\RouteCollector $group) use ($pairingController): void {
				$group->post('', [$pairingController, 'identify']);
			}
		);

		$this->group(
			'/accessories',
			function (Routing\RouteCollector $group) use ($accessoriesController): void {
				$group->get('', [$accessoriesController, 'index']);
			}
		);

		$this->group(
			'/characteristics',
			function (Routing\RouteCollector $group) use ($characteristicsController): void {
				$group->get('', [$characteristicsController, 'index']);
				$group->put('', [$characteristicsController, 'update']);
			}
		);
	}

}
