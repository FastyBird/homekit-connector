<?php declare(strict_types = 1);

namespace Tests\Cases\Unit;

use DateTimeImmutable;
use Doctrine\DBAL;
use Doctrine\ORM;
use FastyBird\DateTimeFactory;
use FastyBird\HomeKitConnector\DI;
use FastyBird\HomeKitConnector\Exceptions;
use Mockery;
use Nette;
use Nettrine\ORM as NettrineORM;
use Ninjify\Nunjuck\TestCase\BaseMockeryTestCase;
use RuntimeException;
use function array_reverse;
use function assert;
use function fclose;
use function feof;
use function fgets;
use function fopen;
use function in_array;
use function md5;
use function rtrim;
use function set_time_limit;
use function sprintf;
use function strlen;
use function substr;
use function time;
use function trim;

abstract class DbTestCase extends BaseMockeryTestCase
{

	private Nette\DI\Container|null $container = null;

	private bool $isDatabaseSetUp = false;

	/** @var Array<string> */
	private array $sqlFiles = [];

	/** @var Array<string> */
	private array $neonFiles = [];

	public function setUp(): void
	{
		$this->registerDatabaseSchemaFile(__DIR__ . '/../../sql/dummy.data.sql');

		parent::setUp();

		$dateTimeFactory = Mockery::mock(DateTimeFactory\Factory::class);
		$dateTimeFactory
			->shouldReceive('getNow')
			->andReturn(new DateTimeImmutable('2020-04-01T12:00:00+00:00'));

		$this->mockContainerService(
			DateTimeFactory\Factory::class,
			$dateTimeFactory,
		);
	}

	protected function registerDatabaseSchemaFile(string $file): void
	{
		if (!in_array($file, $this->sqlFiles, true)) {
			$this->sqlFiles[] = $file;
		}
	}

	protected function mockContainerService(
		string $serviceType,
		object $serviceMock,
	): void
	{
		$container = $this->getContainer();
		$foundServiceNames = $container->findByType($serviceType);

		foreach ($foundServiceNames as $serviceName) {
			$this->replaceContainerService($serviceName, $serviceMock);
		}
	}

	protected function getContainer(): Nette\DI\Container
	{
		if ($this->container === null) {
			$this->container = $this->createContainer();
		}

		return $this->container;
	}

	private function createContainer(): Nette\DI\Container
	{
		$rootDir = __DIR__ . '/../../../tests';

		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5((string) time())]]);
		$config->addParameters(['appDir' => $rootDir, 'wwwDir' => $rootDir]);

		$config->addConfig(__DIR__ . '/../../common.neon');

		foreach ($this->neonFiles as $neonFile) {
			$config->addConfig($neonFile);
		}

		DI\HomeKitConnectorExtension::register($config);

		$this->container = $config->createContainer();

		$this->setupDatabase();

		return $this->container;
	}

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
				} catch (DBAL\DBALException) {
					throw new RuntimeException('Database schema could not be created');
				}
			}

			foreach (array_reverse($this->sqlFiles) as $file) {
				$this->loadFromFile($db, $file);
			}

			$this->isDatabaseSetUp = true;
		}
	}

	protected function getDb(): DBAL\Connection
	{
		$service = $this->getContainer()->getByType(DBAL\Connection::class);
		assert($service instanceof DBAL\Connection);

		return $service;
	}

	protected function getEntityManager(): NettrineORM\EntityManagerDecorator
	{
		$service = $this->getContainer()->getByType(NettrineORM\EntityManagerDecorator::class);
		assert($service instanceof NettrineORM\EntityManagerDecorator);

		return $service;
	}

	private function loadFromFile(DBAL\Connection $db, string $file): int
	{
		@set_time_limit(0); // intentionally @

		$handle = @fopen($file, 'r'); // intentionally @

		if ($handle === false) {
			throw new Exceptions\InvalidArgument(sprintf('Cannot open file "%s".', $file));
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
					} catch (DBAL\DBALException) {
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
			} catch (DBAL\DBALException) {
				// File could not be loaded
			}
		}

		fclose($handle);

		return $count;
	}

	private function replaceContainerService(string $serviceName, object $service): void
	{
		$container = $this->getContainer();

		$container->removeService($serviceName);
		$container->addService($serviceName, $service);
	}

	protected function registerNeonConfigurationFile(string $file): void
	{
		if (!in_array($file, $this->neonFiles, true)) {
			$this->neonFiles[] = $file;
		}
	}

	protected function tearDown(): void
	{
		$this->container = null; // Fatal error: Cannot redeclare class SystemContainer
		$this->isDatabaseSetUp = false;

		parent::tearDown();

		Mockery::close();
	}

}
