<?php

namespace VertoAD\Core\Controllers;

class BaseController {
    // Add common controller functionalities here if needed

    /**
     * Helper method to build pagination URLs
     * 
     * @param array $params Current URL parameters
     * @param int $page Page number to set
     * @return string URL with page parameter
     */
    protected function buildPaginationUrl(array $params, $page) {
        $params['page'] = $page;
        return '?' . http_build_query($params);
    }
}
