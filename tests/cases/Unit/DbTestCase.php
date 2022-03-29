<?php declare(strict_types = 1);

namespace Tests\Cases;

use DateTimeImmutable;
use Doctrine\DBAL;
use Doctrine\ORM;
use FastyBird\DateTimeFactory;
use FastyBird\DevicesModule\DI;
use FastyBird\DevicesModule\Exceptions;
use Mockery;
use Nette;
use Nettrine\ORM as NettrineORM;
use Ninjify\Nunjuck\TestCase\BaseMockeryTestCase;
use RuntimeException;

abstract class DbTestCase extends BaseMockeryTestCase
{

	/** @var Nette\DI\Container|null */
	private ?Nette\DI\Container $container = null;

	/** @var bool */
	private bool $isDatabaseSetUp = false;

	/** @var string[] */
	private array $sqlFiles = [];

	/** @var string[] */
	private array $neonFiles = [];

	public function setUp(): void
	{
		$this->registerDatabaseSchemaFile(__DIR__ . '/../../sql/dummy.data.sql');

		parent::setUp();

		$dateTimeFactory = Mockery::mock(DateTimeFactory\DateTimeFactory::class);
		$dateTimeFactory
			->shouldReceive('getNow')
			->andReturn(new DateTimeImmutable('2020-04-01T12:00:00+00:00'));

		$this->mockContainerService(
			DateTimeFactory\DateTimeFactory::class,
			$dateTimeFactory
		);
	}

	/**
	 * @param string $file
	 */
	protected function registerDatabaseSchemaFile(string $file): void
	{
		if (!in_array($file, $this->sqlFiles, true)) {
			$this->sqlFiles[] = $file;
		}
	}

	/**
	 * @param string $serviceType
	 * @param object $serviceMock
	 *
	 * @return void
	 */
	protected function mockContainerService(
		string $serviceType,
		object $serviceMock
	): void {
		$container = $this->getContainer();
		$foundServiceNames = $container->findByType($serviceType);

		foreach ($foundServiceNames as $serviceName) {
			$this->replaceContainerService($serviceName, $serviceMock);
		}
	}

	/**
	 * @return Nette\DI\Container
	 */
	protected function getContainer(): Nette\DI\Container
	{
		if ($this->container === null) {
			$this->container = $this->createContainer();
		}

		return $this->container;
	}

	/**
	 * @return Nette\DI\Container
	 */
	private function createContainer(): Nette\DI\Container
	{
		$rootDir = __DIR__ . '/../..';

		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5((string) time())]]);
		$config->addParameters(['appDir' => $rootDir, 'wwwDir' => $rootDir]);

		$config->addConfig(__DIR__ . '/../../common.neon');

		foreach ($this->neonFiles as $neonFile) {
			$config->addConfig($neonFile);
		}

		DI\DevicesModuleExtension::register($config);

		$this->container = $config->createContainer();

		$this->setupDatabase();

		return $this->container;
	}

	/**
	 * @return void
	 */
	private function setupDatabase(): void
	{
		if (!$this->isDatabaseSetUp) {
			$db = $this->getDb();

			$metadatas = $this->getEntityManager()->getMetadataFactory()->getAllMetadata();
			$schemaTool = new ORM\Tools\SchemaTool($this->getEntityManager());

			$schemas = $schemaTool->getCreateSchemaSql($metadatas);

			foreach ($schemas as $sql) {
				try {
					$db->exec($sql);
				} catch (DBAL\DBALException $ex) {
					throw new RuntimeException('Database schema could not be created');
				}
			}

			foreach (array_reverse($this->sqlFiles) as $file) {
				$this->loadFromFile($db, $file);
			}

			$this->isDatabaseSetUp = true;
		}
	}

	/**
	 * @return DBAL\Connection
	 */
	protected function getDb(): DBAL\Connection
	{
		/** @var DBAL\Connection $service */
		$service = $this->getContainer()->getByType(DBAL\Connection::class);

		return $service;
	}

	/**
	 * @return NettrineORM\EntityManagerDecorator
	 */
	protected function getEntityManager(): NettrineORM\EntityManagerDecorator
	{
		/** @var NettrineORM\EntityManagerDecorator $service */
		$service = $this->getContainer()->getByType(NettrineORM\EntityManagerDecorator::class);

		return $service;
	}

	/**
	 * @param DBAL\Connection $db
	 * @param string $file
	 *
	 * @return int
	 */
	private function loadFromFile(DBAL\Connection $db, string $file): int
	{
		@set_time_limit(0); // intentionally @

		$handle = @fopen($file, 'r'); // intentionally @

		if ($handle === false) {
			throw new Exceptions\InvalidArgumentException(sprintf('Cannot open file "%s".', $file));
		}

		$count = 0;
		$delimiter = ';';
		$sql = '';

		while (!feof($handle)) {
			$content = fgets($handle);

			if ($content !== false) {
				$s = rtrim($content);

				if (substr($s, 0, 10) === 'DELIMITER ') {
					$delimiter = substr($s, 10);
				} elseif (substr($s, -strlen($delimiter)) === $delimiter) {
					$sql .= substr($s, 0, -strlen($delimiter));

					try {
						$db->query($sql);
						$sql = '';
						$count++;
					} catch (DBAL\DBALException $ex) {
						// File could not be loaded
					}
				} else {
					$sql .= $s . "\n";
				}
			}
		}

		if (trim($sql) !== '') {
			try {
				$db->query($sql);
				$count++;
			} catch (DBAL\DBALException $ex) {
				// File could not be loaded
			}
		}

		fclose($handle);

		return $count;
	}

	/**
	 * @param string $serviceName
	 * @param object $service
	 *
	 * @return void
	 */
	private function replaceContainerService(string $serviceName, object $service): void
	{
		$container = $this->getContainer();

		$container->removeService($serviceName);
		$container->addService($serviceName, $service);
	}

	/**
	 * @param string $file
	 */
	protected function registerNeonConfigurationFile(string $file): void
	{
		if (!in_array($file, $this->neonFiles, true)) {
			$this->neonFiles[] = $file;
		}
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void
	{
		$this->container = null; // Fatal error: Cannot redeclare class SystemContainer
		$this->isDatabaseSetUp = false;

		parent::tearDown();

		Mockery::close();
	}

}
