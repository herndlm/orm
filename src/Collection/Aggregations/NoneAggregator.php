<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Aggregations;


use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalJoinEntry;
use Nextras\Orm\Exception\InvalidStateException;
use function array_merge;
use function array_pop;


/**
 * @implements IArrayAggregator<bool>
 */
class NoneAggregator implements IDbalAggregator, IArrayAggregator
{
	/** @var literal-string */
	private $aggregateKey;


	/**
	 * @param literal-string $aggregateKey
	 */
	public function __construct(string $aggregateKey = 'none')
	{
		$this->aggregateKey = $aggregateKey;
	}


	public function getAggregateKey(): string
	{
		return $this->aggregateKey;
	}


	public function aggregateValues(array $values): bool
	{
		foreach ($values as $value) {
			if ($value) {
				return false;
			}
		}
		return true;
	}


	public function aggregateExpression(
		QueryBuilder $queryBuilder,
		DbalExpressionResult $expression
	): DbalExpressionResult
	{
		$joinExpression = $expression->expression;

		$joinArgs = $expression->args;
		$joins = $expression->joins;
		$join = array_pop($joins);
		if ($join === null) {
			throw new InvalidStateException('Aggregation applied over expression without a relationship');
		}

		$joins[] = new DbalJoinEntry(
			$join->toExpression,
			$join->toArgs,
			$join->toAlias,
			"($join->onExpression) AND $joinExpression",
			array_merge($join->onArgs, $joinArgs),
			$join->conventions
		);

		$primaryKey = $join->conventions->getStoragePrimaryKey()[0];

		return new DbalExpressionResult(
			'COUNT(%table.%column) = 0',
			[$join->toAlias, $primaryKey],
			$joins,
			$expression->groupBy,
			null,
			true,
			null,
			null
		);
	}
}
