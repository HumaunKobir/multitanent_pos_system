<?php

use App\Models\Slider;
use App\Models\User;

test('slider can be updated with patch request', function () {
    $admin = User::factory()->create(['branch_id' => null]);
    $slider = Slider::factory()->create([
        'name' => 'Original Slider',
        'image' => 'sliders/test.jpg',
        'status' => 1,
    ]);

    $this->actingAs($admin)
        ->patch(route('setting.slider.update', $slider), [
            'name' => 'Updated Slider',
            'status' => '0',
        ])
        ->assertRedirect(route('setting.slider.index'));

    expect($slider->fresh())
        ->name->toBe('Updated Slider')
        ->status->toBe(0);
});
