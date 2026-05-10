<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Tenant\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SitePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $page = $this->route('page');
        $pageId = $page?->id;
        $siteId = $page?->site_id ?? $this->input('site_id');

        $rules = [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/',
                Rule::unique('site_pages')->where('site_id', $siteId)->ignore($pageId),
            ],
            'type' => ['sometimes', 'string', 'in:static,blog,product_list,product_detail'],
            'status' => ['sometimes', 'string', 'in:draft,published,archived'],
            'is_homepage' => ['sometimes', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'og_image_asset_id' => ['nullable', 'integer', 'exists:site_assets,id'],
        ];

        if ($this->isMethod('patch') || $this->isMethod('put')) {
            $rules['site_id'] = ['sometimes', 'integer', 'exists:sites,id'];
            $rules['title'] = ['sometimes', 'string', 'max:255'];
            $rules['slug'] = [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/',
                Rule::unique('site_pages')->where('site_id', $siteId)->ignore($pageId),
            ];
        }

        return $rules;
    }
}
