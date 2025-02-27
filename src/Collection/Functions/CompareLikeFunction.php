<?php declare(strict_types = 1);

namespace Nextras\Orm\Collection\Functions;


use Nette\Utils\Strings;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Collection\Aggregations\IArrayAggregator;
use Nextras\Orm\Collection\Aggregations\IDbalAggregator;
use Nextras\Orm\Collection\Expression\LikeExpression;
use Nextras\Orm\Collection\Helpers\ArrayCollectionHelper;
use Nextras\Orm\Collection\Helpers\ArrayPropertyValueReference;
use Nextras\Orm\Collection\Helpers\DbalExpressionResult;
use Nextras\Orm\Collection\Helpers\DbalQueryBuilderHelper;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Exception\InvalidStateException;
use function preg_quote;
use function str_replace;


class CompareLikeFunction implements IArrayFunction, IQueryBuilderFunction
{
	public function processArrayExpression(
		ArrayCollectionHelper $helper,
		IEntity $entity,
		array $args,
		?IArrayAggregator $aggregator = null
	): ArrayPropertyValueReference
	{
		assert(count($args) === 2);

		$valueReference = $helper->getValue($entity, $args[0], $aggregator);

		$likeExpression = $args[1];
		assert($likeExpression instanceof LikeExpression);
		$mode = $likeExpression->getMode();

		if ($valueReference->propertyMetadata !== null) {
			$targetValue = $helper->normalizeValue($likeExpression->getInput(), $valueReference->propertyMetadata, true);
		} else {
			$targetValue = $likeExpression->getInput();
		}

		if ($valueReference->aggregator !== null) {
			$values = array_map(
				function ($value) use ($mode, $targetValue): bool {
					return $this->evaluateInPhp($mode, $value, $targetValue);
				},
				$valueReference->value
			);
			return new ArrayPropertyValueReference(
				$values,
				$valueReference->aggregator,
				null
			);
		} else {
			return new ArrayPropertyValueReference(
				$this->evaluateInPhp($mode, $valueReference->value, $targetValue),
				null,
				null
			);
		}
	}


	public function processQueryBuilderExpression(
		DbalQueryBuilderHelper $helper,
		QueryBuilder $builder,
		array $args,
		?IDbalAggregator $aggregator = null
	): DbalExpressionResult
	{
		assert(count($args) === 2);

		$expression = $helper->processPropertyExpr($builder, $args[0], $aggregator);

		$likeExpression = $args[1];
		assert($likeExpression instanceof LikeExpression);
		$mode = $likeExpression->getMode();

		if ($expression->valueNormalizer !== null) {
			$cb = $expression->valueNormalizer;
			$value = $cb($likeExpression->getInput());
		} else {
			$value = $likeExpression->getInput();
		}

		return $this->evaluateInDb($mode, $expression, $value);
	}


	/**
	 * @param mixed $sourceValue
	 * @param mixed $targetValue
	 */
	protected function evaluateInPhp(int $mode, $sourceValue, $targetValue): bool
	{
		if ($mode === LikeExpression::MODE_RAW) {
			$regexp = '~^' . preg_quote($targetValue, '~') . '$~';
			$regexp = str_replace(['_', '%'], ['.', '.*'], $regexp);
			return Strings::match($sourceValue, $regexp) !== null;

		} elseif ($mode === LikeExpression::MODE_STARTS_WITH) {
			return Strings::startsWith($sourceValue, $targetValue);

		} elseif ($mode === LikeExpression::MODE_ENDS_WITH) {
			return Strings::endsWith($sourceValue, $targetValue);

		} elseif ($mode === LikeExpression::MODE_CONTAINS) {
			$regexp = '~^.*' . preg_quote($targetValue, '~') . '.*$~';
			return Strings::match($sourceValue, $regexp) !== null;

		} else {
			throw new InvalidStateException();
		}
	}


	/**
	 * @param mixed $value
	 */
	protected function evaluateInDb(int $mode, DbalExpressionResult $expression, $value): DbalExpressionResult
	{
		if ($mode === LikeExpression::MODE_RAW) {
			return $expression->append('LIKE %s', $value);
		} elseif ($mode === LikeExpression::MODE_STARTS_WITH) {
			return $expression->append('LIKE %like_', $value);
		} elseif ($mode === LikeExpression::MODE_ENDS_WITH) {
			return $expression->append('LIKE %_like', $value);
		} elseif ($mode === LikeExpression::MODE_CONTAINS) {
			return $expression->append('LIKE %_like_', $value);
		} else {
			throw new InvalidStateException();
		}
	}
}
