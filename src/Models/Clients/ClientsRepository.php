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

namespace FastyBird\HomeKitConnector\Models\Clients;

use Doctrine\ORM;
use Doctrine\Persistence;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Queries;
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

	public function __construct(private readonly Persistence\ManagerRegistry $managerRegistry)
	{
	}

	/**
	 * @phpstan-param Queries\FindClients<Entities\Client> $queryObject
	 */
	public function findOneBy(
		Queries\FindClients $queryObject,
	): Entities\Client|null
	{
		return $queryObject->fetchOne($this->getRepository());
	}

	/**
	 * @param Queries\FindClients<Entities\Client> $queryObject
	 *
	 * @return DoctrineOrmQuery\ResultSet<Entities\Client>
	 */
	public function getResultSet(
		Queries\FindClients $queryObject,
	): DoctrineOrmQuery\ResultSet
	{
		$result = $queryObject->fetch($this->getRepository());

		if (!$result instanceof DoctrineOrmQuery\ResultSet) {
			throw new Exceptions\InvalidState('Result set for given query could not be loaded.');
		}

		return $result;
	}

	/**
	 * @return ORM\EntityRepository<Entities\Client>
	 */
	private function getRepository(): ORM\EntityRepository
	{
		if ($this->repository === null) {
			$repository = $this->managerRegistry->getRepository(Entities\Client::class);

			if (!$repository instanceof ORM\EntityRepository) {
				throw new Exceptions\InvalidState('Entity repository could not be loaded');
			}

			$this->repository = $repository;
		}

		return $this->repository;
	}

}
