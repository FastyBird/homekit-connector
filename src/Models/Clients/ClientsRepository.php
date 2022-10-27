<?php declare(strict_types = 1);

/**
 * ClientsRepository.php
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

use Doctrine\ORM;
use Doctrine\Persistence;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use IPub\DoctrineOrmQuery;
use Nette;

/**
 * Clients repository
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Models
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class ClientsRepository
{

	use Nette\SmartObject;

	/** @var ORM\EntityRepository<Entities\Client>|null */
	private ORM\EntityRepository|null $repository = null;

	public function __construct(
		private readonly DevicesUtilities\Database $database,
		private readonly Persistence\ManagerRegistry $managerRegistry,
	)
	{
	}

	/**
	 * @phpstan-param Queries\FindClients<Entities\Client> $queryObject
	 *
	 * @throws DevicesExceptions\InvalidState
	 */
	public function findOneBy(
		Queries\FindClients $queryObject,
	): Entities\Client|null
	{
		return $this->database->query(
			fn (): Entities\Client|null => $queryObject->fetchOne($this->getRepository()),
		);
	}

	/**
	 * @param Queries\FindClients<Entities\Client> $queryObject
	 *
	 * @return DoctrineOrmQuery\ResultSet<Entities\Client>
	 *
	 * @throws DevicesExceptions\InvalidState
	 */
	public function getResultSet(
		Queries\FindClients $queryObject,
	): DoctrineOrmQuery\ResultSet
	{
		return $this->database->query(
			function () use ($queryObject): DoctrineOrmQuery\ResultSet {
				$result = $queryObject->fetch($this->getRepository());

				if (!$result instanceof DoctrineOrmQuery\ResultSet) {
					throw new Exceptions\InvalidState('Result set for given query could not be loaded.');
				}

				return $result;
			},
		);
	}

	/**
	 * @return ORM\EntityRepository<Entities\Client>
	 */
	private function getRepository(): ORM\EntityRepository
	{
		if ($this->repository === null) {
			$this->repository = $this->managerRegistry->getRepository(Entities\Client::class);
		}

		return $this->repository;
	}

}
