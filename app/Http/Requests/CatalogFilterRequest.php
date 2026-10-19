<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

class CatalogFilterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $raw = (string) $this->server('QUERY_STRING', $this->getRequestUri());
        if (($pos = strpos($raw, '?')) !== false) $raw = substr($raw, $pos + 1);
        $slugs = [];
        foreach (explode('&', $raw) as $pair) {
            if ($pair === '') continue;
            [$key, $val] = array_pad(explode('=', $pair, 2), 2, '');
            if (rawurldecode($key) === 'kategori') $slugs[] = trim(rawurldecode(str_replace('+', ' ', $val)));
        }
        $input = $this->query();
        $input['kategori'] = array_values(array_unique(array_filter($slugs, fn ($v) => is_string($v) && $v !== '' && mb_strlen($v) <= 100 && preg_match('/^[a-z0-9-]+$/', $v) === 1)));
        $this->replace($input);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:100'],
            'material' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'regex:/^[1-9]\d{0,9}$/'],
            'kategori' => ['nullable', 'array'],
            'kategori.*' => ['string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            'harga_min' => ['nullable', 'string', 'regex:/^\d{1,10}$/', 'lte:9999999999'],
            'harga_max' => ['nullable', 'string', 'regex:/^\d{1,10}$/', 'lte:9999999999'],
            'tersedia' => ['nullable', 'string', 'in:1'],
            'urut' => ['nullable', 'string', 'in:harga,terbaru,tersedia'],
            'locale' => ['nullable', 'string', 'in:id,en,zh,fr,it'],
            'page' => ['nullable', 'string', 'regex:/^[1-9]\d{0,8}$/'],
        ];
    }

    protected function failedValidation(Validator $validator): void {}

    public function filters(): array
    {
        $clean = [];
        foreach ($this->rules() as $key => $rule) {
            if (str_contains($key, '.*')) continue;
            $subRules = [$key => $rule];
            if ($key === 'kategori') $subRules['kategori.*'] = $this->rules()['kategori.*'];
            $v = ValidatorFacade::make([$key => $this->input($key)], $subRules);
            if ($v->fails()) continue;
            $val = $this->input($key);
            if (is_string($val)) $val = trim($val);
            if ($val !== null && $val !== '' && $val !== []) $clean[$key] = $val;
        }
        $clean['locale'] = $clean['locale'] ?? 'id';
        return $clean;
    }
}
