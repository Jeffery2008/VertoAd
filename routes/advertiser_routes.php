// Conversion Tracking routes
$router->get('/advertiser/conversion-pixels', 'ConversionController@conversionPixels');
$router->post('/advertiser/generate-pixel', 'ConversionController@generatePixel');
$router->get('/advertiser/delete-pixel/{pixelId}', 'ConversionController@deletePixel');
$router->get('/advertiser/pixel-code/{pixelId}', 'ConversionController@getPixelCode');
$router->get('/advertiser/conversions', 'ConversionController@advertiserConversions');

// Audience Segmentation
$router->get('/advertiser/segments', 'AudienceController@segments');
$router->post('/advertiser/save-segment', 'AudienceController@saveSegment');
$router->get('/advertiser/delete-segment/{id}', 'AudienceController@deleteSegment');
$router->get('/advertiser/segment-members/{id}', 'AudienceController@segmentMembers');
$router->post('/advertiser/update-segment-members/{id}', 'AudienceController@updateDynamicMembers');
$router->get('/advertiser/remove-segment-member/{segment_id}/{visitor_id}', 'AudienceController@removeSegmentMember');
$router->get('/advertiser/audience-insights', 'AudienceController@insights'); 