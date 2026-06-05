<?php

use App\Http\Requests\Api\Admin\StoreAdminRequest;
use App\Http\Requests\Api\Admin\StoreClientRequest;
use App\Http\Requests\Api\Admin\UpdateCaseStatusRequest;
use Illuminate\Support\Facades\Validator;

test('update case status request accepts valid statuses only', function () {
    $invalid = Validator::make(['status' => 'archived'], (new UpdateCaseStatusRequest())->rules());
    $valid = Validator::make(['status' => 'resolved'], (new UpdateCaseStatusRequest())->rules());

    expect($invalid->errors()->has('status'))->toBeTrue();
    expect($valid->passes())->toBeTrue();
});

test('store client request requires core identity fields', function () {
    $validator = Validator::make([], (new StoreClientRequest())->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->keys())->toContain('name', 'email', 'password', 'phone', 'city');
});

test('store admin request requires password confirmation', function () {
    $validator = Validator::make([
        'name' => 'Admin',
        'email' => 'admin.new@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different',
    ], (new StoreAdminRequest())->rules());

    expect($validator->errors()->has('password'))->toBeTrue();
});
