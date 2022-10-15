<?php declare(strict_types = 1);

namespace Tests\Tools;

use Doctrine\Common\EventManager;
use Doctrine\DBAL;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use function getenv;
use function getmypid;
use function is_string;
use function register_shutdown_function;
use function sprintf;

class ConnectionWrapper extends DBAL\Connection
{

	private string $dbName;

	/**
	 * @param Array<mixed> $params
	 *
	 * @throws DBAL\Exception
	 */
	public function __construct(
		array $params,
		Driver $driver,
		Configuration|null $config = null,
		EventManager|null $eventManager = null,
	)
	{
		$this->dbName = is_string(getenv('TEST_TOKEN'))
			? 'fb_test_' . getmypid() . getenv('TEST_TOKEN') ?? ''
			: 'fb_test_' . getmypid();

		unset($params['dbname']);

		parent::__construct($params, $driver, $config, $eventManager);
	}

	public function connect(): bool
	{
		if (parent::connect()) {
			$this->executeStatement(sprintf('DROP DATABASE IF EXISTS `%s`', $this->dbName));
			$this->executeStatement(sprintf('CREATE DATABASE `%s`', $this->dbName));
			$this->executeStatement(sprintf('USE `%s`', $this->dbName));

			// drop on shutdown
			register_shutdown_function(
				function (): void {
					$this->executeStatement(sprintf('DROP DATABASE IF EXISTS `%s`', $this->dbName));
				},
			);

			return true;
		}

		return false;
	}

}
