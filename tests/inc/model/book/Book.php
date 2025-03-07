<?php declare(strict_types = 1);

namespace NextrasTests\Orm;


use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\ManyHasMany as MHM;


/**
 * @property int|null               $id           {primary}
 * @property string                 $title
 * @property Author                 $author       {m:1 Author::$books}
 * @property Author|null            $translator   {m:1 Author::$translatedBooks}
 * @property MHM&Tag[]              $tags         {m:m Tag::$books, isMain=true}
 * @property Book|null              $nextPart     {1:1 Book::$previousPart, isMain=true}
 * @property Book|null              $previousPart {1:1 Book::$nextPart}
 * @property Ean|null               $ean          {1:1 Ean::$book, isMain=true, cascade=[persist, remove]}
 * @property Publisher              $publisher    {m:1 Publisher::$books}
 * @property DateTimeImmutable      $publishedAt  {default "2021-12-31 23:59:59"}
 * @property DateTimeImmutable|null $printedAt
 * @property Money|null             $price        {embeddable}
 * @property Money|null             $origPrice    {embeddable}
 */
final class Book extends Entity
{
}
