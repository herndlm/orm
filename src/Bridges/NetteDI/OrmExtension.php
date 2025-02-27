<?php declare(strict_types = 1);

namespace Nextras\Orm\Bridges\NetteDI;


use Nette\Caching\Cache;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nextras\Dbal\IConnection;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Entity\Reflection\IMetadataParserFactory;
use Nextras\Orm\Entity\Reflection\MetadataParser;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Mapper\Dbal\DbalMapperCoordinator;
use Nextras\Orm\Model\MetadataStorage;
use Nextras\Orm\Model\Model;
use Nextras\Orm\Repository\IRepository;
use function is_subclass_of;
use function method_exists;


class OrmExtension extends CompilerExtension
{
	/** @var ContainerBuilder */
	protected $builder;

	/** @var IRepositoryFinder */
	protected $repositoryFinder;

	/** @var string */
	protected $modelClass;


	public function loadConfiguration()
	{
		$this->builder = $this->getContainerBuilder();

		$configDefaults = [
			'model' => Model::class,
			'repositoryFinder' => PhpDocRepositoryFinder::class,
		];
		$config = $this->validateConfig($configDefaults); // @phpstan-ignore-line
		$this->modelClass = $config['model'];

		$repositoryFinderClass = $config['repositoryFinder'];
		if (!is_subclass_of($repositoryFinderClass, IRepositoryFinder::class)) {
			throw new InvalidStateException('Repository finder does not implement Nextras\Orm\Bridges\NetteDI\IRepositoryFinder interface.');
		}
		$this->repositoryFinder = new $repositoryFinderClass($this->modelClass, $this->builder, $this);

		$repositories = $this->repositoryFinder->loadConfiguration();

		$this->setupCache();
		$this->setupDependencyProvider();
		$this->setupDbalMapperDependencies();
		$this->setupMetadataParserFactory();

		if ($repositories !== null) {
			$repositoriesConfig = Model::getConfiguration($repositories);
			$this->setupMetadataStorage($repositoriesConfig[2]);
			$this->setupModel($this->modelClass, $repositoriesConfig);
		}
	}


	public function beforeCompile()
	{
		$repositories = $this->repositoryFinder->beforeCompile();

		if ($repositories !== null) {
			$repositoriesConfig = Model::getConfiguration($repositories);
			$this->setupMetadataStorage($repositoriesConfig[2]);
			$this->setupModel($this->modelClass, $repositoriesConfig);
		}

		$this->setupDbalMapperDependencies();
	}


	protected function setupCache(): void
	{
		$cacheName = $this->prefix('cache');
		if ($this->builder->hasDefinition($cacheName)) {
			return;
		}

		$this->builder->addDefinition($cacheName)
			->setType(Cache::class)
			->setArguments([
				'namespace' => $this->name,
			])
			->setAutowired(false);
	}


	protected function setupDependencyProvider(): void
	{
		$providerName = $this->prefix('dependencyProvider');
		if ($this->builder->hasDefinition($providerName)) {
			return;
		}

		$this->builder->addDefinition($providerName)
			->setType(DependencyProvider::class);
	}


	protected function setupDbalMapperDependencies(): void
	{
		if (count($this->builder->findByType(IConnection::class)) === 0) {
			return;
		}

		$name = $this->prefix('mapperCoordinator');
		if (!$this->builder->hasDefinition($name)) {
			$this->builder->addDefinition($name)
				->setType(DbalMapperCoordinator::class);
		}
	}


	protected function setupMetadataParserFactory(): void
	{
		$factoryName = $this->prefix('metadataParserFactory');
		if ($this->builder->hasDefinition($factoryName)) {
			return;
		}

		if (method_exists($this->builder, 'addFactoryDefinition')) { // @phpstan-ignore-line
			$this->builder->addFactoryDefinition($factoryName)
				->setImplement(IMetadataParserFactory::class)
				->getResultDefinition()
				->setType(MetadataParser::class)
				->setArguments(['$entityClassesMap']);
		} else {
			// @phpstan-ignore-next-line
			$this->builder->addDefinition($factoryName)
				->setImplement(IMetadataParserFactory::class)
				->setType(MetadataParser::class)
				->setArguments(['$entityClassesMap']);
		}
	}


	/**
	 * @param array<class-string<IEntity>, class-string<IRepository<IEntity>>> $entityClassMap
	 */
	protected function setupMetadataStorage(array $entityClassMap): void
	{
		$metadataName = $this->prefix('metadataStorage');
		if ($this->builder->hasDefinition($metadataName)) {
			return;
		}

		$this->builder->addDefinition($metadataName)
			->setType(MetadataStorage::class)
			->setArguments([
				'entityClassesMap' => $entityClassMap,
				'cache' => $this->prefix('@cache'),
				'metadataParserFactory' => $this->prefix('@metadataParserFactory'),
				'repositoryLoader' => $this->prefix('@repositoryLoader'),
			]);
	}


	/**
	 * @phpstan-param array{
	 *     array<class-string<\Nextras\Orm\Repository\IRepository<\Nextras\Orm\Entity\IEntity>>, true>,
	 *     array<string, class-string<\Nextras\Orm\Repository\IRepository<\Nextras\Orm\Entity\IEntity>>>,
	 *     array<class-string<\Nextras\Orm\Entity\IEntity>, class-string<\Nextras\Orm\Repository\IRepository<\Nextras\Orm\Entity\IEntity>>>
	 *     } $repositoriesConfig
	 */
	protected function setupModel(string $modelClass, array $repositoriesConfig): void
	{
		$modelName = $this->prefix('model');
		if ($this->builder->hasDefinition($modelName)) {
			return;
		}

		$this->builder->addDefinition($modelName)
			->setType($modelClass)
			->setArguments([
				'configuration' => $repositoriesConfig,
				'repositoryLoader' => $this->prefix('@repositoryLoader'),
				'metadataStorage' => $this->prefix('@metadataStorage'),
			]);
	}
}
