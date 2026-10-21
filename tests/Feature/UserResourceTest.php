<?php

it('registers the odontologist resource routes', function (): void {
    expect(route('filament.admin.resources.users.index'))->toContain('/users');
    expect(route('filament.admin.resources.users.create'))->toContain('/users/create');
    expect(route('filament.admin.resources.users.edit', ['record' => 1]))->toContain('/users/1/edit');
});
