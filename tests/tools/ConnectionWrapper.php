<?php declare(strict_types = 1);

namespace Tests\Tools;

use Doctrine\Common\EventManager;
use Doctrine\DBAL;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;

class ConnectionWrapper extends DBAL\Connection
{

	/** @var string */
	private $dbName;

	/**
	 * @param mixed[] $params
	 * @param Driver $driver
	 * @param Configuration|null $config
	 * @param EventManager|null $eventManager
	 *
	 * @throws DBAL\DBALException
	 */
	public function __construct(
		array $params,
		Driver $driver,
		?Configuration $config = null,
		?EventManager $eventManager = null
	) {
		$this->dbName = 'fb_test_' . getmypid();

		unset($params['dbname']);

		parent::__construct($params, $driver, $config, $eventManager);
	}

	public function connect(): bool
	{
		if (parent::connect()) {
			$this->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $this->dbName));
			$this->exec(sprintf('CREATE DATABASE `%s`', $this->dbName));
			$this->exec(sprintf('USE `%s`', $this->dbName));

			// drop on shutdown
			register_shutdown_function(
				function (): void {
					$this->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $this->dbName));
				}
			);

			return true;
		}

		return false;
	}

}
