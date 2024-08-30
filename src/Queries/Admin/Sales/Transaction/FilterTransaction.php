<?php

namespace Webkul\GraphQLAPI\Queries\Admin\Sales\Transaction;

use Webkul\GraphQLAPI\Queries\BaseFilter;

class FilterTransaction extends BaseFilter
{
    /**
     * filter the data .
     *
     * @param  object  $query
     * @param  array  $input
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function __invoke($query, $input)
    {
        return $query->where($input);
    }
}
