// Conversion Tracking routes
$router->get('/advertiser/conversion-pixels', 'ConversionController@conversionPixels');
$router->post('/advertiser/generate-pixel', 'ConversionController@generatePixel');
$router->get('/advertiser/delete-pixel/{pixelId}', 'ConversionController@deletePixel');
$router->get('/advertiser/pixel-code/{pixelId}', 'ConversionController@getPixelCode');
$router->get('/advertiser/conversions', 'ConversionController@advertiserConversions'); 