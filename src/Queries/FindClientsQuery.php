<?php declare(strict_types = 1);

/**
 * FindClientsQuery.php
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
 * Find clients entities query
 *
 * @phpstan-template T of Entities\Client
 * @phpstan-extends  DoctrineOrmQuery\QueryObject<T>
 *
 * @package          FastyBird:HomeKitConnector!
 * @subpackage       Queries
 * @author           Adam Kadlec <adam.kadlec@fastybird.com>
 */
class FindClientsQuery extends DoctrineOrmQuery\QueryObject
{

	/** @var Array<Closure> */
	private array $filter = [];

	/** @var Array<Closure> */
	private array $select = [];

	/**
	 * @param Uuid\UuidInterface $id
	 *
	 * @return void
	 */
	public function byId(Uuid\UuidInterface $id): void
	{
		$this->filter[] = static function (ORM\QueryBuilder $qb) use ($id): void {
			$qb->andWhere('c.id = :id')->setParameter('id', $id, Uuid\Doctrine\UuidBinaryType::NAME);
		};
	}

	/**
	 * @param string $uid
	 *
	 * @return void
	 */
	public function byUid(string $uid): void
	{
		$this->filter[] = static function (ORM\QueryBuilder $qb) use ($uid): void {
			$qb->andWhere('c.uid = :uid')->setParameter('uid', $uid);
		};
	}

	/**
	 * @param Uuid\UuidInterface $connectorId
	 *
	 * @return void
	 */
	public function byConnectorId(Uuid\UuidInterface $connectorId): void
	{
		$this->select[] = static function (ORM\QueryBuilder $qb): void {
			$qb->addSelect('connector');
			$qb->join('c.connector', 'connector');
		};

		$this->filter[] = static function (ORM\QueryBuilder $qb) use ($connectorId): void {
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
		$this->select[] = static function (ORM\QueryBuilder $qb): void {
			$qb->addSelect('connector');
			$qb->join('c.connector', 'connector');
		};

		$this->filter[] = static function (ORM\QueryBuilder $qb) use ($connector): void {
			$qb->andWhere('connector.id = :connector')
				->setParameter('connector', $connector->getId(), Uuid\Doctrine\UuidBinaryType::NAME);
		};
	}

	/**
	 * @param ORM\EntityRepository<T> $repository
	 *
	 * @return ORM\QueryBuilder
	 */
	protected function doCreateQuery(ORM\EntityRepository $repository): ORM\QueryBuilder
	{
		return $this->createBasicDql($repository);
	}

	/**
	 * @param ORM\EntityRepository<T> $repository
	 *
	 * @return ORM\QueryBuilder
	 */
	private function createBasicDql(ORM\EntityRepository $repository): ORM\QueryBuilder
	{
		$qb = $repository->createQueryBuilder('c');

		foreach ($this->select as $modifier) {
			$modifier($qb);
		}

		foreach ($this->filter as $modifier) {
			$modifier($qb);
		}

		return $qb;
	}

	/**
	 * @param ORM\EntityRepository<T> $repository
	 *
	 * @return ORM\QueryBuilder
	 */
	protected function doCreateCountQuery(ORM\EntityRepository $repository): ORM\QueryBuilder
	{
		return $this->createBasicDql($repository)->select('COUNT(c.id)');
	}

}
