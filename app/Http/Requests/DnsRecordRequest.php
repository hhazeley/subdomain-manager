<?php

namespace App\Http\Requests;

use App\Models\Subdomain;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DnsRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subdomain = $this->route('subdomain');

        return $subdomain instanceof Subdomain
            && $subdomain->user_id === $this->user()?->id
            && $subdomain->status === Subdomain::STATUS_ACTIVE;
    }

    public function rules(): array
    {
        $type = strtoupper((string) $this->input('type'));

        return [
            'type' => ['required', Rule::in(config('dns.record_types'))],
            'name' => [
                'required',
                'string',
                'max:253',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $name = $this->absoluteName((string) $value);

                    if (! preg_match('/^(?:\*\.)?(?:[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?\.)*[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?$/i', $name)) {
                        $fail('The DNS record name is invalid.');
                    }
                },
            ],
            'content' => ['nullable', 'string', 'max:8192', 'required_without:data'],
            'ttl' => ['required', 'integer', 'min:1', 'max:86400'],
            'proxied' => ['sometimes', 'boolean'],
            'priority' => [
                Rule::requiredIf(in_array($type, config('dns.priority_types'), true)),
                'nullable',
                'integer',
                'min:0',
                'max:65535',
            ],
            'comment' => ['nullable', 'string', 'max:100'],
            'data' => ['nullable', 'json'],
            'tags' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributesForRecord(): array
    {
        $validated = $this->validated();
        $type = strtoupper($validated['type']);

        return [
            'type' => $type,
            'name' => $this->absoluteName($validated['name']),
            'content' => $validated['content'] ?? null,
            'ttl' => (int) $validated['ttl'],
            'proxied' => in_array($type, config('dns.proxy_types'), true)
                && $this->boolean('proxied'),
            'priority' => in_array($type, config('dns.priority_types'), true)
                ? ($validated['priority'] ?? null)
                : null,
            'comment' => $validated['comment'] ?? null,
            'data' => in_array($type, config('dns.structured_types'), true) && isset($validated['data'])
                ? json_decode($validated['data'], true, flags: JSON_THROW_ON_ERROR)
                : null,
            'tags' => $this->parseTags($validated['tags'] ?? ''),
        ];
    }

    private function absoluteName(string $name): string
    {
        $name = strtolower(rtrim(trim($name), '.'));
        $fqdn = strtolower($this->route('subdomain')->fqdn);

        if ($name === '@' || $name === $fqdn) {
            return $fqdn;
        }

        if (str_ends_with($name, ".{$fqdn}")) {
            return $name;
        }

        return "{$name}.{$fqdn}";
    }

    private function parseTags(string $tags): ?array
    {
        $parsed = collect(explode(',', $tags))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->values()
            ->all();

        return $parsed === [] ? null : $parsed;
    }
}
