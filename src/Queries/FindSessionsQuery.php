<?php declare(strict_types = 1);

/**
 * FindSessionsQuery.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queries
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\HomeKitConnector\Queries;

use Closure;
use Doctrine\ORM;
use FastyBird\DevicesModule\Entities as DevicesModuleEntities;
use FastyBird\HomeKitConnector\Entities;
use IPub\DoctrineOrmQuery;
use Ramsey\Uuid;

/**
 * Find sessions entities query
 *
 * @package          FastyBird:HomeKitConnector!
 * @subpackage       Queries
 *
 * @author           Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @phpstan-template T of Entities\Session
 * @phpstan-extends  DoctrineOrmQuery\QueryObject<T>
 */
class FindSessionsQuery extends DoctrineOrmQuery\QueryObject
{

	/** @var Closure[] */
	private array $filter = [];

	/** @var Closure[] */
	private array $select = [];

	/**
	 * @param Uuid\UuidInterface $id
	 *
	 * @return void
	 */
	public function byId(Uuid\UuidInterface $id): void
	{
		$this->filter[] = function (ORM\QueryBuilder $qb) use ($id): void {
			$qb->andWhere('s.id = :id')->setParameter('id', $id, Uuid\Doctrine\UuidBinaryType::NAME);
		};
	}

	/**
	 * @param string $clientUid
	 *
	 * @return void
	 */
	public function byClientUid(string $clientUid): void
	{
		$this->filter[] = function (ORM\QueryBuilder $qb) use ($clientUid): void {
			$qb->andWhere('s.clientUid = :clientUid')->setParameter('clientUid', $clientUid);
		};
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 *
	 * @return void
	 */
	public function byConnectorId(Uuid\UuidInterface $connectorId): void
	{
		$this->select[] = function (ORM\QueryBuilder $qb): void {
			$qb->addSelect('connector');
			$qb->join('s.connector', 'connector');
		};

		$this->filter[] = function (ORM\QueryBuilder $qb) use ($connectorId): void {
			$qb->andWhere('connector.id = :connector')
				->setParameter('connector', $connectorId, Uuid\Doctrine\UuidBinaryType::NAME);
		};
	}

	/**
	 * @param DevicesModuleEntities\Connectors\IConnector $connector
	 *
	 * @return void
	 */
	public function forConnector(DevicesModuleEntities\Connectors\IConnector $connector): void
	{
		$this->select[] = function (ORM\QueryBuilder $qb): void {
			$qb->addSelect('connector');
			$qb->join('s.connector', 'connector');
		};

		$this->filter[] = function (ORM\QueryBuilder $qb) use ($connector): void {
			$qb->andWhere('connector.id = :connector')
				->setParameter('connector', $connector->getId(), Uuid\Doctrine\UuidBinaryType::NAME);
		};
	}

	/**
	 * @param ORM\EntityRepository $repository
	 *
	 * @return ORM\QueryBuilder
	 *
	 * @phpstan-param ORM\EntityRepository<T> $repository
	 */
	protected function doCreateQuery(ORM\EntityRepository $repository): ORM\QueryBuilder
	{
		return $this->createBasicDql($repository);
	}

	/**
	 * @param ORM\EntityRepository $repository
	 *
	 * @return ORM\QueryBuilder
	 *
	 * @phpstan-param ORM\EntityRepository<T> $repository
	 */
	private function createBasicDql(ORM\EntityRepository $repository): ORM\QueryBuilder
	{
		$qb = $repository->createQueryBuilder('s');

		foreach ($this->select as $modifier) {
			$modifier($qb);
		}

		foreach ($this->filter as $modifier) {
			$modifier($qb);
		}

		return $qb;
	}

	/**
	 * @param ORM\EntityRepository $repository
	 *
	 * @return ORM\QueryBuilder
	 *
	 * @phpstan-param ORM\EntityRepository<T> $repository
	 */
	protected function doCreateCountQuery(ORM\EntityRepository $repository): ORM\QueryBuilder
	{
		return $this->createBasicDql($repository)->select('COUNT(s.id)');
	}

}
