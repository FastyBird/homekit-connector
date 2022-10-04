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
use function assert;

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

	/**
	 * @param Persistence\ManagerRegistry $managerRegistry
	 */
	public function __construct(private Persistence\ManagerRegistry $managerRegistry)
	{
	}

	/**
	 * @param Queries\FindClientsQuery<Entities\Client> $queryObject
	 *
	 * @return Entities\Client|null
	 */
	public function findOneBy(
		Queries\FindClientsQuery $queryObject,
	): Entities\Client|null {
		/** @var mixed $client */
		$client = $queryObject->fetchOne($this->getRepository());
		assert($client instanceof Entities\Client || $client === null);

		return $client;
	}

	/**
	 * @param Queries\FindClientsQuery<Entities\Client> $queryObject
	 *
	 * @return DoctrineOrmQuery\ResultSet<Entities\Client>
	 */
	public function getResultSet(
		Queries\FindClientsQuery $queryObject,
	): DoctrineOrmQuery\ResultSet {
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
