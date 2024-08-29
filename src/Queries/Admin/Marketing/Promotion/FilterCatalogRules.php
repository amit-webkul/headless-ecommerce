<?php

namespace Webkul\GraphQLAPI\Queries\Admin\Marketing\Promotion;

use Webkul\GraphQLAPI\Queries\BaseFilter;

class FilterCatalogRules extends BaseFilter
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
        // Convert the invoice_date parameter to created_at parameter
        if (isset($input['start'])) {
            $input['starts_from'] = $input['start'];

            unset($input['start']);
        }

        // Convert the grand_total parameter to base_grand_total parameter
        if (isset($input['end'])) {
            $input['ends_till'] = $input['end'];

            unset($input['end']);
        }

        // Convert the grand_total parameter to base_grand_total parameter
        if (isset($input['priority'])) {
            $input['sort_order'] = $input['priority'];

            unset($input['priority']);
        }

        return $query->where($input);
    }
}
