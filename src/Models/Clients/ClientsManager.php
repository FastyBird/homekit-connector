<?php declare(strict_types = 1);

/**
 * ClientsManager.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Models
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit\Models\Clients;

use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Models;
use IPub\DoctrineCrud;
use IPub\DoctrineCrud\Crud;
use Nette;
use Nette\Utils;
use function assert;

/**
 * Clients entities manager
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Models
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ClientsManager
{

	use Nette\SmartObject;

	/**
	 * @param Crud\IEntityCrud<Entities\Client> $entityCrud
	 */
	public function __construct(private readonly Crud\IEntityCrud $entityCrud)
	{
	}

	public function create(Utils\ArrayHash $values): Entities\Client
	{
		// Get entity creator
		$creator = $this->entityCrud->getEntityCreator();

		$entity = $creator->create($values);
		assert($entity instanceof Entities\Client);

		return $entity;
	}

	/**
	 * @throws DoctrineCrud\Exceptions\InvalidArgumentException
	 */
	public function update(
		Entities\Client $entity,
		Utils\ArrayHash $values,
	): Entities\Client
	{
		$entity = $this->entityCrud->getEntityUpdater()
			->update($values, $entity);
		assert($entity instanceof Entities\Client);

		return $entity;
	}

	/**
	 * @throws DoctrineCrud\Exceptions\InvalidArgumentException
	 */
	public function delete(Entities\Client $entity): bool
	{
		// Delete entity from database
		return $this->entityCrud->getEntityDeleter()
			->delete($entity);
	}

}
