<?php

namespace Genesis\SQLExtension\Context\Builder;

use Genesis\SQLExtension\Context\Representations;

/**
 * QueryDirector class.
 *
 * This class forces the builder to be implemented and prepare the object in question
 * in a certain way, meaning that when we get the result of the build method, we have a
 * fully function object in its final state.
 */
class QueryDirector
{
    /**
     * @param QueryBuilder $queryBuilder The query builder to use to build.
     *
     * @return Representations\Query
     */
    public static function build(QueryBuilder $queryBuilder)
    {
        return $queryBuilder
            ->buildQuery()
            ->inferType()
            ->getResult();
    }
}
