<?php

use App\Models\CaseImage;

test('case image belongs to service case', function () {
    $case = serviceCaseFor(clientUser());

    $image = CaseImage::create([
        'service_case_id' => $case->id,
        'image_path' => 'cases/images/evidence.jpg',
    ]);

    expect($image->serviceCase->id)->toBe($case->id);
    expect($image->getTable())->toBe('service_case_images');
});
