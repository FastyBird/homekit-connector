<?php declare(strict_types = 1);

namespace Tests\Tools;

use Doctrine\Common\EventManager;
use Doctrine\DBAL;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use function getmypid;
use function register_shutdown_function;
use function sprintf;

class ConnectionWrapper extends DBAL\Connection
{

	private string $dbName;

	/**
	 * @param Array<mixed> $params
	 *
	 * @throws DBAL\DBALException
	 */
	public function __construct(
		array $params,
		Driver $driver,
		Configuration|null $config = null,
		EventManager|null $eventManager = null,
	)
	{
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
				},
			);

			return true;
		}

		return false;
	}

}
