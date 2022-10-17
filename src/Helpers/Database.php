<?php declare(strict_types = 1);

/**
 * Database.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 * @since          0.19.0
 *
 * @date           19.09.22
 */

namespace FastyBird\Connector\HomeKit\Helpers;

use Doctrine\DBAL;
use Doctrine\ORM;
use Doctrine\Persistence;
use FastyBird\Connector\HomeKit\Exceptions;
use Nette;
use Throwable;
use function gc_collect_cycles;

/**
 * Useful database helpers
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Helpers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Database
{

	use Nette\SmartObject;

	public function __construct(private readonly Persistence\ManagerRegistry $managerRegistry)
	{
	}

	/**
	 * @param callable(): T $callback
	 *
	 * @return T|null
	 *
	 * @throws Exceptions\InvalidState
	 *
	 * @template T
	 */
	public function query(callable $callback)
	{
		try {
			$this->pingAndReconnect();

			return $callback();
		} catch (Throwable $ex) {
			throw new Exceptions\InvalidState('An error occurred: ' . $ex->getMessage(), $ex->getCode(), $ex);
		}
	}

	/**
	 * @param callable(): T $callback
	 *
	 * @return T
	 *
	 * @throws DBAL\Exception
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 *
	 * @template T
	 */
	public function transaction(callable $callback)
	{
		try {
			$this->pingAndReconnect();

			// Start transaction connection to the database
			$this->getConnection()->beginTransaction();

			$result = $callback();

			// Commit all changes into database
			$this->getConnection()->commit();

			return $result;
		} catch (Throwable $ex) {
			// Revert all changes when error occur
			if ($this->getConnection()->isTransactionActive()) {
				$this->getConnection()->rollBack();
			}

			throw new Exceptions\InvalidState('An error occurred: ' . $ex->getMessage(), $ex->getCode(), $ex);
		}
	}

	/**
	 * @throws Exceptions\Runtime
	 */
	public function getConnection(): DBAL\Connection
	{
		$em = $this->getEntityManager();

		if ($em instanceof ORM\EntityManagerInterface) {
			return $em->getConnection();
		}

		throw new Exceptions\Runtime('Entity manager could not be loaded');
	}

	/**
	 * @throws Exceptions\Runtime
	 */
	public function ping(): bool
	{
		$connection = $this->getConnection();

		try {
			$connection->executeQuery($connection->getDatabasePlatform()
				->getDummySelectSQL(), [], []);

		} catch (DBAL\Exception) {
			return false;
		}

		return true;
	}

	/**
	 * @throws DBAL\Exception
	 * @throws Exceptions\Runtime
	 */
	public function reconnect(): void
	{
		$connection = $this->getConnection();

		$connection->close();
		$connection->connect();
	}

	/**
	 * @throws DBAL\Exception
	 * @throws Exceptions\Runtime
	 */
	private function pingAndReconnect(): void
	{
		// Check if ping to DB is possible...
		if (!$this->ping()) {
			// ...if not, try to reconnect
			$this->reconnect();

			// ...and ping again
			if (!$this->ping()) {
				throw new Exceptions\Runtime('Connection to database could not be established');
			}

			$em = $this->getEntityManager();

			if ($em === null) {
				throw new Exceptions\Runtime('Entity manager could not be loaded');
			}

			$em->flush();
			$em->clear();

			// Just in case PHP would choose not to run garbage collection,
			// we run it manually at the end of each batch so that memory is
			// regularly released
			gc_collect_cycles();
		}
	}

	private function getEntityManager(): ORM\EntityManagerInterface|null
	{
		$em = $this->managerRegistry->getManager();

		if ($em instanceof ORM\EntityManagerInterface) {
			if (!$em->isOpen()) {
				$this->managerRegistry->resetManager();

				$em = $this->managerRegistry->getManager();
			}

			if ($em instanceof ORM\EntityManagerInterface) {
				return $em;
			}
		}

		return null;
	}

}
