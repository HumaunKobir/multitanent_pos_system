<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebsiteSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('setting.website.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'website_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'fb_share_for_withdraw' => ['nullable', 'url', 'max:500'],
            'youtube' => ['nullable', 'url', 'max:500'],
            'twit' => ['nullable', 'url', 'max:500'],
            'linkend' => ['nullable', 'url', 'max:500'],
            'topnotice1' => ['nullable', 'string', 'max:500'],
            'footer_description' => ['nullable', 'string', 'max:2000'],
            'support_time' => ['nullable', 'string', 'max:255'],
            'delivery_charge_inside_dhaka' => ['required', 'numeric', 'min:0', 'max:999999'],
            'delivery_charge_outside_dhaka' => ['required', 'numeric', 'min:0', 'max:999999'],
            'meta_tags' => ['nullable', 'string', 'max:500'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'newsletter_enabled' => ['required', 'in:0,1'],
            'newsletter_title' => ['nullable', 'string', 'max:255'],
            'newsletter_description' => ['nullable', 'string', 'max:1000'],
            'newsletter_placeholder' => ['nullable', 'string', 'max:255'],
            'newsletter_button' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp,svg', 'max:2048'],
            'fav_icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp,ico,svg', 'max:1024'],
        ];
    }
}
