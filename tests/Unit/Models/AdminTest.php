<?php

use App\Models\Admin;

test('admin belongs to user', function () {
    $user = adminUser();

    expect(Admin::find($user->admin->id)->user->id)->toBe($user->id);
});
